<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\RentalCustomer;
use App\Models\ResaleCustomer;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;

class CustomerService
{
    public function __construct(private readonly ContactPhoneNumberService $contactPhoneNumbers) {}

    public function hasSimilarNames(Department $department, string $name, ?int $ignoreCustomerId = null): bool
    {
        $name = trim($name);

        if ($name === '') {
            return false;
        }

        $customerClass = match ($department) {
            Department::Rental => RentalCustomer::class,
            Department::Resale => ResaleCustomer::class,
        };

        return $this->findSimilarNames($customerClass, $name)
            ->contains(fn (Model $customer): bool => $ignoreCustomerId === null || (int) $customer->getKey() !== $ignoreCustomerId);
    }

    /**
     * @param array{name: string, phones?: array, phone?: string|null, phone_country?: string|null, nationality?: string|null} $attributes
     */
    public function create(User $actor, Department $department, array $attributes, bool $reviewedSimilarNames = false): Model
    {
        if (! $actor->canWorkInDepartment($department)) {
            throw new AuthorizationException('لا تملك صلاحية الوصول إلى هذا القسم.');
        }

        $attributes = Validator::make($attributes, [
            'name' => ['required', 'string', 'max:255'],
            'phones' => ['sometimes', 'array'],
            'phones.*.phone' => ['nullable', 'string', 'max:40'],
            'phones.*.phone_country' => ['nullable', 'string', 'size:2'],
            'phones.*.is_primary' => ['nullable', 'boolean'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'nationality' => ['nullable', 'string', 'max:100'],
        ])->validate();
        $attributes['name'] = trim($attributes['name']);
        if ($attributes['name'] === '') {
            throw ValidationException::withMessages(['name' => 'اسم العميل مطلوب.']);
        }

        $customerClass = match ($department) {
            Department::Rental => RentalCustomer::class,
            Department::Resale => ResaleCustomer::class,
        };

        $similarCustomers = $this->findSimilarNames($customerClass, $attributes['name']);
        if ($similarCustomers->isNotEmpty() && ! $reviewedSimilarNames) {
            $labels = $similarCustomers->map(fn (Model $customer): string => "#{$customer->getKey()} {$customer->name}")->implode('، ');
            throw ValidationException::withMessages([
                'name' => "قد يوجد عميل مشابه بهذا الاسم ({$labels}). راجع السجلات ثم أكد المتابعة إذا كان عميلًا مختلفًا.",
            ]);
        }

        $customer = new $customerClass;
        $phones = $this->contactPhoneNumbers->prepareFor($customer, $this->phoneRows($attributes), required: true);
        $customer->name = $attributes['name'];
        $customer->nationality = $attributes['nationality'] ?? null;

        return DB::transaction(function () use ($customer, $phones, $actor, $department): Model {
            $customer->save();
            $this->contactPhoneNumbers->replaceFor($customer, $phones);
            $this->recordAudit($actor, $department, 'customer.created', $customer, null, $this->customerAuditValues($customer));

            return $customer;
        });
    }

    /**
     * @param array{name: string, phones?: array, phone?: string|null, phone_country?: string|null, nationality?: string|null} $attributes
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
            'phones' => ['sometimes', 'array'],
            'phones.*.phone' => ['nullable', 'string', 'max:40'],
            'phones.*.phone_country' => ['nullable', 'string', 'size:2'],
            'phones.*.is_primary' => ['nullable', 'boolean'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:40'],
            'phone_country' => ['nullable', 'string', 'size:2'],
            'nationality' => ['nullable', 'string', 'max:100'],
        ])->validate();
        $attributes['name'] = trim($attributes['name']);
        if ($attributes['name'] === '') {
            throw ValidationException::withMessages(['name' => 'اسم العميل مطلوب.']);
        }

        $phones = $this->contactPhoneNumbers->prepareFor($customer, $this->phoneRows($attributes), required: true);

        $similarCustomers = $this->findSimilarNames($customerClass, $attributes['name'])
            ->reject(fn (Model $match): bool => (int) $match->getKey() === $customerId);
        if ($similarCustomers->isNotEmpty() && ! $reviewedSimilarNames) {
            $labels = $similarCustomers->map(fn (Model $match): string => "#{$match->getKey()} {$match->name}")->implode('، ');
            throw ValidationException::withMessages([
                'name' => "قد يوجد عميل مشابه بهذا الاسم ({$labels}). راجع السجلات ثم أكد المتابعة إذا كان عميلًا مختلفًا.",
            ]);
        }

        $customer->name = $attributes['name'];
        $customer->nationality = $attributes['nationality'] ?? null;

        return DB::transaction(function () use ($customer, $phones, $before, $actor, $department): Model {
            $customer->save();
            $this->contactPhoneNumbers->replaceFor($customer, $phones);
            $after = $this->customerAuditValues($customer);
            $changedBefore = $changedAfter = [];
            foreach ($after as $key => $value) {
                if (($before[$key] ?? null) !== ($value ?? null)) {
                    $changedBefore[$key] = $before[$key] ?? null;
                    $changedAfter[$key] = $value;
                }
            }
            if ($changedAfter !== []) {
                $this->recordAudit($actor, $department, 'customer.updated', $customer, $changedBefore, $changedAfter);
            }

            return $customer;
        });
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
            'phones' => $customer->phoneNumbers()->get()->map(fn (Model $phone): array => [
                'phone_e164' => $phone->phone_e164,
                'phone_country' => $phone->phone_country,
                'is_primary' => $phone->is_primary,
                'sort_order' => $phone->sort_order,
            ])->all(),
            'nationality' => $customer->nationality,
        ];
    }

    /** @param array<string, mixed> $attributes
     *  @return array<int|string, array{phone?: string|null, phone_country?: string|null, is_primary?: bool|string|int|null}>
     */
    private function phoneRows(array $attributes): array
    {
        if (array_key_exists('phones', $attributes)) {
            return is_array($attributes['phones']) ? $attributes['phones'] : [];
        }

        return [[
            'phone' => $attributes['phone'] ?? null,
            'phone_country' => $attributes['phone_country'] ?? 'EG',
            'is_primary' => true,
        ]];
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
