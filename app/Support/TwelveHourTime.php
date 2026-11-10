<?php

namespace App\Support;

use InvalidArgumentException;

final class TwelveHourTime
{
    /**
     * @return array{hour: int, period: 'AM'|'PM'}
     */
    public static function from24Hour(int $hour): array
    {
        if (($hour < 0) || ($hour > 23)) {
            throw new InvalidArgumentException('The 24-hour value must be between 0 and 23.');
        }

        return [
            'hour' => ($hour % 12) ?: 12,
            'period' => $hour < 12 ? 'AM' : 'PM',
        ];
    }

    public static function to24Hour(int $hour, string $period): int
    {
        if (($hour < 1) || ($hour > 12)) {
            throw new InvalidArgumentException('The 12-hour value must be between 1 and 12.');
        }

        $period = strtoupper($period);

        if (! in_array($period, ['AM', 'PM'], true)) {
            throw new InvalidArgumentException('The period must be AM or PM.');
        }

        return ($hour % 12) + ($period === 'PM' ? 12 : 0);
    }
}
