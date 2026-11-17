<?php

namespace App\Exports;

use App\Enums\RequestOutcome;
use App\Enums\RequestStatus;
use App\Enums\Department;
use App\Enums\ElectricityMeterType;
use App\Enums\RequestClientCategory;
use App\Enums\RequestUnitType;
use App\Exports\Concerns\FormatsSpreadsheetData;
use App\Filament\Support\RequestFormSchema;
use App\Models\ResaleRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ResaleRequestExporter extends Exporter
{
    use FormatsSpreadsheetData;

    protected static ?string $model = ResaleRequest::class;

    public static function getColumns(): array
    {
        return [
            static::textColumn('customer.name', 'العميل'),
            ExportColumn::make('customer_primary_phone')
                ->label('هاتف العميل')
                ->state(fn (Model $record): ?string => $record->customer?->primaryPhone()?->phone_e164)
                ->formatStateUsing(fn (mixed $state): mixed => static::safeText($state)),
            ExportColumn::make('customer_additional_phones')
                ->label('أرقام هاتف العميل الإضافية')
                ->state(fn (Model $record): string => static::additionalPhones($record->customer?->phoneNumbers ?? []))
                ->formatStateUsing(fn (mixed $state): mixed => static::safeText($state)),
            ExportColumn::make('broker')->label('الوسيط')->state(fn (Model $record): ?string => $record->broker?->name ?? $record->broker_name_snapshot)->formatStateUsing(fn (mixed $state): mixed => static::safeText($state)),
            ExportColumn::make('broker_phone')->label('هاتف الوسيط')->state(fn (Model $record): ?string => static::primaryBrokerPhone($record))->formatStateUsing(fn (mixed $state): mixed => static::safeText($state)),
            ExportColumn::make('broker_additional_phones')->label('أرقام هاتف الوسيط الإضافية')->state(fn (Model $record): string => static::additionalPhones(static::brokerPhoneRecords($record)))->formatStateUsing(fn (mixed $state): mixed => static::safeText($state)),
            static::textColumn('owner.name', 'المسؤول'),
            static::textColumn('leadSource.name', 'مصدر الوصول'),
            static::textColumn('lead_source_other', 'تفصيل مصدر الوصول'),
            ExportColumn::make('lead_source_referrer_export')
                ->label('الشخص المُحيل')
                ->state(fn (Model $record): string => filled($record->lead_source_referrer_name)
                    ? (string) $record->lead_source_referrer_name
                    : ($record->lead_source_referrer_unknown ? 'غير معروف' : ''))
                ->formatStateUsing(fn (mixed $state): mixed => static::safeText($state)),
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
            static::timestampColumn('next_follow_up_at', 'موعد المتابعة القادم'),
            ExportColumn::make('unit_type')->label('نوع الوحدة')->formatStateUsing(fn (mixed $state): string => match (static::enumValue($state)) {
                RequestUnitType::Apartment->value => 'شقة', RequestUnitType::Studio->value => 'استوديو', RequestUnitType::Villa->value => 'فيلا', RequestUnitType::Other->value => 'أخرى', default => '',
            }),
            ExportColumn::make('client_category')->label('نوع العميل')->formatStateUsing(fn (mixed $state): string => RequestClientCategory::tryFrom(static::enumValue($state) ?? '')?->label() ?? ''),
            ExportColumn::make('occupants')->label('عدد الأفراد'),
            ExportColumn::make('bedrooms')->label('عدد الغرف'),
            ExportColumn::make('bathrooms')->label('عدد الحمامات'),
            ExportColumn::make('building_year')->label('سنة البناء'),
            ExportColumn::make('elevator_required')->label('المصعد')->formatStateUsing(fn (mixed $state): string => match ($state) { true, 1, '1' => 'مطلوب', false, 0, '0' => 'غير مطلوب', default => '' }),
            ExportColumn::make('water_meter')->label('عداد المياه')->formatStateUsing(fn (mixed $state): string => match ($state) { true, 1, '1' => 'يوجد', false, 0, '0' => 'لا يوجد', default => '' }),
            ExportColumn::make('electricity_meter_type')->label('عداد الكهرباء')->formatStateUsing(fn (mixed $state): string => ElectricityMeterType::tryFrom(static::enumValue($state) ?? '')?->label() ?? ''),
            ExportColumn::make('gas_available')->label('عداد الغاز')->formatStateUsing(fn (mixed $state): string => match ($state) { true, 1, '1' => 'يوجد', false, 0, '0' => 'لا يوجد', default => '' }),
            ExportColumn::make('area_min')->label('المساحة من (م²)'),
            ExportColumn::make('area_max')->label('المساحة إلى (م²)'),
            ExportColumn::make('budget_declared')->label('ميزانية العميل'),
            ExportColumn::make('budget_targeted')->label('ميزانية السوق'),
            ExportColumn::make('budget_currency')->label('العملة'),
            static::textColumn('other_requirements', 'متطلبات أخرى'),
            static::textColumn('notes', 'ملاحظات'),
            static::textColumn('outcome_reason_other', 'تصنيف سبب الإغلاق'),
            static::textColumn('outcome_notes', 'سبب الإغلاق المكتوب'),
            static::timestampColumn('closed_at', 'تاريخ الإغلاق'),
            static::timestampColumn('created_at', 'تاريخ إنشاء الطلب'),
        ];
    }

    protected static function dateTimeExportColumns(): array
    {
        return ['next_follow_up_at', 'closed_at', 'created_at'];
    }

    public static function modifyQuery(Builder $query): Builder
    {
        return $query->with(['customer.phoneNumbers', 'broker.phoneNumbers']);
    }

    public static function getCompletedNotificationBody(Export $export): string
    {
        return static::completedBody($export);
    }
}
