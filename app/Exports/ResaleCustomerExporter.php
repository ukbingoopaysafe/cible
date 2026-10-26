<?php

namespace App\Exports;

use App\Exports\Concerns\FormatsSpreadsheetData;
use App\Models\ResaleCustomer;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;

class ResaleCustomerExporter extends Exporter
{
    use FormatsSpreadsheetData;

    protected static ?string $model = ResaleCustomer::class;

    public static function getColumns(): array
    {
        return [
            static::textColumn('name', 'اسم العميل'),
            static::textColumn('phone_e164', 'رقم الهاتف'),
            static::textColumn('nationality', 'الجنسية'),
            ExportColumn::make('created_at')->label('تاريخ التسجيل'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return static::completedBody($export);
    }
}
