<?php

namespace App\Services;

use App\Support\PhoneCountries;
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
        $country = mb_strtoupper(trim($country ?: PhoneCountries::DEFAULT));
        $input = trim($input);

        if (PhoneCountries::isOther($country)) {
            return $this->normalizeInternational($input);
        }

        if (! PhoneNumber::isValidCountry($country)) {
            throw ValidationException::withMessages([
                'phone_country' => 'رمز الدولة المحدد غير صالح.',
            ]);
        }

        try {
            $allowedCountries = $country === 'US' ? ['US', 'CA'] : [$country];
            $phone = new PhoneNumber($input, $allowedCountries);

            if (str_starts_with($input, '+')) {
                $detectedCountry = $phone->getCountry();

                if (! in_array($detectedCountry, $allowedCountries, true)) {
                    throw ValidationException::withMessages([
                        'phone' => 'مقدمة الرقم الدولي لا تطابق الدولة المحددة ('.PhoneCountries::name($country).'). غيّر الدولة بعد مراجعة الرقم.',
                    ]);
                }
            }

            if (! $phone->isValid()) {
                $example = PhoneCountries::example($country);

                throw ValidationException::withMessages([
                    'phone' => 'رقم الهاتف غير صالح أو غير مكتمل لدولة '.PhoneCountries::name($country)
                        .($example ? " (مثال: {$example})" : '').'.',
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

    /**
     * Validates an international number when the user chooses "Other".
     *
     * @return array{phone_e164: string, phone_country: string}
     */
    private function normalizeInternational(string $input): array
    {
        if (! str_starts_with($input, '+')) {
            throw ValidationException::withMessages([
                'phone' => 'عند اختيار «Other» أدخل الرقم بالصيغة الدولية بدءًا من +.',
            ]);
        }

        try {
            $phone = new PhoneNumber($input);
            $detectedCountry = $phone->getCountry();

            if (blank($detectedCountry)) {
                throw ValidationException::withMessages([
                    'phone' => 'تعذر تحديد دولة الرقم. تأكد من إدخال الرقم بالصيغة الدولية.',
                ]);
            }

            if (! $phone->isValid()) {
                throw ValidationException::withMessages([
                    'phone' => 'رقم الهاتف غير صالح أو غير مكتمل. راجع عدد الأرقام والصيغة الدولية.',
                ]);
            }

            return [
                'phone_e164' => $phone->formatE164(),
                'phone_country' => $detectedCountry,
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
