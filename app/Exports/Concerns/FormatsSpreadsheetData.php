<?php

namespace App\Exports\Concerns;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
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

    /** @param iterable<mixed> $phones */
    protected static function additionalPhones(iterable $phones): string
    {
        return collect($phones)
            ->reject(fn (mixed $phone): bool => (bool) data_get($phone, 'is_primary'))
            ->map(fn (mixed $phone): string => (string) data_get($phone, 'phone_e164', ''))
            ->filter()
            ->implode('; ');
    }

    protected static function primaryBrokerPhone(Model $request): ?string
    {
        $broker = $request->broker;
        if ($broker) {
            return $broker->primaryPhone()?->phone_e164;
        }

        $snapshot = collect($request->broker_phone_numbers_snapshot ?? [])
            ->first(fn (mixed $phone): bool => (bool) data_get($phone, 'is_primary'));

        return data_get($snapshot, 'phone_e164') ?: $request->broker_phone_snapshot;
    }

    /** @return iterable<mixed> */
    protected static function brokerPhoneRecords(Model $request): iterable
    {
        $broker = $request->broker;
        if ($broker) {
            return $broker->phoneNumbers;
        }

        $snapshot = $request->broker_phone_numbers_snapshot ?? [];
        if ($snapshot !== []) {
            return $snapshot;
        }

        return filled($request->broker_phone_snapshot)
            ? [[
                'phone_e164' => $request->broker_phone_snapshot,
                'is_primary' => true,
            ]]
            : [];
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
