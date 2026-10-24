<?php

namespace App\Services;

use App\Enums\Department;
use App\Enums\RequestOutcome;
use App\Enums\RequestStatus;
use App\Models\AuditEvent;
use App\Models\RentalDeal;
use App\Models\RentalRequest;
use App\Models\ResaleDeal;
use App\Models\ResaleRequest;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class DealService
{
    /** @param array<string, mixed> $data */
    public function createFromRequest(User $actor, Department $department, int $requestId, array $data): Model
    {
        if (! $actor->canWorkInDepartment($department)) {
            throw new AuthorizationException('لا تملك صلاحية الوصول إلى هذا القسم.');
        }

        $requestClass = $department === Department::Rental ? RentalRequest::class : ResaleRequest::class;
        $dealClass = $department === Department::Rental ? RentalDeal::class : ResaleDeal::class;
        $validated = Validator::make($data, [
            'deal_date' => ['required', 'date'],
            'agreed_total_value' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'currency' => ['required', Rule::in(['EGP', 'USD'])],
            'unit_code' => ['nullable', 'string', 'max:40', 'regex:/^[0-9]+$/'],
            'unit_type' => ['nullable', 'string', 'max:80'],
            'unit_area' => ['nullable', 'numeric', 'gt:0', 'max:99999999.99'],
            'unit_location' => ['nullable', 'string', 'max:255'],
            'contact_representative' => ['nullable', 'string', 'max:160'],
            'field_agent_name' => ['nullable', 'string', 'max:160'],
            'notes' => ['nullable', 'string', 'max:10000'],
            'rental_term' => [$department === Department::Rental ? 'required' : 'nullable', Rule::in(['daily', 'monthly', 'half_yearly', 'yearly'])],
            'rental_term_count' => [$department === Department::Rental ? 'required' : 'nullable', 'integer', 'min:1', 'max:120'],
            'payment_schedule' => [$department === Department::Rental ? 'required' : 'nullable', Rule::in(['monthly', 'daily'])],
            'seller_name' => ['nullable', 'string', 'max:160'],
            'seller_phone' => ['nullable', 'string', 'max:30'],
            'commissions' => ['nullable', 'array', 'max:20'],
            'commissions.*.payer' => ['required', 'string', 'max:160'],
            'commissions.*.recipient' => ['required', 'string', 'max:160'],
            'commissions.*.calculation_type' => ['required', Rule::in(['amount', 'percentage'])],
            'commissions.*.agreed_value' => ['required', 'numeric', 'gt:0', 'max:9999999999999.99'],
            'commissions.*.conditions' => ['nullable', 'string', 'max:5000'],
        ])->after(function ($validator) use ($department, $data): void {
            if ($department === Department::Rental) {
                $expectedSchedule = ($data['rental_term'] ?? null) === 'daily' ? 'daily' : 'monthly';
                if (($data['payment_schedule'] ?? null) !== $expectedSchedule) {
                    $validator->errors()->add('payment_schedule', $expectedSchedule === 'daily'
                        ? 'يجب أن تكون دورية الدفع يومية للإيجار اليومي.'
                        : 'يجب أن تكون دورية الدفع شهرية للإيجار الشهري أو نصف السنوي أو السنوي.');
                }
            }
            foreach (is_array($data['commissions'] ?? null) ? $data['commissions'] : [] as $index => $commission) {
                if (! is_array($commission)) {
                    continue;
                }
                if (($commission['calculation_type'] ?? null) === 'percentage' && (float) ($commission['agreed_value'] ?? 0) > 100) {
                    $validator->errors()->add("commissions.{$index}.agreed_value", 'لا يمكن أن تتجاوز نسبة العمولة 100%.');
                }
            }
        })->validate();

        return DB::transaction(function () use ($actor, $department, $requestId, $requestClass, $dealClass, $validated): Model {
            $request = $requestClass::query()->lockForUpdate()->findOrFail($requestId);
            if ($request->status !== RequestStatus::Closed || $request->outcome !== RequestOutcome::Successful) {
                throw ValidationException::withMessages(['request' => 'لا يمكن إنشاء صفقة إلا من طلب مغلق بنتيجة ناجحة.']);
            }
            if ($dealClass::query()->where('request_id', $requestId)->exists()) {
                throw ValidationException::withMessages(['request' => 'يوجد بالفعل سجل صفقة مرتبط بهذا الطلب.']);
            }

            $rentalFields = $department === Department::Rental ? [
                'rental_term' => $validated['rental_term'] ?? $request->rental_term?->value,
                'rental_term_count' => $validated['rental_term_count'] ?? $request->rental_term_count,
                'payment_schedule' => $validated['payment_schedule'] ?? 'monthly',
            ] : [
                'seller_name' => $validated['seller_name'] ?? null,
                'seller_phone' => $validated['seller_phone'] ?? null,
            ];
            $deal = $dealClass::query()->create([
                'request_id' => $request->id,
                'customer_id' => $request->customer_id,
                'broker_id' => $request->broker_id,
                'deal_date' => $validated['deal_date'],
                'agreed_total_value' => $validated['agreed_total_value'],
                'currency' => $validated['currency'],
                'unit_code' => $validated['unit_code'] ?? null,
                'unit_type' => $validated['unit_type'] ?? null,
                'unit_area' => $validated['unit_area'] ?? null,
                'unit_location' => $validated['unit_location'] ?? null,
                'contact_representative' => $validated['contact_representative'] ?? null,
                'field_agent_name' => $validated['field_agent_name'] ?? null,
                'notes' => $validated['notes'] ?? null,
                ...$rentalFields,
            ]);

            foreach ($validated['commissions'] ?? [] as $commission) {
                $deal->commissions()->create($commission);
            }

            app(AftercareService::class)->startForDeal($actor, $department, $deal);

            AuditEvent::query()->create([
                'actor_user_id' => $actor->id,
                'department' => $department->value,
                'event_key' => 'deal.created',
                'subject_type' => $deal::class,
                'subject_id' => $deal->id,
                'before_values' => null,
                'after_values' => ['request_id' => $request->id, 'agreed_total_value' => $deal->agreed_total_value, 'currency' => $deal->currency],
                'reason' => null,
                'created_at' => now(),
            ]);

            return $deal;
        });
    }
}
