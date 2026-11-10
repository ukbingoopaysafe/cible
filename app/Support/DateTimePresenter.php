<?php

namespace App\Support;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Support\HtmlString;
use IntlDateFormatter;
use InvalidArgumentException;
use LogicException;

final class DateTimePresenter
{
    private const TIMEZONE = 'Africa/Cairo';

    private readonly IntlDateFormatter $dateFormatter;

    private readonly IntlDateFormatter $dateOnlyFormatter;

    private readonly IntlDateFormatter $timeFormatter;

    private readonly string $datePattern;

    public function __construct(private readonly DateTimeLocale $locale)
    {
        $intlLocale = $locale->getIntlLocale();
        $regionalPatternFormatter = $this->makeFormatter($intlLocale, IntlDateFormatter::MEDIUM, IntlDateFormatter::NONE, self::TIMEZONE);
        $this->datePattern = $this->localizedMonthPattern($regionalPatternFormatter->getPattern());
        $this->dateFormatter = $this->makeFormatter($intlLocale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, self::TIMEZONE, $this->datePattern);
        $this->dateOnlyFormatter = $this->makeFormatter($intlLocale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, 'UTC', $this->datePattern);
        $this->timeFormatter = $this->makeFormatter($intlLocale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, self::TIMEZONE, 'hh:mm a');
    }

