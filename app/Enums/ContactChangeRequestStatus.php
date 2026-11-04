<?php

namespace App\Enums;

enum ContactChangeRequestStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'بانتظار المدير',
            self::Approved => 'تمت الموافقة',
            self::Rejected => 'مرفوض',
            self::Cancelled => 'ملغي',
        };
    }
}
