<?php

namespace App\Enums;

enum ContactActivityChannel: string
{
    case Phone = 'phone';
    case WhatsApp = 'whatsapp';
    case InPerson = 'in_person';
    case Email = 'email';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Phone => 'مكالمة هاتفية',
            self::WhatsApp => 'WhatsApp',
            self::InPerson => 'مقابلة شخصية',
            self::Email => 'بريد إلكتروني',
            self::Other => 'أخرى',
        };
    }
}
