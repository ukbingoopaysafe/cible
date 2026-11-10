<?php

namespace App\Exports\Support;

use App\Support\DateTimeLocale;
use IntlDateFormatter;
use Locale;
use LogicException;

final class SpreadsheetDateTimeFormat
{
    public static function dateTime(string $regionalTag): string
    {
        return self::qualified($regionalTag, self::datePattern($regionalTag) . ' hh:mm AM/PM');
    }

    public static function time(string $regionalTag): string
    {
        return self::qualified($regionalTag, 'hh:mm AM/PM');
    }

    public static function date(string $regionalTag): string
    {
        return self::qualified($regionalTag, self::datePattern($regionalTag));
    }

    private static function qualified(string $regionalTag, string $format): string
    {
        $locale = DateTimeLocale::fromAcceptLanguage($regionalTag);
        $language = $locale->getLanguage();
        $region = Locale::getRegion(str_replace('-', '_', $locale->getRegionalTag()));
        $localeId = match (true) {
            $language === 'en' && $region === 'GB' => '0809',
            $language === 'en' => '0409',
            default => '0C01',
        };

        return '[$-' . $localeId . ']' . $format;
    }

    private static function datePattern(string $regionalTag): string
    {
        $locale = DateTimeLocale::fromAcceptLanguage($regionalTag);
        $formatter = new IntlDateFormatter(
            $locale->getIntlLocale(),
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE,
            'Africa/Cairo',
            IntlDateFormatter::GREGORIAN,
        );
        $pattern = $formatter->getPattern();

        if (! is_string($pattern) || $pattern === '') {
            throw new LogicException('ICU did not provide a regional date pattern for the spreadsheet.');
        }

        $pattern = preg_replace('/[\x{061C}\x{200E}\x{200F}\x{2066}-\x{2069}]/u', '', $pattern) ?? $pattern;
        preg_match_all('/([A-Za-z])\\1*/', $pattern, $matches, PREG_OFFSET_CAPTURE);
        $parts = [];

        foreach ($matches[0] as [$token, $offset]) {
            $field = match ($token[0]) {
                'd' => 'd',
                'M', 'L' => 'M',
                'y', 'Y' => 'y',
                default => null,
            };

            if ($field !== null && ! in_array($field, array_column($parts, 'field'), true)) {
                $parts[] = ['field' => $field, 'offset' => $offset];
            }
        }

        usort($parts, static fn (array $left, array $right): int => $left['offset'] <=> $right['offset']);
        $order = implode('', array_column($parts, 'field'));
        $monthToken = $locale->getLanguage() === 'ar' ? 'mmmm' : 'mmm';

        return match ($order) {
            'Mdy' => $monthToken . ' d, yyyy',
            'dMy' => 'd ' . $monthToken . ' yyyy',
            'yMd' => 'yyyy ' . $monthToken . ' d',
            'ydM' => 'yyyy d ' . $monthToken,
            'dyM' => 'd yyyy ' . $monthToken,
            'Myd' => $monthToken . ' yyyy d',
            default => throw new LogicException("Unsupported ICU spreadsheet date pattern [{$pattern}]."),
        };
    }
}
