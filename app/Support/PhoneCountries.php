<?php

namespace App\Support;

use libphonenumber\PhoneNumberFormat;
use libphonenumber\PhoneNumberType;
use libphonenumber\PhoneNumberUtil;
use Propaganistas\LaravelPhone\PhoneNumber;
use Throwable;

final class PhoneCountries
{
    public const DEFAULT = 'EG';

    public const OTHER = 'ZZ';

    /**
     * The country list used by every phone field in the admin panel.
     *
     * @var array<string, array{name: string, dial: string}>
     */
    private const COUNTRIES = [
        'EG' => ['name' => 'Egypt', 'dial' => '+20'],
        'SA' => ['name' => 'Saudi Arabia', 'dial' => '+966'],
        'AE' => ['name' => 'UAE', 'dial' => '+971'],
        'KW' => ['name' => 'Kuwait', 'dial' => '+965'],
        'QA' => ['name' => 'Qatar', 'dial' => '+974'],
        'BH' => ['name' => 'Bahrain', 'dial' => '+973'],
        'OM' => ['name' => 'Oman', 'dial' => '+968'],
        'IQ' => ['name' => 'Iraq', 'dial' => '+964'],
        'SD' => ['name' => 'Sudan', 'dial' => '+249'],
        'YE' => ['name' => 'Yemen', 'dial' => '+967'],
        'SY' => ['name' => 'Syria', 'dial' => '+963'],
        'LB' => ['name' => 'Lebanon', 'dial' => '+961'],
        'TN' => ['name' => 'Tunisia', 'dial' => '+216'],
        'MA' => ['name' => 'Morocco', 'dial' => '+212'],
        'DZ' => ['name' => 'Algeria', 'dial' => '+213'],
        'LY' => ['name' => 'Libya', 'dial' => '+218'],
        'FR' => ['name' => 'France', 'dial' => '+33'],
        'SE' => ['name' => 'Sweden', 'dial' => '+46'],
        'BE' => ['name' => 'Belgium', 'dial' => '+32'],
        'IT' => ['name' => 'Italy', 'dial' => '+39'],
        'ES' => ['name' => 'Spain', 'dial' => '+34'],
        'AT' => ['name' => 'Austria', 'dial' => '+43'],
        'PL' => ['name' => 'Poland', 'dial' => '+48'],
        'AU' => ['name' => 'Australia', 'dial' => '+61'],
        'US' => ['name' => 'USA/Canada', 'dial' => '+1'],
        'GB' => ['name' => 'UK', 'dial' => '+44'],
    ];

    /**
     * @return array<string, string>
     */
    public static function options(): array
    {
        $countries = self::COUNTRIES;

        uasort($countries, fn (array $left, array $right): int => strnatcasecmp($left['name'], $right['name']));

        $options = [];

        foreach ($countries as $code => $country) {
            $options[$code] = self::flagImage($code)." {$country['name']} ({$country['dial']})";
        }

        $options[self::OTHER] = '🌐 Other';

        return $options;
    }

    private static function flagImage(string $country): string
    {
        return '<img class="fi-phone-country-flag" src="https://flagcdn.com/24x18/'.strtolower($country).'.png" alt="" aria-hidden="true">';
    }

    public static function isSupported(?string $country): bool
    {
        $country = mb_strtoupper(trim((string) $country));

        return array_key_exists($country, self::COUNTRIES);
    }

    public static function isOther(?string $country): bool
    {
        return mb_strtoupper(trim((string) $country)) === self::OTHER;
    }

    public static function name(?string $country): string
    {
        $country = mb_strtoupper(trim((string) $country));

        return self::COUNTRIES[$country]['name'] ?? $country;
    }

    public static function display(?string $country): ?string
    {
        $country = mb_strtoupper(trim((string) $country));

        if ($country === '') {
            return null;
        }

        return self::isOther($country) ? 'Other' : $country;
    }

    /**
     * Maps a stored country to a value that exists in the select options.
     */
    public static function selectable(?string $country): string
    {
        $country = mb_strtoupper(trim((string) $country));

        if ($country === '') {
            return self::DEFAULT;
        }

        return self::isSupported($country) ? $country : self::OTHER;
    }

    /**
     * Formats an E.164 number as the compact national number shown in the input.
     */
    public static function nationalNumber(?string $e164, ?string $country): ?string
    {
        if (blank($e164)) {
            return null;
        }

        $selectable = self::selectable($country);

        if (self::isOther($selectable)) {
            return $e164;
        }

        try {
            $national = (new PhoneNumber($e164, $selectable))->formatNational();

            return preg_replace('/\D+/', '', $national) ?: $e164;
        } catch (Throwable) {
            return $e164;
        }
    }

    /**
     * Returns a compact example number for the selected country.
     */
    public static function example(?string $country): ?string
    {
        $country = mb_strtoupper(trim((string) $country));

        if ($country === '' || self::isOther($country) || ! self::isSupported($country)) {
            return null;
        }

        $utility = PhoneNumberUtil::getInstance();
        $number = $utility->getExampleNumberForType($country, PhoneNumberType::MOBILE)
            ?? $utility->getExampleNumberForType($country, PhoneNumberType::FIXED_LINE);

        if (! $number) {
            return null;
        }

        return preg_replace('/\D+/', '', $utility->format($number, PhoneNumberFormat::NATIONAL)) ?: null;
    }
}
