<?php

namespace App\Services;

use App\Enums\Department;
use App\Models\ContactPhoneNumber;
use App\Models\RentalCustomer;
use App\Models\ResaleCustomer;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class ContactPhoneNumberService
{
    public function __construct(private readonly CustomerPhoneNormalizer $phoneNormalizer) {}

    /**
     * @param array<int|string, array{phone?: string|null, phone_country?: string|null, is_primary?: bool|string|int|null}> $phones
     * @return list<array{phone_e164: string, phone_country: string, is_primary: bool, sort_order: int, input_key: int|string}>
     */
    public function prepareFor(Model $contact, array $phones, bool $required): array
    {
        $normalized = [];
        $seen = [];

        foreach ($phones as $rowKey => $row) {
            $errorKey = 'phones.'.(string) $rowKey;
            if (! is_array($row)) {
                throw ValidationException::withMessages([$errorKey => 'بيانات رقم الهاتف غير صالحة.']);
            }

            $input = trim((string) ($row['phone'] ?? ''));
            $country = trim((string) ($row['phone_country'] ?? ''));

            if ($input === '' && $country === '') {
                continue;
            }

            if ($input === '' || $country === '') {
                throw ValidationException::withMessages([
                    $errorKey.'.phone' => 'أدخل رقم الهاتف واختر الدولة لكل رقم.',
                ]);
            }

            try {
                $phone = $this->phoneNormalizer->normalize($input, $country);
            } catch (ValidationException $exception) {
                throw ValidationException::withMessages([
                    $errorKey.'.phone' => collect($exception->errors())->flatten()->first() ?? 'رقم الهاتف غير صالح.',
                ]);
            }

            $e164 = $phone['phone_e164'];
            if (isset($seen[$e164])) {
                throw ValidationException::withMessages([
                    $errorKey.'.phone' => 'هذا الرقم مكرر في قائمة جهة الاتصال نفسها.',
                ]);
            }
            $seen[$e164] = true;

            $isPrimary = filter_var($row['is_primary'] ?? false, FILTER_VALIDATE_BOOLEAN);
            $normalized[] = [
                'phone_e164' => $e164,
                'phone_country' => $phone['phone_country'],
                'is_primary' => $isPrimary,
                'sort_order' => count($normalized),
                'input_key' => $rowKey,
            ];
        }

        if ($required && $normalized === []) {
            throw ValidationException::withMessages([
                'phones' => 'أضف رقم هاتف واحدًا على الأقل للعميل.',
            ]);
        }

        if ($normalized !== [] && count(array_filter($normalized, fn (array $phone): bool => $phone['is_primary'])) !== 1) {
            throw ValidationException::withMessages([
                'phones' => 'حدد رقمًا أساسيًا واحدًا فقط.',
            ]);
        }

        $this->assertAvailable($contact, $normalized);

        return $normalized;
    }

    /**
     * @param list<array{phone_e164: string, phone_country: string, is_primary: bool, sort_order: int, input_key: int|string}> $normalizedPhones
     * @return Collection<int, ContactPhoneNumber>
     */
    public function replaceFor(Model $contact, array $normalizedPhones): Collection
    {
        if (! $contact->exists || $contact->getKey() === null) {
            throw new \LogicException('The contact must be saved before replacing its phone numbers.');
        }

        try {
            return DB::transaction(function () use ($contact, $normalizedPhones): Collection {
                $lockedContact = $contact->newModelQuery()
                    ->whereKey($contact->getKey())
                    ->lockForUpdate()
                    ->firstOrFail();

                $this->assertAvailable($lockedContact, $normalizedPhones);
                $lockedContact->phoneNumbers()->delete();

                foreach ($normalizedPhones as $phone) {
                    unset($phone['input_key']);
                    $lockedContact->phoneNumbers()->create($phone);
                }

                return $lockedContact->phoneNumbers()->get();
            });
        } catch (QueryException $exception) {
            if ($this->isUniqueConstraintViolation($exception)) {
                $this->throwDuplicateError($contact, $normalizedPhones);
            }

            throw $exception;
        }
    }

    /** @param list<array{phone_e164: string, phone_country: string, is_primary: bool, sort_order: int, input_key: int|string}> $phones */
    private function assertAvailable(Model $contact, array $phones): void
    {
        if ($phones === []) {
            return;
        }

        $query = ContactPhoneNumber::query()
            ->where('phoneable_type', $contact->getMorphClass())
            ->whereIn('phone_e164', array_column($phones, 'phone_e164'));

        if ($contact->exists) {
            $query->where('phoneable_id', '!=', $contact->getKey());
        }

        if ($query->exists()) {
            $this->throwDuplicateError($contact, $phones);
        }
    }

    private function isUniqueConstraintViolation(QueryException $exception): bool
    {
        $driverCode = $exception->errorInfo[1] ?? null;
        $message = strtolower($exception->getMessage());

        return (string) $exception->getCode() === '23505'
            || (int) $driverCode === 1062
            || str_contains($message, 'contact_phones_type_number_unique')
            || str_contains($message, 'unique constraint failed: contact_phone_numbers.phoneable_type, contact_phone_numbers.phone_e164');
    }

    /** @param list<array{phone_e164: string, phone_country: string, is_primary: bool, sort_order: int, input_key: int|string}> $phones */
    private function throwDuplicateError(Model $contact, array $phones): never
    {
        $phone = $contact instanceof RentalCustomer || $contact instanceof ResaleCustomer
            ? 'رقم الهاتف مسجل بالفعل لعميل آخر في هذا القسم.'
            : 'رقم الهاتف مسجل بالفعل لوسيط آخر في هذا القسم.';
        $target = $phones[0]['phone_e164'] ?? null;
        $index = 0;

        if ($target !== null) {
            foreach ($phones as $phoneIndex => $candidate) {
                $query = ContactPhoneNumber::query()
                    ->where('phoneable_type', $contact->getMorphClass())
                    ->where('phone_e164', $candidate['phone_e164']);

                if ($contact->exists) {
                    $query->where('phoneable_id', '!=', $contact->getKey());
                }

                if ($query->exists()) {
                    $index = $candidate['input_key'] ?? $phoneIndex;
                    break;
                }
            }
        }

        throw ValidationException::withMessages([
            "phones.{$index}.phone" => $phone.' راجع السجل القائم.',
        ]);
    }
}
