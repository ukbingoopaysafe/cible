<?php

namespace Tests\Unit;

use App\Support\TwelveHourTime;
use PHPUnit\Framework\TestCase;

class TwelveHourTimeTest extends TestCase
{
    public function test_every_24_hour_value_round_trips_through_a_12_hour_value_and_period(): void
    {
        foreach (range(0, 23) as $hour24) {
            $display = TwelveHourTime::from24Hour($hour24);

            $this->assertSame($hour24 % 12 ?: 12, $display['hour']);
            $this->assertSame($hour24 < 12 ? 'AM' : 'PM', $display['period']);
            $this->assertSame($hour24, TwelveHourTime::to24Hour($display['hour'], $display['period']));
        }
    }

    public function test_invalid_display_hours_and_periods_are_rejected(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TwelveHourTime::to24Hour(0, 'AM');
    }

    public function test_period_must_be_am_or_pm(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        TwelveHourTime::to24Hour(1, 'morning');
    }
}
