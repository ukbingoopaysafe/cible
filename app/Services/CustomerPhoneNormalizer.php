<?php

namespace App\Services;

use Illuminate\Validation\ValidationException;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

class CustomerPhoneNormalizer
{
    /**
     * @return array{phone_e164: string, phone_country: string}
     */
    public function normalize(string $input, ?string $country): array
    {
        $country = mb_strtoupper(trim($country ?: 'EG'));

        if (! PhoneNumber::isValidCountry($country)) {
            throw ValidationException::withMessages([
                'phone_country' => 'رمز الدولة المحدد غير صالح.',
            ]);
        }

        try {
            $phone = new PhoneNumber(trim($input), $country);
            $detectedCountry = $phone->getCountry();

            if ($detectedCountry !== $country) {
                throw ValidationException::withMessages([
                    'phone' => 'مقدمة الرقم الدولي لا تطابق الدولة المحددة. غيّر الدولة بعد مراجعة الرقم.',
                ]);
            }

            if (! $phone->isValid()) {
                throw ValidationException::withMessages([
                    'phone' => 'رقم الهاتف غير صالح أو غير مكتمل للدولة المحددة.',
                ]);
            }

            return [
                'phone_e164' => $phone->formatE164(),
                'phone_country' => $country,
            ];
        } catch (ValidationException $exception) {
            throw $exception;
        } catch (Throwable) {
            throw ValidationException::withMessages([
                'phone' => 'تعذر التحقق من رقم الهاتف. راجع الرقم وحاول مرة أخرى.',
            ]);
        }
    }
}
