<?php

namespace App\Services;

use App\Enums\ContactParty;
use App\Enums\Department;
use App\Models\AuditEvent;
use App\Models\RentalFieldInspection;
use App\Models\RentalRecommendation;
use App\Models\RentalRequest;
use App\Models\RentalViewing;
use App\Models\ResaleFieldInspection;
use App\Models\ResaleRecommendation;
use App\Models\ResaleRequest;
use App\Models\ResaleViewing;
use App\Models\User;
use App\Support\PhoneCountries;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class RecommendationService
{
    public function createRecommendation(User $actor, Department $department, int $requestId, array $data): Model
    {
        $this->authorize($actor, $department);
        $request = $this->requestClass($department)::query()->findOrFail($requestId);
        $this->ensureOpen($request);

        $validated = Validator::make($data, [
            'unit_code' => ['nullable', 'string', 'max:100'],
            'listing_code' => ['nullable', 'digits_between:1,30'],
            'unit_type' => ['nullable', 'string', 'max:80'],
            'unit_area' => ['nullable', 'numeric', 'gt:0', 'max:99999999'],
            'location_description' => ['nullable', 'string', 'max:10000'],
            'asking_price' => ['nullable', 'numeric', 'gte:0', 'max:9999999999999'],
            'currency' => ['required', Rule::in(['EGP', 'USD'])],
            'notes' => ['nullable', 'string', 'max:10000'],
        ])->validate();
        if (blank($validated['unit_code'] ?? null) && blank($validated['listing_code'] ?? null) && blank($validated['unit_type'] ?? null) && blank($validated['location_description'] ?? null)) {
            throw ValidationException::withMessages(['unit_code' => 'أدخل كودًا أو نوعًا أو وصف موقع واحدًا على الأقل للتعرف على الوحدة.']);
        }

        return DB::transaction(function () use ($actor, $department, $request, $validated): Model {
            $recommendation = $this->recommendationClass($department)::query()->create([
                ...$validated,
                'request_id' => $request->id,
                'status' => 'proposed',
                'created_by' => $actor->id,
            ]);
            $this->audit($actor, $department, 'recommendation.created', $recommendation, ['request_id' => $request->id, 'status' => 'proposed']);

            return $recommendation;
        });
    }

    public function updateRecommendationStatus(User $actor, Department $department, int $recommendationId, string $status): Model
    {
        $this->authorize($actor, $department);
        Validator::make(['status' => $status], ['status' => ['required', Rule::in(['proposed', 'shared', 'selected', 'rejected', 'withdrawn'])]])->validate();

        return DB::transaction(function () use ($actor, $department, $recommendationId, $status): Model {
            $recommendation = $this->recommendationClass($department)::query()->findOrFail($recommendationId);
            $this->ensureOpen($recommendation->request);
            $before = $recommendation->status;
            $recommendation->update(['status' => $status]);
            $this->audit($actor, $department, 'recommendation.status_changed', $recommendation, ['from' => $before, 'to' => $status]);

            return $recommendation;
        });
    }

    public function recordInspection(User $actor, Department $department, int $recommendationId, array $data): Model
    {
        $this->authorize($actor, $department);

        if (array_key_exists('observed_defects', $data)) {
            throw ValidationException::withMessages([
                'observed_defects' => 'لم يعد تسجيل العيوب متاحًا في الفحص الجديد. استخدم ملاحظات الفحص.',
            ]);
        }

        $brokerTable = $department->value.'_brokers';
        $validated = Validator::make($data, [
            'field_representative_name' => ['required', 'string', 'max:160'],
            'inspected_at' => ['required', 'date', 'before_or_equal:now'],
            'photo_match' => ['required', Rule::in(['matches', 'partly_matches', 'does_not_match'])],
            'contact_role' => ['required', 'string', 'max:16', Rule::in(['owner', 'broker'])],
            'contact_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'phone_country' => ['required', 'string', Rule::in(array_keys(PhoneCountries::options()))],
            'link_request_broker' => ['sometimes', 'boolean'],
            'shown_request_broker_id' => [
                'required_if:link_request_broker,1,true',
                'nullable',
                'integer',
                Rule::exists($brokerTable, 'id'),
            ],
            'notes' => ['nullable', 'string', 'max:10000'],
        ])->validate();

        $contactName = trim($validated['contact_name']);
        if ($contactName === '') {
            throw ValidationException::withMessages(['contact_name' => 'اسم جهة التواصل مطلوب.']);
        }

        $normalizedPhone = app(CustomerPhoneNormalizer::class)->normalize($validated['phone'], $validated['phone_country']);
        $linkRequestBroker = (bool) ($validated['link_request_broker'] ?? false);

        return DB::transaction(function () use ($actor, $department, $recommendationId, $validated, $contactName, $normalizedPhone, $linkRequestBroker): Model {
            $recommendation = $this->recommendationClass($department)::query()
                ->lockForUpdate()
                ->findOrFail($recommendationId);
            $request = $this->requestClass($department)::query()
                ->lockForUpdate()
                ->findOrFail($recommendation->request_id);
            $this->ensureOpen($request);

            if (! in_array($recommendation->status, ['shared', 'selected'], true)) {
                throw ValidationException::withMessages(['recommendation_id' => 'حدّث حالة الترشيح إلى أُرسلت للعميل أو اختارها العميل قبل تسجيل الفحص.']);
            }

            $linkedBrokerId = null;
            if ($linkRequestBroker) {
                if ($validated['contact_role'] !== 'broker') {
                    throw ValidationException::withMessages(['link_request_broker' => 'لا يمكن ربط جهة تواصل مالك بوسيط الطلب.']);
                }

                if (! $request->broker_id) {
                    throw ValidationException::withMessages(['link_request_broker' => 'لا يوجد وسيط مرتبط بهذا الطلب.']);
                }

                if ((int) $request->broker_id !== (int) $validated['shown_request_broker_id']) {
                    throw ValidationException::withMessages(['link_request_broker' => 'تغير وسيط الطلب. راجع بيانات الوسيط وأكد الربط مرة أخرى.']);
                }

                $linkedBrokerId = (int) $request->broker_id;
            }

            $inspection = $this->inspectionClass($department)::query()->create([
                'recommendation_id' => $recommendation->id,
                'recorded_by' => $actor->id,
                'field_representative_name' => $validated['field_representative_name'],
                'inspected_at' => $validated['inspected_at'],
                'photo_match' => $validated['photo_match'],
                'contact_role' => $validated['contact_role'],
                'contact_name' => $contactName,
                'contact_phone_e164' => $normalizedPhone['phone_e164'],
                'contact_phone_country' => $normalizedPhone['phone_country'],
                'linked_broker_id' => $linkedBrokerId,
                'notes' => $validated['notes'] ?? null,
            ]);

            $this->audit($actor, $department, 'field_inspection.recorded', $inspection, [
                'contact_role' => $validated['contact_role'],
                'broker_link_confirmed' => $linkRequestBroker,
                'linked_broker_id' => $linkedBrokerId,
            ]);

            return $inspection;
        });
    }

    public function scheduleViewing(User $actor, Department $department, int $requestId, array $data): Model
    {
        $this->authorize($actor, $department);
        $request = $this->requestClass($department)::query()->findOrFail($requestId);
        $this->ensureOpen($request);
        $recommendationTable = $department->value.'_recommendations';
        $validated = Validator::make($data, [
            'recommendation_id' => ['nullable', 'integer', Rule::exists($recommendationTable, 'id')],
            'scheduled_at' => ['required', 'date', 'after:now'],
            'field_representative_name' => ['nullable', 'string', 'max:160'],
        ])->validate();
        if (! empty($validated['recommendation_id'])) {
            $recommendation = $this->recommendationClass($department)::query()->whereKey($validated['recommendation_id'])->where('request_id', $request->id)->first();
            if (! $recommendation) {
                throw ValidationException::withMessages(['recommendation_id' => 'الترشيح المختار لا يخص هذا الطلب.']);
            }
            if (! $recommendation->inspections()->exists()) {
                throw ValidationException::withMessages(['recommendation_id' => 'سجل فحص الشقة للوحدة قبل تحديد معاينة العميل.']);
            }
        }

        return DB::transaction(function () use ($actor, $department, $request, $validated): Model {
            $viewing = $this->viewingClass($department)::query()->create([...$validated, 'request_id' => $request->id, 'recorded_by' => $actor->id]);
            $this->audit($actor, $department, 'viewing.scheduled', $viewing, ['request_id' => $request->id, 'recommendation_id' => $viewing->recommendation_id]);

            return $viewing;
        });
    }

    public function recordViewingResult(User $actor, Department $department, int $viewingId, array $data): Model
    {
        $this->authorize($actor, $department);
        $viewing = $this->viewingClass($department)::query()->findOrFail($viewingId);
        $this->ensureOpen($viewing->request);
        $rentalResultOptions = ['reserved', 'unsuitable', 'other_apartment_viewing', 'rented_same_unit', 'awaiting_customer_reply'];
        $resaleResultOptions = ['interested', 'not_interested', 'second_viewing', 'negotiating', 'cancelled'];
        $reasonRequiredResult = $department === Department::Rental ? 'unsuitable' : 'not_interested';
        $validated = Validator::make($data, [
            'result' => ['required', Rule::in($department === Department::Rental ? $rentalResultOptions : $resaleResultOptions)],
            'customer_feedback' => ['nullable', 'string', 'max:10000'],
            'rejection_reason' => ['nullable', "required_if:result,{$reasonRequiredResult}", 'string', 'max:10000'],
        ])->validate();
        if ($department === Department::Rental && in_array($validated['result'], ['reserved', 'rented_same_unit'], true)) {
            $request = $viewing->request;
            $hasKnownCustomerOrBroker = filled($request->customer_id)
                || ($request->contact_party === ContactParty::Broker && filled($request->broker_id));

            if (! $hasKnownCustomerOrBroker) {
                throw ValidationException::withMessages(['result' => 'اربط الطلب بعميل معروف أو بوسيط معروف قبل تسجيل «حجز» أو «تم التأجير».']);
            }

            $hasUnitForRequest = filled($viewing->recommendation_id)
                && $this->recommendationClass($department)::query()
                    ->whereKey($viewing->recommendation_id)
                    ->where('request_id', $viewing->request_id)
                    ->exists();

            if (! $hasUnitForRequest) {
                throw ValidationException::withMessages(['viewing_id' => 'اربط المعاينة بوحدة مرشحة تخص هذا الطلب قبل تسجيل «حجز» أو «تم التأجير».']);
            }
        }
        if ($department === Department::Rental && $validated['result'] !== 'unsuitable') {
            $validated['rejection_reason'] = null;
        }

        return DB::transaction(function () use ($actor, $department, $viewing, $validated): Model {
            $before = ['result' => $viewing->result];
            $viewing->update($validated);
            $this->audit($actor, $department, 'viewing.result_recorded', $viewing, [
                'from' => $before['result'],
                'to' => $validated['result'],
                'has_customer_feedback' => filled($validated['customer_feedback'] ?? null),
                'has_rejection_reason' => filled($validated['rejection_reason'] ?? null),
            ]);

            return $viewing;
        });
    }

    private function authorize(User $actor, Department $department): void
    {
        if (! $actor->canWorkInDepartment($department)) {
            abort(403);
        }
    }

    private function ensureOpen(Model $request): void
    {
        if ($request->status->value !== 'active') {
            throw ValidationException::withMessages(['request' => 'لا يمكن تسجيل ترشيحات أو معاينات لطلب غير نشط.']);
        }
    }

    private function requestClass(Department $department): string
    {
        return $department === Department::Rental ? RentalRequest::class : ResaleRequest::class;
    }

    private function recommendationClass(Department $department): string
    {
        return $department === Department::Rental ? RentalRecommendation::class : ResaleRecommendation::class;
    }

    private function inspectionClass(Department $department): string
    {
        return $department === Department::Rental ? RentalFieldInspection::class : ResaleFieldInspection::class;
    }

    private function viewingClass(Department $department): string
    {
        return $department === Department::Rental ? RentalViewing::class : ResaleViewing::class;
    }

    private function audit(User $actor, Department $department, string $event, Model $subject, array $after): void
    {
        AuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'department' => $department->value,
            'event_key' => $event,
            'subject_type' => $subject::class,
            'subject_id' => $subject->getKey(),
            'before_values' => null,
            'after_values' => $after,
            'reason' => null,
            'created_at' => now(),
        ]);
    }
}
