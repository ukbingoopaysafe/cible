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
    public function create(User $actor, Department $department, array $attributes): Model
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
}