    public function formatDate(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value) && $this->isDateOnly($value)) {
            return $this->format($this->dateOnlyFormatter, $this->parseDateOnly($value));
        }

        return $this->format($this->dateFormatter, $this->toCairo($value));
    }

    public function formatTime(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value) && $this->isDateOnly($value)) {
            throw new InvalidArgumentException('Time values must use canonical Y-m-d H:i[:s] format.');
        }

        $formatted = $this->format($this->timeFormatter, $this->toCairo($value));

        return $this->locale->getLanguage() === 'en'
            ? preg_replace_callback('/\b(am|pm)\b/i', static fn (array $match): string => strtoupper($match[0]), $formatted) ?? $formatted
            : $formatted;
    }

    public function formatDateTime(DateTimeInterface|string|null $value): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value) && $this->isDateOnly($value)) {
            throw new InvalidArgumentException('Date/time values must use canonical Y-m-d H:i[:s] format.');
        }

        return $this->formatDate($value) . ' · ' . $this->formatTime($value);
    }

    public function formatRelativeDateTime(DateTimeInterface|string|null $value, DateTimeInterface $referenceAt): string
    {
        if ($value === null || $value === '') {
            return '';
        }

        if (is_string($value) && $this->isDateOnly($value)) {
            throw new InvalidArgumentException('Relative date/time values must use canonical Y-m-d H:i[:s] format.');
        }

        $dateTime = $this->toCairo($value);
        $reference = $this->toCairo($referenceAt);
        $dayDelta = (int) $reference->setTime(0, 0)->diff($dateTime->setTime(0, 0))->format('%r%a');

        $relativeDay = match ($dayDelta) {
            0 => $this->locale->getLanguage() === 'en' ? 'Today' : 'اليوم',
            -1 => $this->locale->getLanguage() === 'en' ? 'Yesterday' : 'أمس',
            1 => $this->locale->getLanguage() === 'en' ? 'Tomorrow' : 'غدًا',
            default => null,
        };

        if ($relativeDay === null) {
            return $this->formatDateTime($dateTime);
        }

        return $relativeDay . ' · ' . $this->formatTime($dateTime);
    }

    public function getFilamentDatePattern(): string
    {
        return preg_replace_callback(
            "/'[^']*'|([A-Za-z])\\1*/",
            static function (array $match): string {
                if (str_starts_with($match[0], "'")) {
                    return str_replace("'", '', $match[0]);
                }

                $token = $match[1];
                $length = strlen($match[0]);

                return match ($token) {
                    'd' => $length === 1 ? 'j' : 'd',
                    'M', 'L' => match (true) {
                        $length === 1 => 'n',
                        $length === 2 => 'm',
                        $length === 3 => 'M',
                        default => 'F',
                    },
                    'y', 'Y' => 'Y',
                    default => $match[0],
                };
            },
            $this->datePattern,
        ) ?? throw new LogicException('Unable to convert the ICU date pattern for Filament.');
    }

    public function getFilamentDateTimePattern(): string
    {
        return $this->getFilamentDatePattern() . ' h:i A';
    }

    public function bidi(string $value): HtmlString
    {
        return new HtmlString('<bdi dir="ltr" class="bidi-isolate">' . e($value) . '</bdi>');
    }

    public function bidiText(string $value): string
    {
        return "\u{2068}{$value}\u{2069}";
    }

    private function makeFormatter(
        string $locale,
        int $dateType,
        int $timeType,
        string $timezone,
        ?string $pattern = null,
    ): IntlDateFormatter {
        $formatter = new IntlDateFormatter(
            $locale,
            $dateType,
            $timeType,
            $timezone,
            IntlDateFormatter::GREGORIAN,
            $pattern,
        );

        if ($formatter->getErrorCode() !== U_ZERO_ERROR) {
            throw new LogicException('Unable to initialize ICU date/time formatting: ' . $formatter->getErrorMessage());
        }

        return $formatter;
    }

    private function format(IntlDateFormatter $formatter, DateTimeInterface $value): string
    {
        $formatted = $formatter->format($value);

        if ($formatted === false) {
            throw new LogicException('ICU failed to format a date/time value: ' . $formatter->getErrorMessage());
        }

        return $formatted;
    }

    private function toCairo(DateTimeInterface|string $value): DateTimeImmutable
    {
        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)->setTimezone(new DateTimeZone(self::TIMEZONE));
        }

        foreach (['!Y-m-d H:i:s', '!Y-m-d H:i'] as $format) {
            $parsed = DateTimeImmutable::createFromFormat($format, $value, new DateTimeZone(self::TIMEZONE));

            if ($parsed !== false && $parsed->format(substr($format, 1)) === $value && self::hasNoParseErrors()) {
                return $parsed;
            }
        }

        throw new InvalidArgumentException('Date/time strings must use canonical Y-m-d H:i[:s] format.');
    }

    private function parseDateOnly(string $value): DateTimeImmutable
    {
        $parsed = DateTimeImmutable::createFromFormat('!Y-m-d', $value, new DateTimeZone('UTC'));

        if ($parsed === false || $parsed->format('Y-m-d') !== $value || ! self::hasNoParseErrors()) {
            throw new InvalidArgumentException('Date strings must use canonical Y-m-d format.');
        }

        return $parsed;
    }

    private function isDateOnly(string $value): bool
    {
        return preg_match('/^\d{4}-\d{2}-\d{2}$/D', $value) === 1;
    }

    private function localizedMonthPattern(string|false $pattern): string
    {
        if (! is_string($pattern) || $pattern === '') {
            throw new LogicException('ICU did not provide a medium date pattern.');
        }

        $pattern = preg_replace('/[\x{061C}\x{200E}\x{200F}\x{2066}-\x{2069}]/u', '', $pattern) ?? $pattern;
        preg_match_all('/([A-Za-z])\\1*/', $pattern, $matches, PREG_OFFSET_CAPTURE);
        $parts = [];

        foreach ($matches[0] as [$token, $offset]) {
            $field = $token[0];
            $field = match ($field) {
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

        return match ($order) {
            'Mdy' => 'MMM d, y',
            'dMy' => 'd MMM y',
            'yMd' => 'y MMM d',
            'ydM' => 'y d MMM',
            'dyM' => 'd y MMM',
            'Myd' => 'MMM y d',
            default => throw new LogicException("Unsupported ICU medium date pattern [{$pattern}]."),
        };
    }

    private static function hasNoParseErrors(): bool
    {
        $errors = DateTimeImmutable::getLastErrors();

        return $errors === false || ($errors['warning_count'] === 0 && $errors['error_count'] === 0);
    }
}
