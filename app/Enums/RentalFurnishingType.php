<?php

namespace App\Enums;

enum RentalFurnishingType: string
{
    case Tiles = 'tiles';
    case Ordinary = 'ordinary';
    case Lux = 'lux';
    case SuperLux = 'super_lux';
    case Hotel = 'hotel';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Tiles => 'بلاط',
            self::Ordinary => 'عادية',
            self::Lux => 'لوكس',
            self::SuperLux => 'سوبر لوكس',
            self::Hotel => 'فندقية',
            self::Other => 'أخرى',
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
