<?php

namespace App\Enums;

enum RentalRequestStage: string
{
    case UnitSearch = 'unit_search';
    case Recommendation = 'recommendation';
    case Viewing = 'viewing';
    case Negotiation = 'negotiation';

    public function label(): string
    {
        return match ($this) {
            self::UnitSearch => 'بحث عن وحدات',
            self::Recommendation => 'ترشيح',
            self::Viewing => 'معاينة',
            self::Negotiation => 'تفاوض',
        };
    }
}
