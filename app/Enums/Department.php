<?php

namespace App\Enums;

enum Department: string
{
    case Rental = 'rental';
    case Resale = 'resale';

    public function label(): string
    {
        return match ($this) {
            self::Rental => 'الإيجار',
            self::Resale => 'إعادة البيع',
        };
    }
}
