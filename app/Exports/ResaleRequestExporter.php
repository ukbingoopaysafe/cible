<?php

namespace App\Exports;

use App\Enums\RequestOutcome;
use App\Enums\RequestStatus;
use App\Enums\Department;
use App\Enums\RequestUnitType;
use App\Exports\Concerns\FormatsSpreadsheetData;
use App\Filament\Support\RequestFormSchema;
use App\Models\ResaleRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;

class ResaleRequestExporter extends Exporter
{
    use FormatsSpreadsheetData;

    protected static ?string $model = ResaleRequest::class;

    public static function getColumns(): array
    {
        return [
            static::textColumn('customer.name', 'العميل'),
            static::textColumn('customer.phone_e164', 'هاتف العميل'),
            ExportColumn::make('broker')->label('الوسيط')->state(fn (Model $record): ?string => $record->broker?->name ?? $record->broker_name_snapshot)->formatStateUsing(fn (mixed $state): mixed => static::safeText($state)),
            ExportColumn::make('broker_phone')->label('هاتف الوسيط')->state(fn (Model $record): ?string => $record->broker?->phone_e164 ?? $record->broker_phone_snapshot)->formatStateUsing(fn (mixed $state): mixed => static::safeText($state)),
            static::textColumn('owner.name', 'المسؤول'),
            static::textColumn('leadSource.name', 'مصدر الوصول'),
            static::textColumn('lead_source_other', 'تفصيل مصدر الوصول'),
            static::textColumn('area.governorate', 'المحافظة'),
            static::textColumn('area.name', 'المنطقة'),
            ExportColumn::make('contact_party')->label('جهة الاتصال')->formatStateUsing(fn (mixed $state): string => match (static::enumValue($state)) {
                'customer' => 'العميل',
                'broker' => 'الوسيط',
                default => '',
            }),
            ExportColumn::make('status')->label('الحالة')->formatStateUsing(fn (mixed $state): string => match (static::enumValue($state)) {
                RequestStatus::Active->value => 'نشط',
                RequestStatus::Paused->value => 'متوقف مؤقتًا',
                RequestStatus::Closed->value => 'مغلق',
                default => '',
            }),
            ExportColumn::make('stage')->label('المرحلة')->formatStateUsing(fn (mixed $state): string => RequestFormSchema::stageOptions(Department::Resale)[static::enumValue($state) ?? ''] ?? ''),
            ExportColumn::make('outcome')->label('النتيجة')->formatStateUsing(fn (mixed $state): string => match (static::enumValue($state)) {
                RequestOutcome::Successful->value => 'ناجح',
                RequestOutcome::Unsuccessful->value => 'غير ناجح',
                default => '',
            }),
            ExportColumn::make('next_follow_up_at')->label('موعد المتابعة القادم'),
            ExportColumn::make('unit_type')->label('نوع الوحدة')->formatStateUsing(fn (mixed $state): string => match (static::enumValue($state)) {
                RequestUnitType::Apartment->value => 'شقة', RequestUnitType::Studio->value => 'استوديو', RequestUnitType::Villa->value => 'فيلا', RequestUnitType::Other->value => 'أخرى', default => '',
            }),
            ExportColumn::make('area_min')->label('المساحة من (م²)'),
            ExportColumn::make('area_max')->label('المساحة إلى (م²)'),
            ExportColumn::make('budget_declared')->label('الميزانية المعلنة'),
            ExportColumn::make('budget_targeted')->label('الميزانية المستهدفة'),
            ExportColumn::make('budget_currency')->label('العملة'),
            static::textColumn('other_requirements', 'متطلبات أخرى'),
            static::textColumn('notes', 'ملاحظات'),
            static::textColumn('outcome_reason_other', 'تصنيف سبب الإغلاق'),
            static::textColumn('outcome_notes', 'سبب الإغلاق المكتوب'),
            ExportColumn::make('closed_at')->label('تاريخ الإغلاق'),
            ExportColumn::make('created_at')->label('تاريخ إنشاء الطلب'),
        ];
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return static::completedBody($export);
    }
}
