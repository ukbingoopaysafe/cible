<?php

namespace App\Enums;

enum ViewingAppointmentStatus: string
{
    case PendingSchedule = 'pending_schedule';
    case Scheduled = 'scheduled';
    case Completed = 'completed';
    case Postponed = 'postponed';
    case Cancelled = 'cancelled';

    public function label(): string
    {
        return match ($this) {
            self::PendingSchedule => 'بانتظار تحديد الموعد',
            self::Scheduled => 'موعد محدد',
            self::Completed => 'تمت المعاينة',
            self::Postponed => 'مؤجلة',
            self::Cancelled => 'ملغاة',
        };
    }
}
