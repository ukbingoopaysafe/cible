<?php

namespace Tests\Unit;

use App\Exports\Support\SpreadsheetDateTimeFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SpreadsheetDateTimeFormatTest extends TestCase
{
    #[DataProvider('formats')]
    public function test_it_builds_locale_qualified_formats_with_regional_date_order(
        string $locale,
        string $expectedDateTime,
        string $expectedTime,
        string $expectedDate,
    ): void {
        $this->assertSame($expectedDateTime, SpreadsheetDateTimeFormat::dateTime($locale));
        $this->assertSame($expectedTime, SpreadsheetDateTimeFormat::time($locale));
        $this->assertSame($expectedDate, SpreadsheetDateTimeFormat::date($locale));
    }

    public static function formats(): array
    {
        return [
            'English US' => [
                'en-US',
                '[$-0409]mmm d, yyyy hh:mm AM/PM',
                '[$-0409]hh:mm AM/PM',
                '[$-0409]mmm d, yyyy',
            ],
            'English GB' => [
                'en-GB',
                '[$-0809]d mmm yyyy hh:mm AM/PM',
                '[$-0809]hh:mm AM/PM',
                '[$-0809]d mmm yyyy',
            ],
            'Arabic Egypt' => [
                'ar-EG',
                '[$-0C01]d mmmm yyyy hh:mm AM/PM',
                '[$-0C01]hh:mm AM/PM',
                '[$-0C01]d mmmm yyyy',
            ],
            'English Australia uses language fallback LCID with regional ordering' => [
                'en-AU',
                '[$-0409]d mmm yyyy hh:mm AM/PM',
                '[$-0409]hh:mm AM/PM',
                '[$-0409]d mmm yyyy',
            ],
            'Arabic Morocco uses Arabic fallback LCID with regional ordering' => [
                'ar-MA',
                '[$-0C01]d mmmm yyyy hh:mm AM/PM',
                '[$-0C01]hh:mm AM/PM',
                '[$-0C01]d mmmm yyyy',
            ],
        ];
    }
}
