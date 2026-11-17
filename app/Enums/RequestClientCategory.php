<?php

namespace App\Enums;

enum RequestClientCategory: string
{
    case Family = 'family';
    case Youth = 'youth';
    case Students = 'students';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Family => 'أسرة',
            self::Youth => 'شباب',
            self::Students => 'طلاب',
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
