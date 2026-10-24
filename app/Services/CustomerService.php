<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\RentalCustomer;
use App\Models\ResaleCustomer;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
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

        if ($customerClass::query()->where('phone_e164', $phone['phone_e164'])->exists()) {
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
            $customer->save();
        } catch (QueryException $exception) {
            if ($customerClass::query()->where('phone_e164', $phone['phone_e164'])->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'رقم الهاتف مسجل بالفعل في هذا القسم. راجع سجل العميل القائم.',
                ]);
            }

            throw $exception;
        }

        return $customer;
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
        if ($customerClass::query()->where('phone_e164', $phone['phone_e164'])->whereKeyNot($customerId)->exists()) {
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
            $customer->save();
        } catch (QueryException $exception) {
            if ($customerClass::query()->where('phone_e164', $phone['phone_e164'])->whereKeyNot($customerId)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'رقم الهاتف مسجل بالفعل لعميل آخر في هذا القسم. راجع سجل العميل القائم.',
                ]);
            }

            throw $exception;
        }

        return $customer;
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
