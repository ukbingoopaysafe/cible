<?php

namespace App\Exports;

use App\Enums\RequestOutcome;
use App\Enums\RequestStatus;
use App\Enums\RequestUnitType;
use App\Enums\FinishingLevel;
use App\Enums\FloorPreference;
use App\Exports\Concerns\FormatsSpreadsheetData;
use App\Filament\Support\RequestFormSchema;
use App\Models\RentalRequest;
use Filament\Actions\Exports\ExportColumn;
use Filament\Actions\Exports\Exporter;
use Filament\Actions\Exports\Models\Export;
use Illuminate\Database\Eloquent\Model;

class RentalRequestExporter extends Exporter
{
    use FormatsSpreadsheetData;

    protected static ?string $model = RentalRequest::class;

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
            ExportColumn::make('stage')->label('المرحلة')->formatStateUsing(fn (mixed $state): string => RequestFormSchema::stageOptions(\App\Enums\Department::Rental)[static::enumValue($state) ?? ''] ?? ''),
            ExportColumn::make('outcome')->label('النتيجة')->formatStateUsing(fn (mixed $state): string => match (static::enumValue($state)) {
                RequestOutcome::Successful->value => 'ناجح',
                RequestOutcome::Unsuccessful->value => 'غير ناجح',
                default => '',
            }),
            ExportColumn::make('next_follow_up_at')->label('موعد المتابعة القادم'),
            ExportColumn::make('unit_type')->label('نوع الوحدة')->formatStateUsing(fn (mixed $state): string => match (static::enumValue($state)) {
                RequestUnitType::Apartment->value => 'شقة', RequestUnitType::Studio->value => 'استوديو', RequestUnitType::Villa->value => 'فيلا', RequestUnitType::Other->value => 'أخرى', default => '',
            }),
            static::textColumn('unit_type_other', 'تفصيل نوع الوحدة'),
            ExportColumn::make('bedrooms')->label('عدد الغرف'),
            ExportColumn::make('occupants')->label('عدد الأفراد'),
            ExportColumn::make('household')->label('أسرة')->formatStateUsing(fn (mixed $state): string => match ($state) { true, 1, '1' => 'نعم', false, 0, '0' => 'لا', default => '' }),
            ExportColumn::make('rental_term')->label('مدة الإيجار')->formatStateUsing(fn (mixed $state): string => match (static::enumValue($state)) { 'daily' => 'يومي', 'monthly' => 'شهري', 'half_yearly' => 'نصف سنوي', 'yearly' => 'سنوي', default => '' }),
            ExportColumn::make('rental_term_count')->label('عدد وحدات المدة'),
            ExportColumn::make('finishing')->label('التشطيب')->formatStateUsing(fn (mixed $state): string => match (static::enumValue($state)) {
                FinishingLevel::Unfinished->value => 'بدون تشطيب', FinishingLevel::HalfFinished->value => 'نصف تشطيب', FinishingLevel::SuperLux->value => 'سوبر لوكس', FinishingLevel::UltraSuperLux->value => 'ألترا سوبر لوكس', FinishingLevel::Hotel->value => 'فندقي', FinishingLevel::Smart->value => 'ذكي', FinishingLevel::Other->value => 'أخرى', default => '',
            }),
            static::textColumn('finishing_other', 'تفصيل التشطيب'),
            ExportColumn::make('floor_preference')->label('تفضيل الدور')->formatStateUsing(fn (mixed $state): string => match (static::enumValue($state)) {
                FloorPreference::Ground->value => 'أرضي', FloorPreference::Low->value => 'منخفض', FloorPreference::Middle->value => 'متوسط', FloorPreference::High->value => 'مرتفع', FloorPreference::Top->value => 'أخير', FloorPreference::NoPreference->value => 'لا تفضيل', default => '',
            }),
            ExportColumn::make('elevator_required')->label('المصعد مطلوب')->formatStateUsing(fn (mixed $state): string => match ($state) { true, 1, '1' => 'نعم', false, 0, '0' => 'لا', default => '' }),
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
