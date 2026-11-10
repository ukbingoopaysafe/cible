<?php

namespace App\Exports;

use App\Exports\Concerns\FormatsSpreadsheetData;
use App\Models\ResaleCustomer;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ResaleCustomerExporter extends Exporter
{
    use FormatsSpreadsheetData;

    protected static ?string $model = ResaleCustomer::class;

    public static function getColumns(): array
    {
        return [
            static::textColumn('name', 'اسم العميل'),
            ExportColumn::make('primary_phone')
                ->label('رقم الهاتف')
                ->state(fn (Model $record): ?string => $record->primaryPhone()?->phone_e164)
                ->formatStateUsing(fn (mixed $state): mixed => static::safeText($state)),
            ExportColumn::make('additional_phone_numbers')
                ->label('أرقام الهاتف الإضافية')
                ->state(fn (Model $record): string => static::additionalPhones($record->phoneNumbers))
                ->formatStateUsing(fn (mixed $state): mixed => static::safeText($state)),
            static::textColumn('nationality', 'الجنسية'),
            static::timestampColumn('created_at', 'تاريخ التسجيل'),
        ];
    }

    protected static function dateTimeExportColumns(): array
    {
        return ['created_at'];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with('phoneNumbers');
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return static::completedBody($export);
    }
}
