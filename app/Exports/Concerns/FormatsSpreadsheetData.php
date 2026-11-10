<?php

namespace App\Exports\Concerns;

use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Number;
use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;
use InvalidArgumentException;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use App\Exports\Support\SpreadsheetDateTimeFormat;

trait FormatsSpreadsheetData
{
    protected static function timestampColumn(string $name, string $label): ExportColumn
    {
        return ExportColumn::make($name)
            ->label($label)
            ->formatStateUsing(static fn (mixed $state): ?string => static::canonicalTimestamp($state));
    }

    /** @return list<string> */
    protected static function dateTimeExportColumns(): array
    {
        return [];
    }

    public function makeXlsxHeaderRow(array $values, ?Style $style = null): Row
    {
        return Row::fromValues($values, $style);
    }

    public function makeXlsxRow(array $values, ?Style $style = null): Row
    {
        $locale = is_string($this->options['cible_datetime_locale'] ?? null)
            ? $this->options['cible_datetime_locale']
            : 'ar-EG';
        $columnStyles = [];

        foreach (array_keys($this->columnMap) as $index => $columnName) {
            if (! in_array($columnName, static::dateTimeExportColumns(), true)) {
                continue;
            }

            $value = $values[$index] ?? null;

            if ($value === null || $value === '') {
                continue;
            }

            $values[$index] = self::hydrateTimestamp((string) $value);
            $columnStyles[$index] = (new Style())->setFormat(SpreadsheetDateTimeFormat::dateTime($locale));
        }

        return Row::fromValuesWithStyles($values, $style, $columnStyles);
    }

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

    private static function canonicalTimestamp(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        $timezone = new DateTimeZone('Africa/Cairo');

        if ($value instanceof DateTimeInterface) {
            return DateTimeImmutable::createFromInterface($value)
                ->setTimezone($timezone)
                ->format('Y-m-d H:i:s');
        }

        if (! is_string($value)) {
            throw new InvalidArgumentException('Exported timestamp values must be date/time objects or canonical strings.');
        }

        foreach (['!Y-m-d H:i:s', '!Y-m-d H:i'] as $format) {
            $timestamp = DateTimeImmutable::createFromFormat($format, $value, $timezone);

            if ($timestamp !== false && $timestamp->format(substr($format, 1)) === $value) {
                return $timestamp->format('Y-m-d H:i:s');
            }
        }

        throw new InvalidArgumentException('Exported timestamps must use canonical Y-m-d H:i[:s] format.');
    }

    private static function hydrateTimestamp(string $value): DateTimeImmutable
    {
        $timezone = new DateTimeZone('Africa/Cairo');
        $timestamp = DateTimeImmutable::createFromFormat('!Y-m-d H:i:s', $value, $timezone);
        $errors = DateTimeImmutable::getLastErrors();

        if (
            $timestamp === false
            || $timestamp->format('Y-m-d H:i:s') !== $value
            || ($errors !== false && ($errors['warning_count'] > 0 || $errors['error_count'] > 0))
        ) {
            throw new InvalidArgumentException('Temporary XLSX timestamps must use canonical Y-m-d H:i:s format.');
        }

        return $timestamp;
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
