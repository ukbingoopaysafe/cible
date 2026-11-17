<?php

namespace App\Enums;

enum ElectricityMeterType: string
{
    case Old = 'old';
    case Card = 'card';

    public function label(): string
    {
        return match ($this) {
            self::Old => 'قديم',
            self::Card => 'كارت',
        };
    }

    /** @return array<string, string> */
    public static function options(): array
    {
        $options = [];
        foreach (self::cases() as $case) {
            $options[$case->value] = $case->label();
        }

        return $options;
    }
}
