<?php

namespace App\Exports\Concerns;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Support\Number;

trait FormatsSpreadsheetData
{
    protected static function textColumn(string $name, string $label): ExportColumn
    {
        return ExportColumn::make($name)
            ->label($label)
            ->formatStateUsing(static fn (mixed $state): mixed => static::safeText($state));
    }

    protected static function safeText(mixed $value): mixed
    {
        if (! is_string($value) || $value === '') {
            return $value;
        }

        return preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1 ? "'{$value}" : $value;
    }

    protected static function enumValue(mixed $value): ?string
    {
        if ($value instanceof \BackedEnum) {
            return (string) $value->value;
        }

        return is_string($value) ? $value : null;
    }

    protected static function completedBody(Export $export): string
    {
        return 'تم تجهيز ملف Excel ويحتوي على '.Number::format($export->successful_rows).' سجل.';
    }
}
