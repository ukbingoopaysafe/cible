<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\AuditEvent;
use App\Models\RentalBroker;
use App\Models\ResaleBroker;
use App\Models\User;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;

class BrokerService
{
    public function __construct(private readonly CustomerPhoneNormalizer $phoneNormalizer) {}

    /**
     * @param array{name: string, phone?: string|null, phone_country?: string|null} $data
     */
    public function update(User $actor, Department $department, int $brokerId, array $data, bool $reviewedSimilarNames = false): Model
    {
        $this->authorizeDepartment($actor, $department);
        $brokerClass = $this->brokerClass($department);
        $broker = $brokerClass::query()->findOrFail($brokerId);
        $attributes = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'phone_country' => ['nullable', 'string', 'size:2'],
        ])->validate();

        $attributes['name'] = trim($attributes['name']);
        if ($attributes['name'] === '') {
            throw ValidationException::withMessages(['name' => 'اسم الوسيط مطلوب.']);
        }
        if (empty($attributes['phone']) && ! empty($attributes['phone_country'])) {
            throw ValidationException::withMessages(['phone_country' => 'أدخل رقم الهاتف قبل اختيار رمز الدولة.']);
        }

        $phone = null;
        if (! empty($attributes['phone'])) {
            $phone = $this->phoneNormalizer->normalize($attributes['phone'], $attributes['phone_country'] ?? null);
            if ($brokerClass::query()->where('phone_e164', $phone['phone_e164'])->whereKeyNot($brokerId)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'رقم الهاتف مسجل لوسيط آخر في هذا القسم. راجع سجل الوسيط القائم.',
                ]);
            }
        }

        $similarBrokers = $this->findSimilarNames($actor, $department, $attributes['name'])
            ->reject(fn (Model $match): bool => (int) $match->getKey() === $brokerId);
        if ($similarBrokers->isNotEmpty() && ! $reviewedSimilarNames) {
            $labels = $similarBrokers->map(fn (Model $match): string => "#{$match->getKey()} {$match->name}")->implode('، ');
            throw ValidationException::withMessages([
                'name' => "قد يوجد وسيط مشابه بهذا الاسم ({$labels}). راجع السجلات ثم أكد المتابعة إذا كان وسيطًا مختلفًا.",
            ]);
        }

        $before = [
            'name' => $broker->name,
            'phone_e164' => $broker->phone_e164,
            'phone_country' => $broker->phone_country,
        ];
        $after = [
            'name' => $attributes['name'],
            'phone_e164' => $phone['phone_e164'] ?? null,
            'phone_country' => $phone['phone_country'] ?? null,
        ];
        if ($before === $after) {
            return $broker;
        }

        try {
            return DB::transaction(function () use ($broker, $after, $before, $actor, $department): Model {
                $broker->name = $after['name'];
                $broker->phone_e164 = $after['phone_e164'];
                $broker->phone_country = $after['phone_country'];
                $broker->save();
                AuditEvent::query()->create([
                    'actor_user_id' => $actor->id,
                    'department' => $department->value,
                    'event_key' => 'broker.updated',
                    'subject_type' => $broker::class,
                    'subject_id' => $broker->getKey(),
                    'before_values' => $before,
                    'after_values' => $after,
                    'reason' => null,
                    'created_at' => now(),
                ]);

                return $broker;
            });
        } catch (QueryException $exception) {
            if ($phone && $brokerClass::query()->where('phone_e164', $phone['phone_e164'])->whereKeyNot($brokerId)->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'رقم الهاتف مسجل لوسيط آخر في هذا القسم. راجع سجل الوسيط القائم.',
                ]);
            }

            throw $exception;
        }
    }

    /**
     * @param array{name: string, phone?: string|null, phone_country?: string|null} $data
     */
    public function create(User $actor, Department $department, array $data, bool $reviewedSimilarNames = false): Model
    {
        $this->authorizeDepartment($actor, $department);
        $attributes = Validator::make($data, [
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'phone_country' => ['nullable', 'string', 'size:2'],
        ])->validate();

        $attributes['name'] = trim($attributes['name']);
        if ($attributes['name'] === '') {
            throw ValidationException::withMessages(['name' => 'اسم الوسيط مطلوب.']);
        }
        if (empty($attributes['phone']) && ! empty($attributes['phone_country'])) {
            throw ValidationException::withMessages(['phone_country' => 'أدخل رقم الهاتف قبل اختيار رمز الدولة.']);
        }

        $brokerClass = $this->brokerClass($department);
        $phone = null;
        if (! empty($attributes['phone'])) {
            $phone = $this->phoneNormalizer->normalize(
                $attributes['phone'],
                $attributes['phone_country'] ?? null,
            );

            if ($brokerClass::query()->where('phone_e164', $phone['phone_e164'])->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'رقم الهاتف مسجل لوسيط آخر في هذا القسم. راجع سجل الوسيط القائم.',
                ]);
            }
        }

        $similarBrokers = $this->findSimilarNames($actor, $department, $attributes['name']);
        if ($similarBrokers->isNotEmpty() && ! $reviewedSimilarNames) {
            $labels = $similarBrokers->map(fn (Model $broker): string => "#{$broker->getKey()} {$broker->name}")->implode('، ');

            throw ValidationException::withMessages([
                'name' => "قد يوجد وسيط مشابه بهذا الاسم ({$labels}). راجع السجلات ثم أكد المتابعة إذا كان وسيطًا مختلفًا.",
            ]);
        }

        $broker = new $brokerClass;
        $broker->name = $attributes['name'];
        $broker->phone_e164 = $phone['phone_e164'] ?? null;
        $broker->phone_country = $phone['phone_country'] ?? null;

        try {
            return DB::transaction(function () use ($broker, $department, $actor): Model {
                $broker->save();

                AuditEvent::query()->create([
                    'actor_user_id' => $actor->id,
                    'department' => $department->value,
                    'event_key' => 'broker.created',
                    'subject_type' => $broker::class,
                    'subject_id' => $broker->getKey(),
                    'before_values' => null,
                    'after_values' => [
                        'name' => $broker->name,
                        'phone_e164' => $broker->phone_e164,
                        'phone_country' => $broker->phone_country,
                    ],
                    'reason' => null,
                    'created_at' => now(),
                ]);

                return $broker;
            });
        } catch (QueryException $exception) {
            if ($phone && $brokerClass::query()->where('phone_e164', $phone['phone_e164'])->exists()) {
                throw ValidationException::withMessages([
                    'phone' => 'رقم الهاتف مسجل لوسيط آخر في هذا القسم. راجع سجل الوسيط القائم.',
                ]);
            }

            throw $exception;
        }

        return $broker;
    }

    /** @return Collection<int, Model> */
    public function findSimilarNames(User $actor, Department $department, string $name): Collection
    {
        $this->authorizeDepartment($actor, $department);
        $normalized = $this->normalizeName($name);
        $matches = [];

        foreach ($this->brokerClass($department)::query()->get(['id', 'name']) as $broker) {
            $existingName = $this->normalizeName($broker->name);
            similar_text($normalized, $existingName, $similarity);

            if ($normalized === $existingName || $similarity >= 88) {
                $matches[] = $broker;
            }
        }

        return new Collection($matches);
    }

    private function normalizeName(string $name): string
    {
        $name = mb_strtolower(trim($name), 'UTF-8');
        $name = strtr($name, ['أ' => 'ا', 'إ' => 'ا', 'آ' => 'ا', 'ة' => 'ه', 'ى' => 'ي']);
        $name = preg_replace('/[\p{Z}\s]+/u', ' ', $name) ?? $name;

        return trim($name);
    }

    private function authorizeDepartment(User $actor, Department $department): void
    {
        if (! $actor->canWorkInDepartment($department)) {
            throw new AuthorizationException('لا تملك صلاحية الوصول إلى هذا القسم.');
        }
    }

    /** @return class-string<RentalBroker|ResaleBroker> */
    private function brokerClass(Department $department): string
    {
        return match ($department) {
            Department::Rental => RentalBroker::class,
            Department::Resale => ResaleBroker::class,
        };
    }
}
