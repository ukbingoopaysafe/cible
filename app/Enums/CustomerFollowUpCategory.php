<?php

namespace App\Enums;

enum CustomerFollowUpCategory: string
{
    case Successful = 'successful';
    case Unsuccessful = 'unsuccessful';
    case LegacyWithoutRequest = 'legacy_without_request';

    public function label(): string
    {
        return match ($this) {
            self::Successful => 'طلبات ناجحة',
            self::Unsuccessful => 'طلبات غير ناجحة',
            self::LegacyWithoutRequest => 'عميل قديم بدون طلب مسجل',
        };
    }
}
