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

    public function requestCode(int|string|null $id): ?string
    {
        return \App\Support\RecordCode::request($this, $id);
    }

    public function customerCode(int|string|null $id): ?string
    {
        return \App\Support\RecordCode::customer($this, $id);
    }
}
