<?php

namespace Tests\Unit;

use App\Support\DateTimeLocale;
use App\Support\DateTimePresenter;
use DateTimeImmutable;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DateTimePresenterTest extends TestCase
{
    #[DataProvider('localizedOutputCases')]
    public function test_it_formats_gregorian_dates_and_zero_padded_12_hour_times(
        string $header,
        string $date,
        string $time,
    ): void {
        $presenter = new DateTimePresenter(DateTimeLocale::fromAcceptLanguage($header));

        $this->assertSame($date, $presenter->formatDate('2026-11-04'));
        $this->assertSame($time, $presenter->formatTime('2026-11-04 17:30'));
    }

    public static function localizedOutputCases(): array
    {
        return [
            'English US' => ['en-US', 'Nov 4, 2026', '05:30 PM'],
            'English GB' => ['en-GB', '4 Nov 2026', '05:30 PM'],
            'Arabic Egypt' => ['ar-EG', '4 نوفمبر 2026', '05:30 م'],
            'unsupported locale falls back to Arabic' => ['fr-FR', '4 نوفمبر 2026', '05:30 م'],
        ];
    }

    public function test_it_formats_date_time_and_relative_cairo_calendar_days(): void
    {
        $english = new DateTimePresenter(DateTimeLocale::fromAcceptLanguage('en-US'));
        $arabic = new DateTimePresenter(DateTimeLocale::fromAcceptLanguage('ar-EG'));
        $reference = new DateTimeImmutable('2026-11-04 12:00:00', new \DateTimeZone('Africa/Cairo'));

        $this->assertSame('Nov 4, 2026 · 05:30 PM', $english->formatDateTime('2026-11-04 17:30'));
        $this->assertSame('Today · 05:30 PM', $english->formatRelativeDateTime('2026-11-04 17:30', $reference));
        $this->assertSame('Yesterday · 05:30 PM', $english->formatRelativeDateTime('2026-11-03 17:30', $reference));
        $this->assertSame('Tomorrow · 05:30 PM', $english->formatRelativeDateTime('2026-11-05 17:30', $reference));
        $this->assertSame('اليوم · 05:30 م', $arabic->formatRelativeDateTime('2026-11-04 17:30', $reference));
        $this->assertSame('', $english->formatDate(null));
        $this->assertSame('', $english->formatTime(null));
        $this->assertSame('', $english->formatDateTime(null));
    }

    public function test_date_only_values_are_not_shifted_and_instants_use_cairo_timezone(): void
    {
        $presenter = new DateTimePresenter(DateTimeLocale::fromAcceptLanguage('en-US'));

        $this->assertSame('Nov 4, 2026', $presenter->formatDate('2026-11-04'));
        $this->assertSame('Nov 4, 2026 · 06:00 PM', $presenter->formatDateTime(new DateTimeImmutable('2026-11-04T16:00:00Z')));
    }

    public function test_it_rejects_noncanonical_or_invalid_strings(): void
    {
        $presenter = new DateTimePresenter(DateTimeLocale::fromAcceptLanguage('en-US'));

        foreach (['11/04/2026', '2026-02-30', '2026-11-04T17:30:00Z', '2026-11-04'] as $value) {
            try {
                $presenter->formatDateTime($value);
                $this->fail("Expected [{$value}] to be rejected.");
            } catch (InvalidArgumentException) {
                $this->assertTrue(true);
            }
        }
    }

    public function test_it_exposes_flatpickr_patterns_that_follow_regional_date_order(): void
    {
        $us = new DateTimePresenter(DateTimeLocale::fromAcceptLanguage('en-US'));
        $gb = new DateTimePresenter(DateTimeLocale::fromAcceptLanguage('en-GB'));
        $arabic = new DateTimePresenter(DateTimeLocale::fromAcceptLanguage('ar-EG'));

        $this->assertSame('M j, Y', $us->getFilamentDatePattern());
        $this->assertSame('j M Y', $gb->getFilamentDatePattern());
        $this->assertSame('j M Y', $arabic->getFilamentDatePattern());
        $this->assertStringEndsWith(' h:i A', $gb->getFilamentDateTimePattern());
    }

    public function test_bidi_output_isolated_in_ltr_and_escaped(): void
    {
        $presenter = new DateTimePresenter(DateTimeLocale::fromAcceptLanguage('en-US'));

        $this->assertSame('<bdi dir="ltr" class="bidi-isolate">&lt;script&gt;</bdi>', $presenter->bidi('<script>')->toHtml());
        $this->assertSame("\u{2068}05:30 PM\u{2069}", $presenter->bidiText('05:30 PM'));
    }
}
