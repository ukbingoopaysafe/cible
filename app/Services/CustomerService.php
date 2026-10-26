<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\RentalCustomer;
use App\Models\ResaleCustomer;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(private readonly CustomerPhoneNormalizer $phoneNormalizer) {}

    /**
     * @param array{name: string, phone: string, phone_country?: string|null, nationality?: string|null} $attributes
     */
    public function create(User $actor, Department $department, array $attributes, bool $reviewedSimilarNames = false): Model
    {
        if (! $actor->canWorkInDepartment($department)) {
            throw new AuthorizationException('لا تملك صلاحية الوصول إلى هذا القسم.');
        }

        $attributes = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'nationality' => ['nullable', 'string', 'max:100'],
        ])->validate();
        $attributes['name'] = trim($attributes['name']);
        if ($attributes['name'] === '') {
            throw ValidationException::withMessages(['name' => 'اسم العميل مطلوب.']);
        }

        $phone = $this->phoneNormalizer->normalize(
            $attributes['phone'],
            $attributes['phone_country'] ?? null,
        );

        $customerClass = match ($department) {
            Department::Rental => RentalCustomer::class,
            Department::Resale => ResaleCustomer::class,
        };

        if ($customerClass::withTrashed()->where('phone_e164', $phone['phone_e164'])->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف مسجل بالفعل في هذا القسم. راجع سجل العميل القائم.',
            ]);
        }

        $similarCustomers = $this->findSimilarNames($customerClass, $attributes['name']);
        if ($similarCustomers->isNotEmpty() && ! $reviewedSimilarNames) {
            $labels = $similarCustomers->map(fn (Model $customer): string => "#{$customer->getKey()} {$customer->name}")->implode('، ');
            throw ValidationException::withMessages([
                'name' => "قد يوجد عميل مشابه بهذا الاسم ({$labels}). راجع السجلات ثم أكد المتابعة إذا كان عميلًا مختلفًا.",
            ]);
        }

        $customer = new $customerClass;
        $customer->name = $attributes['name'];
        $customer->phone_e164 = $phone['phone_e164'];
        $customer->phone_country = $phone['phone_country'];
        $customer->nationality = $attributes['nationality'] ?? null;

        try {
            return DB::transaction(function () use ($customer, $actor, $department): Model {
                $customer->save();
                $this->recordAudit($actor, $department, 'customer.created', $customer, null, $this->customerAuditValues($customer));

                return $customer;
            });
        } catch (QueryException $exception) {
            if ($customerClass::withTrashed()->where('phone_e164', $phone['phone_e164'])->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'رقم الهاتف مسجل بالفعل في هذا القسم. راجع سجل العميل القائم.',
                ]);
            }

            throw $exception;
        }
    }

    /**
     * @param array{name: string, phone: string, phone_country?: string|null, nationality?: string|null} $attributes
     */
    public function update(User $actor, Department $department, int $customerId, array $attributes, bool $reviewedSimilarNames = false): Model
    {
        if (! $actor->canWorkInDepartment($department)) {
            throw new AuthorizationException('لا تملك صلاحية الوصول إلى هذا القسم.');
        }

        $customerClass = match ($department) {
            Department::Rental => RentalCustomer::class,
            Department::Resale => ResaleCustomer::class,
        };
        $customer = $customerClass::query()->findOrFail($customerId);
        $before = $this->customerAuditValues($customer);
        $attributes = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:40'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'nationality' => ['nullable', 'string', 'max:100'],
        ])->validate();
        $attributes['name'] = trim($attributes['name']);
        if ($attributes['name'] === '') {
            throw ValidationException::withMessages(['name' => 'اسم العميل مطلوب.']);
        }

        $phone = $this->phoneNormalizer->normalize($attributes['phone'], $attributes['phone_country'] ?? null);
        if ($customerClass::withTrashed()->where('phone_e164', $phone['phone_e164'])->whereKeyNot($customerId)->exists()) {
            throw ValidationException::withMessages([
                'phone' => 'رقم الهاتف مسجل بالفعل لعميل آخر في هذا القسم. راجع سجل العميل القائم.',
            ]);
        }

        $similarCustomers = $this->findSimilarNames($customerClass, $attributes['name'])
            ->reject(fn (Model $match): bool => (int) $match->getKey() === $customerId);
        if ($similarCustomers->isNotEmpty() && ! $reviewedSimilarNames) {
            $labels = $similarCustomers->map(fn (Model $match): string => "#{$match->getKey()} {$match->name}")->implode('، ');
            throw ValidationException::withMessages([
                'name' => "قد يوجد عميل مشابه بهذا الاسم ({$labels}). راجع السجلات ثم أكد المتابعة إذا كان عميلًا مختلفًا.",
            ]);
        }

        $customer->name = $attributes['name'];
        $customer->phone_e164 = $phone['phone_e164'];
        $customer->phone_country = $phone['phone_country'];
        $customer->nationality = $attributes['nationality'] ?? null;

        try {
            return DB::transaction(function () use ($customer, $before, $actor, $department): Model {
                $customer->save();
                $after = $this->customerAuditValues($customer);
                $changedBefore = $changedAfter = [];
                foreach ($after as $key => $value) {
                    if ((string) ($before[$key] ?? '') !== (string) ($value ?? '')) {
                        $changedBefore[$key] = $before[$key] ?? null;
                        $changedAfter[$key] = $value;
                    }
                }
                if ($changedAfter !== []) {
                    $this->recordAudit($actor, $department, 'customer.updated', $customer, $changedBefore, $changedAfter);
                }

                return $customer;
            });
        } catch (QueryException $exception) {
            if ($customerClass::withTrashed()->where('phone_e164', $phone['phone_e164'])->whereKeyNot($customerId)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'رقم الهاتف مسجل بالفعل لعميل آخر في هذا القسم. راجع سجل العميل القائم.',
                ]);
            }

            throw $exception;
        }
    }

    public function archive(User $actor, Department $department, int $customerId): void
    {
        if (! $actor->canManageDepartment($department)) {
            throw new AuthorizationException('أرشفة العملاء متاحة لمدير الشركة داخل القسم فقط.');
        }

        $customerClass = $department === Department::Rental ? RentalCustomer::class : ResaleCustomer::class;

        DB::transaction(function () use ($actor, $department, $customerClass, $customerId): void {
            $customer = $customerClass::withTrashed()->lockForUpdate()->findOrFail($customerId);
            if ($customer->trashed()) {
                return;
            }

            if ($customer->requests()->where('status', '!=', 'closed')->exists()) {
                throw ValidationException::withMessages([
                    'customer' => 'أغلق طلبات العميل أو أعد تعيينها قبل أرشفة العميل.',
                ]);
            }

            $before = $this->customerAuditValues($customer);
            $customer->delete();
            $this->recordAudit($actor, $department, 'customer.archived', $customer, $before, [
                'archived' => true,
            ]);
        });
    }

    public function restore(User $actor, Department $department, int $customerId): void
    {
        if (! $actor->canManageDepartment($department)) {
            throw new AuthorizationException('استعادة العملاء متاحة لمدير الشركة داخل القسم فقط.');
        }

        $customerClass = $department === Department::Rental ? RentalCustomer::class : ResaleCustomer::class;

        DB::transaction(function () use ($actor, $department, $customerClass, $customerId): void {
            $customer = $customerClass::withTrashed()->lockForUpdate()->findOrFail($customerId);
            if (! $customer->trashed()) {
                return;
            }

            $customer->restore();
            $this->recordAudit($actor, $department, 'customer.restored', $customer, [
                'archived' => true,
            ], [
                'archived' => false,
            ]);
        });
    }

    /** @return array<string, mixed> */
    private function customerAuditValues(Model $customer): array
    {
        return [
            'name' => $customer->name,
            'phone_e164' => $customer->phone_e164,
            'phone_country' => $customer->phone_country,
            'nationality' => $customer->nationality,
        ];
    }

    /** @param array<string, mixed>|null $before @param array<string, mixed> $after */
    private function recordAudit(User $actor, Department $department, string $event, Model $customer, ?array $before, array $after): void
    {
        AuditEvent::query()->create([
            'actor_user_id' => $actor->id,
            'department' => $department->value,
            'event_key' => $event,
            'subject_type' => $customer::class,
            'subject_id' => $customer->getKey(),
            'before_values' => $before,
            'after_values' => $after,
            'reason' => null,
            'created_at' => now(),
        ]);
    }

    /** @param class-string<Model> $customerClass */
    private function findSimilarNames(string $customerClass, string $name): \Illuminate\Database\Eloquent\Collection
    {
        $normalized = $this->normalizeName($name);
        $matches = [];

        foreach ($customerClass::query()->get(['id', 'name']) as $customer) {
            similar_text($normalized, $this->normalizeName($customer->name), $similarity);
            if ($normalized === $this->normalizeName($customer->name) || $similarity >= 88) {
                $matches[] = $customer;
            }
        }

        return new \Illuminate\Database\Eloquent\Collection($matches);
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);
        $name = preg_replace('/[\p{Z}\s]+/u', ' ', $name) ?? $name;

        return trim($name);
    }
}
