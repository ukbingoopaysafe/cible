<?php

namespace App\Filament\Support;

use App\Enums\ContactParty;
use App\Enums\Department;
use App\Enums\ElectricityMeterType;
use App\Enums\FinishingLevel;
use App\Enums\FloorPreference;
use App\Enums\LeadSourceType;
use App\Enums\RentalFurnishingType;
use App\Enums\RentalTerm;
use App\Enums\RequestClientCategory;
use App\Enums\RequestStatus;
use App\Enums\RequestUnitType;
use App\Models\Area;
use App\Models\RentalArea;
use App\Models\RentalBroker;
use App\Models\RentalCustomer;
use App\Models\RentalLeadSource;
use App\Models\ResaleArea;
use App\Models\ResaleBroker;
use App\Models\ResaleCustomer;
use App\Models\ResaleLeadSource;
use App\Models\User;
use App\Services\AreaService;
use App\Services\BrokerService;
use App\Services\CustomerService;
use App\Support\RecordCode;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
use Illuminate\Database\Eloquent\Model;

final class RequestFormSchema
{
    public static function configure(Schema $schema, Department $department, bool $editing = false): Schema
    {
        [$customerClass, $brokerClass, $areaClass, $sourceClass] = match ($department) {
            Department::Rental => [RentalCustomer::class, RentalBroker::class, RentalArea::class, RentalLeadSource::class],
            Department::Resale => [ResaleCustomer::class, ResaleBroker::class, ResaleArea::class, ResaleLeadSource::class],
        };

        $areaOptions = fn (Get $get): array => Area::query()
            ->when(
                $editing && filled($get('area_id')),
                fn ($query) => $query->withTrashed()->where(function ($query) use ($get): void {
                    $query->whereNull('deleted_at')->orWhere($query->getModel()->getKeyName(), $get('area_id'));
                }),
                fn ($query) => $query->whereNull('deleted_at')
            )
            ->orderBy('governorate')
            ->orderBy('name')
            ->get(['id', 'governorate', 'name'])
            ->mapWithKeys(fn ($area): array => [$area->id => "{$area->governorate} — {$area->name}"])
            ->all();

        $contactOptions = fn (Get $get): array => array_filter([
            ContactParty::Customer->value => filled($get('customer_id')) ? 'العميل' : null,
            ContactParty::Broker->value => filled($get('broker_id')) ? 'الوسيط' : null,
        ]);

        $components = [
            Section::make('أطراف الطلب')
                ->icon('heroicon-o-user-group')
                ->schema([
                    Select::make('customer_id')
                        ->label('العميل')
                        ->options(fn (Get $get): array => $customerClass::query()
                            ->when(
                                $editing && filled($get('customer_id')),
                                fn ($query) => $query->withTrashed()->where(function ($query) use ($get): void {
                                    $query->whereNull('deleted_at')->orWhere($query->getModel()->getKeyName(), $get('customer_id'));
                                }),
                            )
                            ->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->placeholder('اختر عميلًا إن كان معروفًا')
                        ->createOptionForm([
                            TextInput::make('name')
                                ->label('الاسم')
                                ->required()
                                ->maxLength(255)
                                ->live(debounce: 500)
                                ->afterStateUpdated(function (Set $set): void {
                                    $set('reviewed_similar_names', false);
                                }),
                            ContactPhonesField::make(required: true),
                            TextInput::make('nationality')->label('الجنسية')->maxLength(100),
                            Checkbox::make('reviewed_similar_names')
                                ->label('تأكيد: هذا عميل مختلف رغم تشابه الاسم.')
                                ->visible(fn (Get $get): bool => app(CustomerService::class)->hasSimilarNames(
                                    $department,
                                    (string) $get('name'),
                                )),
                        ])
                        ->createOptionUsing(function (array $data, Schema $schema) use ($department): int {
                            $actor = auth()->user();
                            abort_unless($actor instanceof User, 403);
                            $reviewedSimilarNames = (bool) ($data['reviewed_similar_names'] ?? false);
                            unset($data['reviewed_similar_names']);
                            $data = ContactPhonesField::toServiceData($data);

                            return FilamentValidationHandler::run(
                                fn (): int => (int) app(CustomerService::class)->create($actor, $department, $data, $reviewedSimilarNames)->getKey(),
                                $schema,
                                'تعذر إضافة العميل'
                            );
                        })
                        ->live(),
                    Select::make('broker_id')
                        ->label('الوسيط')
                        ->options(fn (): array => $brokerClass::query()
                            ->with('phoneNumbers')
                            ->orderBy('name')
                            ->get(['id', 'name'])
                            ->mapWithKeys(function ($broker): array {
                                $primaryPhone = $broker->primaryPhone()?->phone_e164;
                                $additionalCount = $broker->phoneNumbers->where('is_primary', false)->count();
                                $label = filled($primaryPhone) ? "{$broker->name} — {$primaryPhone}" : $broker->name;

                                if ($additionalCount > 0) {
                                    $label .= " (+{$additionalCount} أرقام إضافية)";
                                }

                                return [$broker->id => $label];
                            })
                            ->all())
                        ->searchable()
                        ->placeholder('اختر وسيطًا إن وجد')
                        ->createOptionForm(BrokerFormSchema::fields($department))
                        ->createOptionModalHeading('إضافة وسيط')
                        ->createOptionAction(fn (Action $action) => $action->modalSubmitActionLabel('إضافة الوسيط'))
                        ->createOptionUsing(function (array $data, Schema $schema) use ($department): int {
                            $actor = auth()->user();
                            abort_unless($actor instanceof User, 403);
                            $reviewedSimilarNames = (bool) ($data['reviewed_similar_names'] ?? false);
                            unset($data['reviewed_similar_names']);
                            $data = ContactPhonesField::toServiceData($data);

                            return FilamentValidationHandler::run(
                                fn (): int => (int) app(BrokerService::class)->create($actor, $department, $data, $reviewedSimilarNames)->getKey(),
                                $schema,
                                'تعذر إضافة الوسيط'
                            );
                        })
                        ->live(),
                    Select::make('contact_party')
                        ->label('جهة الاتصال')
                        ->options($contactOptions)
                        ->required(fn (Get $get): bool => filled($get('customer_id')) && filled($get('broker_id')))
                        ->dehydrated(fn (Get $get): bool => filled($get('customer_id')) && filled($get('broker_id')))
                        ->visible(fn (Get $get): bool => filled($get('customer_id')) && filled($get('broker_id'))),
                    Select::make('area_id')
                        ->label('المنطقة والمحافظة')
                        ->options($areaOptions)
                        ->searchable()
                        ->required()
                        ->createOptionForm([
                            Select::make('governorate')
                                ->label('المحافظة')
                                ->options(['القاهرة' => 'القاهرة', 'الجيزة' => 'الجيزة'])
                                ->required(),
                            TextInput::make('name')
                                ->label('اسم المنطقة')
                                ->required()
                                ->maxLength(100),
                        ])
                        ->createOptionUsing(function (array $data, Schema $schema): int {
                            $actor = auth()->user();
                            abort_unless($actor instanceof User, 403);

                            return FilamentValidationHandler::run(
                                fn (): int => (int) app(AreaService::class)->create($actor, $data)->getKey(),
                                $schema,
                                'تعذر إضافة المنطقة'
                            );
                        })
                        ->createOptionAction(fn ($action) => $action->visible(
                            fn (): bool => auth()->user()?->is_company_manager || (bool) auth()->user()?->canAddRentalAreas()
                        )),
                    Hidden::make('lead_source_referrer_details_touched')->default(false),
                    Hidden::make('lead_source_details_touched')->default(false),
                    Hidden::make('lead_source_selection_changed')->default(false)->dehydrated(false),
                    Select::make('lead_source_id')
                        ->label('مصدر الوصول')
                        ->options(fn (Get $get): array => $sourceClass::query()
                            ->where(function ($query) use ($editing, $get): void {
                                $query->where(function ($query): void {
                                    $query->where('is_active', true)->whereIn('type', [
                                        LeadSourceType::Awareness->value,
                                        LeadSourceType::PersonReferral->value,
                                        LeadSourceType::Other->value,
                                    ]);
                                });
                                if ($editing && filled($get('lead_source_id'))) {
                                    $query->orWhere($query->getModel()->getQualifiedKeyName(), $get('lead_source_id'));
                                }
                            })
                            ->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('lead_source_other', null);
                            $set('lead_source_details_touched', false);
                            $set('lead_source_referrer_name', null);
                            $set('lead_source_referrer_unknown', false);
                            $set('lead_source_referrer_details_touched', false);
                            $set('lead_source_selection_changed', true);
                        }),
                    TextInput::make('lead_source_other')
                        ->label(fn (Get $get): string => self::selectedLeadSourceType($sourceClass, $get) === LeadSourceType::Other
                            ? 'توضيح مصدر الوصول'
                            : 'المنصة أو تفاصيل مصدر الوصول')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => self::selectedLeadSourceNeedsDetails($sourceClass, $get))
                        ->required(function (Get $get) use ($sourceClass, $editing): bool {
                            if (! self::selectedLeadSourceNeedsDetails($sourceClass, $get)) {
                                return false;
                            }

                            $sourceType = self::selectedLeadSourceType($sourceClass, $get);

                            return ! $editing
                                || $sourceType === LeadSourceType::Other
                                || filled($get('lead_source_other'))
                                || (bool) $get('lead_source_selection_changed')
                                || (bool) $get('lead_source_details_touched');
                        })
                        ->dehydrated(fn (Get $get): bool => ! $editing
                            || self::selectedLeadSourceType($sourceClass, $get) === LeadSourceType::Other
                            || (bool) $get('lead_source_selection_changed')
                            || (bool) $get('lead_source_details_touched'))
                        ->dehydratedWhenHidden()
                        ->afterStateUpdated(function (Set $set): void {
                            $set('lead_source_details_touched', true);
                        }),
                    TextInput::make('lead_source_referrer_name')
                        ->label('اسم الشخص المُحيل')
                        ->maxLength(255)
                        ->live(debounce: 400)
                        ->visible(fn (Get $get): bool => self::selectedLeadSourceType($sourceClass, $get) === LeadSourceType::PersonReferral)
                        ->dehydrated(fn (Get $get): bool => self::selectedLeadSourceType($sourceClass, $get) === LeadSourceType::PersonReferral && (! $editing || (bool) $get('lead_source_referrer_details_touched') || (bool) $get('lead_source_selection_changed')))
                        ->dehydratedWhenHidden()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if (filled($state)) {
                                $set('lead_source_referrer_unknown', false);
                            }
                            $set('lead_source_referrer_details_touched', true);
                        }),
                    Checkbox::make('lead_source_referrer_unknown')
                        ->label('الاسم غير معروف')
                        ->live()
                        ->visible(fn (Get $get): bool => self::selectedLeadSourceType($sourceClass, $get) === LeadSourceType::PersonReferral)
                        ->dehydrated(fn (Get $get): bool => self::selectedLeadSourceType($sourceClass, $get) === LeadSourceType::PersonReferral && (! $editing || (bool) $get('lead_source_referrer_details_touched') || (bool) $get('lead_source_selection_changed')))
                        ->dehydratedWhenHidden()
                        ->afterStateUpdated(function ($state, Set $set): void {
                            if ($state) {
                                $set('lead_source_referrer_name', null);
                            }
                            $set('lead_source_referrer_details_touched', true);
                        }),
                    ...(! $editing ? [Select::make('owner_user_id')
                        ->label('الموظف المسؤول')
                        ->options(function () use ($department): array {
                            $actor = auth()->user();
                            $query = User::query();

                            if ($actor instanceof User && $actor->canManageDepartment($department)) {
                                $query->where(function ($query) use ($actor, $department): void {
                                    $query->whereKey($actor->id)->orWhereHas(
                                        'departmentMemberships',
                                        fn ($membershipQuery) => $membershipQuery->where('department', $department->value),
                                    );
                                });
                            } elseif ($actor instanceof User) {
                                $query->whereKey($actor->id);
                            } else {
                                $query->whereRaw('1 = 0');
                            }

                            return $query->orderBy('name')->pluck('name', 'id')->all();
                        })
                        ->default(fn (): ?int => auth()->id())
                        ->searchable()
                        ->required()] : []),
                    ...(! $editing ? [DateTimePicker::make('next_follow_up_at')
                        ->label('موعد المتابعة القادمة')
                        ->native(false)
                        ->seconds(false)
                        ->minDate(now()->addMinute())
                        ->required()] : []),
                ])
                ->columns(2),
        ];

        if ($department === Department::Rental) {
            $components[] = Section::make('متطلبات الإيجار')
                ->icon('heroicon-o-home-modern')
                ->schema([
                    Select::make('client_category')->label('نوع العميل')->options(RequestClientCategory::options()),
                    Select::make('unit_type')->label('نوع الوحدة')->options(self::unitTypeOptions())->live(),
                    TextInput::make('unit_type_other')->label('توضيح نوع الوحدة')->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('unit_type') === RequestUnitType::Other->value)
                        ->required(fn (Get $get): bool => $get('unit_type') === RequestUnitType::Other->value),
                    TextInput::make('bedrooms')->label('غرف النوم')->numeric()->integer()->minValue(1)->maxValue(100),
                    TextInput::make('occupants')->label('الأفراد')->numeric()->integer()->minValue(1)->maxValue(1000),
                    Select::make('rental_term')
                        ->label('مدة الإيجار')
                        ->options(self::rentalTermOptions())
                        ->helperText('في الإيجار نصف السنوي والسنوي يكون السداد شهريًا.')
                        ->live(),
                    TextInput::make('rental_term_count')->label('مدة العقد (بالأرقام)')->numeric()->integer()->minValue(1)->maxValue(3650),
                    Select::make('finishing')
                        ->label('نوع الفرش')
                        ->options(fn (Get $get): array => self::finishingOptions($editing, $get('finishing')))
                        ->live(),
                    TextInput::make('finishing_other')->label('توضيح نوع الفرش')->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('finishing') === RentalFurnishingType::Other->value)
                        ->required(fn (Get $get): bool => $get('finishing') === RentalFurnishingType::Other->value),
                    Select::make('floor_preference')->label('الدور المفضل')->options(self::floorPreferenceOptions()),
                    Toggle::make('elevator_required')->label('يشترط مصعد'),
                ])
                ->columns(2);
        } else {
            $components[] = Section::make('متطلبات الشراء')
                ->icon('heroicon-o-home-modern')
                ->schema([
                    Select::make('client_category')->label('نوع العميل')->options(RequestClientCategory::options()),
                    Select::make('unit_type')->label('نوع الوحدة')->options(self::unitTypeOptions())->live(),
                    TextInput::make('unit_type_other')->label('توضيح نوع الوحدة')->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('unit_type') === RequestUnitType::Other->value)
                        ->required(fn (Get $get): bool => $get('unit_type') === RequestUnitType::Other->value),
                    TextInput::make('area_min')->label('المساحة من (م²)')->numeric()->minValue(0.01),
                    TextInput::make('area_max')->label('المساحة إلى (م²)')->numeric()->minValue(0.01),
                    TextInput::make('occupants')->label('عدد الأفراد')->numeric()->integer()->minValue(1)->maxValue(1000),
                    TextInput::make('bedrooms')->label('عدد الغرف')->numeric()->integer()->minValue(1)->maxValue(100),
                    TextInput::make('bathrooms')->label('عدد الحمامات')->numeric()->integer()->minValue(1)->maxValue(100),
                    TextInput::make('building_year')->label('سنة البناء')->numeric()->integer()->minValue(1800)->maxValue((int) now()->format('Y')),
                    Select::make('elevator_required')->label('المصعد')->options(self::booleanPreferenceOptions('مطلوب', 'غير مطلوب'))->placeholder('غير محدد'),
                    Select::make('water_meter')->label('عداد المياه')->options(self::booleanPreferenceOptions('يوجد', 'لا يوجد'))->placeholder('غير محدد'),
                    Select::make('electricity_meter_type')->label('عداد الكهرباء')->options(ElectricityMeterType::options())->placeholder('غير محدد'),
                    Select::make('gas_available')->label('عداد الغاز')->options(self::booleanPreferenceOptions('يوجد', 'لا يوجد'))->placeholder('غير محدد'),
                ])
                ->columns(2);
        }

        $components[] = Section::make('الميزانية والملاحظات')
            ->icon('heroicon-o-banknotes')
            ->columnSpanFull()
            ->schema([
                TextInput::make('budget_declared')->label('ميزانية العميل')->numeric()->minValue(0.01),
                TextInput::make('budget_targeted')->label('ميزانية السوق')->numeric()->minValue(0.01),
                Select::make('budget_currency')
                    ->label('العملة')
                    ->options(['EGP' => 'جنيه مصري', 'USD' => 'دولار أمريكي'])
                    ->default('EGP'),
                Textarea::make('other_requirements')->label('متطلبات أخرى')->rows(3)->columnSpanFull(),
                Textarea::make('notes')->label('ملاحظات داخلية')->rows(3)->columnSpanFull(),
            ])
            ->columns(3);

        return $schema->components($components);
    }

    public static function details(Schema $schema, Department $department): Schema
    {
        $stageOptions = self::stageOptions($department);

        $mainComponents = [
            Section::make('بيانات التواصل')
                ->icon('heroicon-o-user-group')
                ->schema([
                    TextEntry::make('customer.name')
                        ->label('العميل')
                        ->icon('heroicon-m-user')
                        ->weight(FontWeight::Bold)
                        ->placeholder('غير مربوط')
                        ->formatStateUsing(function ($state, ?Model $record) use ($department): string {
                            if (blank($state)) {
                                return 'غير معروف';
                            }
                            $code = RecordCode::customer($department, $record?->customer_id);

                            return $code ? "{$state} ({$code})" : (string) $state;
                        }),
                    TextEntry::make('customer_primary_phone')
                        ->label('هاتف العميل')
                        ->icon('heroicon-m-phone')
                        ->state(fn (?Model $record): ?string => $record?->customer?->primaryPhone()?->phone_e164)
                        ->placeholder('—')
                        ->copyable()
                        ->copyMessage('تم نسخ رقم الهاتف')
                        ->url(fn (?Model $record): ?string => ($phone = $record?->customer?->primaryPhone()?->phone_e164) ? "tel:{$phone}" : null)
                        ->extraAttributes(['class' => 'font-mono text-primary-600 dark:text-primary-400']),
                    TextEntry::make('customer_whatsapp')
                        ->label('واتساب العميل')
                        ->icon('heroicon-m-chat-bubble-left-ellipsis')
                        ->state(fn (?Model $record): ?string => $record?->customer?->primaryPhone()?->phone_e164 ? 'واتساب' : null)
                        ->placeholder('—')
                        ->url(function (?Model $record): ?string {
                            $phone = $record?->customer?->primaryPhone()?->phone_e164;
                            if (blank($phone)) {
                                return null;
                            }
                            $digits = preg_replace('/\D+/', '', $phone);

                            return "https://wa.me/{$digits}";
                        })
                        ->openUrlInNewTab()
                        ->badge()
                        ->color('success')
                        ->visible(fn (?Model $record): bool => filled($record?->customer?->primaryPhone()?->phone_e164)),
                    TextEntry::make('contact_party')
                        ->label('جهة الاتصال')
                        ->badge()
                        ->color(fn ($state): string => match (self::enumValue($state)) {
                            'customer' => 'primary',
                            'broker' => 'warning',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn ($state): string => match (self::enumValue($state)) {
                            'customer' => 'العميل',
                            'broker' => 'الوسيط',
                            default => '—',
                        }),
                    TextEntry::make('broker.name')
                        ->label('الوسيط العقاري')
                        ->icon('heroicon-m-briefcase')
                        ->state(fn (?Model $record): ?string => $record?->broker?->name ?? $record?->broker_name_snapshot)
                        ->placeholder('لا يوجد وسيط'),
                    TextEntry::make('broker_primary_phone')
                        ->label('هاتف الوسيط')
                        ->icon('heroicon-m-phone')
                        ->state(fn (?Model $record): ?string => self::brokerPrimaryPhone($record))
                        ->placeholder('—')
                        ->copyable()
                        ->copyMessage('تم نسخ رقم هاتف الوسيط')
                        ->url(fn (?Model $record): ?string => ($phone = self::brokerPrimaryPhone($record)) ? "tel:{$phone}" : null)
                        ->extraAttributes(['class' => 'font-mono text-primary-600 dark:text-primary-400', 'dir' => 'ltr'])
                        ->visible(fn (?Model $record): bool => filled($record?->broker_id) || filled($record?->broker_name_snapshot)),
                    TextEntry::make('area_location')
                        ->label('المنطقة والمحافظة')
                        ->icon('heroicon-m-map-pin')
                        ->state(fn (?Model $record): string => $record?->area ? "{$record->area->governorate} — {$record->area->name}" : '—')
                        ->placeholder('—'),
                    TextEntry::make('leadSource.name')
                        ->label('مصدر الوصول')
                        ->icon('heroicon-m-arrow-trending-up')
                        ->placeholder('—'),
                    TextEntry::make('lead_source_other')
                        ->label(fn (?Model $record): string => $record?->leadSource?->type === LeadSourceType::Other
                            ? 'توضيح مصدر الوصول'
                            : 'المنصة أو تفاصيل مصدر الوصول')
                        ->placeholder('—')
                        ->visible(fn (?Model $record): bool => filled($record?->lead_source_other)),
                    TextEntry::make('lead_source_referrer_display')
                        ->label('الشخص المُحيل')
                        ->state(fn (?Model $record): string => match (true) {
                            filled($record?->lead_source_referrer_name) => (string) $record->lead_source_referrer_name,
                            $record?->lead_source_referrer_unknown === true => 'الاسم غير معروف',
                            default => 'لم يُسجل',
                        })
                        ->visible(fn (?Model $record): bool => $record?->leadSource?->type === LeadSourceType::PersonReferral),
                ])
                ->columns(['default' => 1, 'sm' => 2]),
        ];

        if ($department === Department::Rental) {
            $mainComponents[] = Section::make('مواصفات الوحدة')
                ->icon('heroicon-o-home-modern')
                ->schema([
                    TextEntry::make('unit_type')
                        ->label('نوع الوحدة')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn ($state): string => self::unitTypeOptions()[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('unit_type_other')
                        ->label('توضيح نوع الوحدة')
                        ->placeholder('—')
                        ->visible(fn (?Model $record): bool => filled($record?->unit_type_other)),
                    TextEntry::make('client_category')
                        ->label('نوع العميل')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => RequestClientCategory::options()[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('rental_term_summary')
                        ->label('مدة الإيجار')
                        ->icon('heroicon-m-calendar-days')
                        ->state(function (?Model $record): string {
                            if (! $record || blank($record->rental_term)) {
                                return '—';
                            }
                            $term = self::rentalTermOptions()[self::enumValue($record->rental_term) ?? ''] ?? '—';
                            $count = $record->rental_term_count;

                            return $count ? "{$term} ({$count})" : $term;
                        }),
                    TextEntry::make('finishing')
                        ->label('نوع الفرش')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn ($state): string => self::finishingOptions(true, $state)[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('finishing_other')
                        ->label('توضيح نوع الفرش')
                        ->placeholder('—')
                        ->visible(fn (?Model $record): bool => filled($record?->finishing_other)),
                    TextEntry::make('bedrooms')
                        ->label('غرف النوم')
                        ->icon('heroicon-m-building-office')
                        ->placeholder('—'),
                    TextEntry::make('occupants')
                        ->label('الأفراد')
                        ->icon('heroicon-m-user-group')
                        ->placeholder('—'),
                    TextEntry::make('floor_preference')
                        ->label('الدور المفضل')
                        ->formatStateUsing(fn ($state): string => self::floorPreferenceOptions()[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('elevator_required')
                        ->label('المصعد')
                        ->badge()
                        ->color(fn ($state): string => $state === null ? 'gray' : ($state ? 'success' : 'gray'))
                        ->formatStateUsing(fn ($state): string => $state === null ? '—' : ($state ? 'يشترط مصعد' : 'لا يشترط')),
                ])
                ->columns(['default' => 1, 'sm' => 2, 'md' => 3]);
        } else {
            $mainComponents[] = Section::make('مواصفات الوحدة')
                ->icon('heroicon-o-home-modern')
                ->schema([
                    TextEntry::make('unit_type')
                        ->label('نوع الوحدة')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn ($state): string => self::unitTypeOptions()[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('unit_type_other')
                        ->label('توضيح نوع الوحدة')
                        ->placeholder('—')
                        ->visible(fn (?Model $record): bool => filled($record?->unit_type_other)),
                    TextEntry::make('area_range')
                        ->label('نطاق المساحة المطلوبة')
                        ->icon('heroicon-m-arrows-pointing-out')
                        ->state(function (?Model $record): string {
                            if (! $record) {
                                return '—';
                            }
                            $min = $record->area_min ? number_format((float) $record->area_min, 1) : null;
                            $max = $record->area_max ? number_format((float) $record->area_max, 1) : null;
                            if ($min && $max) {
                                return "من {$min} م² إلى {$max} م²";
                            }
                            if ($min) {
                                return "من {$min} م² فما فوق";
                            }
                            if ($max) {
                                return "حتى {$max} م²";
                            }

                            return '—';
                        }),
                    TextEntry::make('area_min')
                        ->label('المساحة من (م²)')
                        ->numeric(decimalPlaces: 2)
                        ->placeholder('—'),
                    TextEntry::make('area_max')
                        ->label('المساحة إلى (م²)')
                        ->numeric(decimalPlaces: 2)
                        ->placeholder('—'),
                    TextEntry::make('client_category')
                        ->label('نوع العميل')
                        ->badge()
                        ->formatStateUsing(fn ($state): string => RequestClientCategory::options()[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('occupants')->label('عدد الأفراد')->placeholder('—'),
                    TextEntry::make('bedrooms')->label('عدد الغرف')->placeholder('—'),
                    TextEntry::make('bathrooms')->label('عدد الحمامات')->placeholder('—'),
                    TextEntry::make('building_year')->label('سنة البناء')->placeholder('—'),
                    TextEntry::make('elevator_required')
                        ->label('المصعد')
                        ->formatStateUsing(fn ($state): string => $state === null ? 'غير محدد' : ($state ? 'مطلوب' : 'غير مطلوب')),
                    TextEntry::make('water_meter')
                        ->label('عداد المياه')
                        ->formatStateUsing(fn ($state): string => $state === null ? 'غير محدد' : ($state ? 'يوجد' : 'لا يوجد')),
                    TextEntry::make('electricity_meter_type')
                        ->label('عداد الكهرباء')
                        ->formatStateUsing(fn ($state): string => ElectricityMeterType::tryFrom(self::enumValue($state) ?? '')?->label() ?? 'غير محدد'),
                    TextEntry::make('gas_available')
                        ->label('عداد الغاز')
                        ->formatStateUsing(fn ($state): string => $state === null ? 'غير محدد' : ($state ? 'يوجد' : 'لا يوجد')),
                ])
                ->columns(['default' => 1, 'sm' => 2, 'md' => 3]);
        }

        $mainComponents[] = Section::make('الميزانية')
            ->icon('heroicon-o-banknotes')
            ->schema([
                TextEntry::make('budget_declared')
                    ->label('ميزانية العميل')
                    ->weight(FontWeight::Bold)
                    ->size(TextSize::Large)
                    ->color('primary')
                    ->formatStateUsing(function ($state, ?Model $record): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }
                        $curr = $record?->budget_currency === 'USD' ? 'USD' : 'ج.م';

                        return number_format((float) $state, 2)." {$curr}";
                    })
                    ->placeholder('—'),
                TextEntry::make('budget_targeted')
                    ->label('ميزانية السوق')
                    ->weight(FontWeight::SemiBold)
                    ->size(TextSize::Medium)
                    ->formatStateUsing(function ($state, ?Model $record): string {
                        if ($state === null || $state === '') {
                            return '—';
                        }
                        $curr = $record?->budget_currency === 'USD' ? 'USD' : 'ج.م';

                        return number_format((float) $state, 2)." {$curr}";
                    })
                    ->placeholder('—'),
                TextEntry::make('budget_currency')
                    ->label('العملة المعتمدة')
                    ->badge()
                    ->color(fn ($state): string => $state === 'USD' ? 'success' : 'info')
                    ->formatStateUsing(fn ($state): string => match ($state) {
                        'USD' => 'دولار أمريكي (USD)',
                        'EGP' => 'جنيه مصري (EGP)',
                        default => '—',
                    }),
            ])
            ->columns(['default' => 1, 'sm' => 3]);

        $mainComponents[] = Section::make('ملاحظات إضافية')
            ->icon('heroicon-o-document-text')
            ->collapsible()
            ->schema([
                TextEntry::make('other_requirements')
                    ->label('متطلبات أخرى')
                    ->placeholder('لا توجد متطلبات أخرى مسجلة')
                    ->columnSpanFull(),
                TextEntry::make('notes')
                    ->label('ملاحظات داخلية')
                    ->placeholder('لا توجد ملاحظات داخلية')
                    ->columnSpanFull(),
            ]);

        $mainComponents[] = Section::make('تفاصيل الإغلاق')
            ->icon('heroicon-o-lock-closed')
            ->schema([
                TextEntry::make('outcome')
                    ->label('النتيجة')
                    ->badge()
                    ->color(fn ($state): string => match (self::enumValue($state)) {
                        'successful' => 'success',
                        'unsuccessful' => 'danger',
                        default => 'gray',
                    })
                    ->formatStateUsing(fn ($state): string => match (self::enumValue($state)) {
                        'successful' => 'ناجح',
                        'unsuccessful' => 'غير ناجح',
                        default => '—',
                    }),
                TextEntry::make('closeReason.name')
                    ->label('تصنيف الإغلاق')
                    ->placeholder('—'),
                TextEntry::make('outcome_reason_text')
                    ->label('سبب الإغلاق (سجل قديم)')
                    ->placeholder('—')
                    ->columnSpanFull(),
                TextEntry::make('closed_at')
                    ->label('تاريخ ووقت الإغلاق')
                    ->dateTime('d/m/Y H:i')
                    ->placeholder('—'),
                TextEntry::make('outcome_notes')
                    ->label('ملاحظات الإغلاق')
                    ->placeholder('—')
                    ->columnSpanFull(),
            ])
            ->columns(['default' => 1, 'sm' => 2])
            ->visible(fn (?Model $record): bool => filled($record?->outcome));

        $sidebarComponents = [
            Section::make('حالة الطلب')
                ->icon('heroicon-o-arrow-path')
                ->schema([
                    TextEntry::make('status')
                        ->label('الحالة')
                        ->badge()
                        ->color(fn ($state): string => match (self::enumValue($state)) {
                            'active' => 'success',
                            'paused' => 'warning',
                            'closed' => 'gray',
                            default => 'gray',
                        })
                        ->formatStateUsing(fn ($state): string => match (self::enumValue($state)) {
                            'active' => 'نشط',
                            'paused' => 'متوقف مؤقتًا',
                            'closed' => 'مغلق',
                            default => '—',
                        }),
                    TextEntry::make('stage')
                        ->label('المرحلة')
                        ->badge()
                        ->color('primary')
                        ->formatStateUsing(fn ($state): string => $stageOptions[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('pause_reason')
                        ->label('سبب الإيقاف المؤقت')
                        ->placeholder('—')
                        ->visible(fn (?Model $record): bool => $record?->status === RequestStatus::Paused),
                    TextEntry::make('resume_at')
                        ->label('موعد الاستئناف المخطط')
                        ->dateTime('d/m/Y H:i')
                        ->placeholder('—')
                        ->visible(fn (?Model $record): bool => $record?->status === RequestStatus::Paused),
                    TextEntry::make('next_follow_up_at')
                        ->label('موعد المتابعة القادمة')
                        ->icon('heroicon-m-calendar')
                        ->badge()
                        ->color(function ($state, ?Model $record): string {
                            if (! $state) {
                                return 'gray';
                            }
                            if ($record?->status === RequestStatus::Active) {
                                $date = Carbon::parse($state);
                                if ($date->isPast()) {
                                    return 'danger';
                                }
                                if ($date->isToday()) {
                                    return 'warning';
                                }

                                return 'info';
                            }

                            return 'gray';
                        })
                        ->formatStateUsing(function ($state, ?Model $record) {
                            if (! $state) {
                                return 'غير محدد';
                            }
                            $date = Carbon::parse($state);
                            $presenter = app(\App\Support\DateTimePresenter::class);
                            $formatted = $presenter->bidi(
                                $record?->status === RequestStatus::Active
                                    ? $presenter->formatRelativeDateTime($state, now())
                                    : $presenter->formatDateTime($state),
                            )->toHtml();
                            if ($record?->status === RequestStatus::Active) {
                                if ($date->isPast()) {
                                    return new \Illuminate\Support\HtmlString($formatted . ' (' . e('متأخرة') . ')');
                                }
                            }

                            return new \Illuminate\Support\HtmlString($formatted);
                        })
                        ->html(),
                ])
                ->columns(1),

            Section::make('المسؤولية')
                ->icon('heroicon-o-identification')
                ->schema([
                    TextEntry::make('owner.name')
                        ->label('الموظف المسؤول')
                        ->icon('heroicon-m-user-circle')
                        ->weight(FontWeight::SemiBold)
                        ->placeholder('غير محدد'),
                    TextEntry::make('pending_reassignment')
                        ->label('نقل المسؤولية')
                        ->badge()
                        ->color('warning')
                        ->state('طلب نقل معلق')
                        ->visible(fn (?Model $record): bool => (int) ($record?->pending_reassignment_count ?? 0) > 0),
                    TextEntry::make('created_at')
                        ->label('تاريخ التسجيل')
                        ->icon('heroicon-m-calendar')
                        ->formatStateUsing(fn ($state) => app(\App\Support\DateTimePresenter::class)->bidi(app(\App\Support\DateTimePresenter::class)->formatDateTime($state)))
                        ->html(),
                    TextEntry::make('updated_at')
                        ->label('آخر تحديث')
                        ->icon('heroicon-m-clock')
                        ->formatStateUsing(fn ($state) => app(\App\Support\DateTimePresenter::class)->bidi(app(\App\Support\DateTimePresenter::class)->formatDateTime($state)))
                        ->html(),
                ])
                ->columns(1),

            Section::make('سجل النشاط')
                ->icon('heroicon-o-chart-bar')
                ->schema([
                    TextEntry::make('activities_count')
                        ->label('التواصل')
                        ->icon('heroicon-m-phone-arrow-up-right')
                        ->badge()
                        ->color('gray')
                        ->state(fn (?Model $record): int => (int) ($record?->activities_count ?? $record?->activities()->count() ?? 0)),
                    TextEntry::make('recommendations_count')
                        ->label('الترشيحات')
                        ->icon('heroicon-m-document-magnifying-glass')
                        ->badge()
                        ->color('gray')
                        ->state(fn (?Model $record): int => (int) ($record?->recommendations_count ?? $record?->recommendations()->count() ?? 0)),
                    TextEntry::make('viewings_count')
                        ->label('المعاينات')
                        ->icon('heroicon-m-eye')
                        ->badge()
                        ->color('gray')
                        ->state(fn (?Model $record): int => (int) ($record?->viewings_count ?? $record?->viewings()->count() ?? 0)),
                    TextEntry::make('deal_status')
                        ->label('الصفقة')
                        ->icon('heroicon-m-check-badge')
                        ->badge()
                        ->color(fn (?Model $record): string => $record?->deal ? 'success' : 'gray')
                        ->state(fn (?Model $record): string => $record?->deal ? 'منجزة' : 'لا توجد'),
                ])
                ->columns(2),
        ];

        return $schema->columns(1)->components([
            Grid::make(['default' => 1, 'lg' => 3])
                ->columnSpanFull()
                ->schema([
                    Group::make($mainComponents)
                        ->columnSpan(['default' => 1, 'lg' => 2]),
                    Group::make($sidebarComponents)
                        ->columnSpan(['default' => 1, 'lg' => 1]),
                ]),
        ]);
    }

    /** @return array<string, string> */
    public static function stageOptions(Department $department): array
    {
        return [
            'unit_search' => 'بحث عن وحدات',
            'recommendation' => 'ترشيح',
            'viewing' => 'معاينة',
            'negotiation' => 'تفاوض',
        ];
    }

    /** @return array<string, string> */
    private static function unitTypeOptions(): array
    {
        return [
            RequestUnitType::Apartment->value => 'شقة',
            RequestUnitType::Studio->value => 'استوديو',
            RequestUnitType::Villa->value => 'فيلا',
            RequestUnitType::Other->value => 'أخرى',
        ];
    }

    /** @return array<string, string> */
    private static function rentalTermOptions(): array
    {
        return [
            RentalTerm::Daily->value => 'يومي',
            RentalTerm::Monthly->value => 'شهري',
            RentalTerm::HalfYearly->value => 'نصف سنوي',
            RentalTerm::Yearly->value => 'سنوي',
        ];
    }

    /** @return array<string, string> */
    private static function finishingOptions(bool $includeLegacy = false, mixed $current = null): array
    {
        $options = RentalFurnishingType::options();
        $currentValue = self::enumValue($current);

        if ($includeLegacy && filled($currentValue) && ! array_key_exists($currentValue, $options)) {
            $options[$currentValue] = self::legacyFinishingLabel($currentValue);
        }

        return $options;
    }

    private static function legacyFinishingLabel(string $value): string
    {
        return 'قيمة قديمة — '.match ($value) {
            FinishingLevel::Unfinished->value => 'بدون تشطيب',
            FinishingLevel::HalfFinished->value => 'نصف تشطيب',
            FinishingLevel::UltraSuperLux->value => 'ألترا سوبر لوكس',
            FinishingLevel::Smart->value => 'ذكي',
            default => $value,
        };
    }

    /** @return array<string, string> */
    private static function booleanPreferenceOptions(string $yes, string $no): array
    {
        return ['1' => $yes, '0' => $no];
    }

    /** @return array<string, string> */
    private static function floorPreferenceOptions(): array
    {
        return [
            FloorPreference::Ground->value => 'أرضي',
            FloorPreference::Low->value => 'أدوار منخفضة',
            FloorPreference::Middle->value => 'أدوار متوسطة',
            FloorPreference::High->value => 'أدوار مرتفعة',
            FloorPreference::Top->value => 'أخير',
            FloorPreference::NoPreference->value => 'لا يفرق',
        ];
    }

    private static function brokerPrimaryPhone(?Model $record): ?string
    {
        $broker = $record?->broker;
        if ($broker) {
            return $broker->primaryPhone()?->phone_e164;
        }

        $snapshot = collect($record?->broker_phone_numbers_snapshot ?? [])
            ->first(fn (mixed $phone): bool => (bool) data_get($phone, 'is_primary'));

        return data_get($snapshot, 'phone_e164') ?: $record?->broker_phone_snapshot;
    }

    /** @param class-string<Model> $sourceClass */
    private static function selectedLeadSourceType(string $sourceClass, Get $get): ?LeadSourceType
    {
        if (blank($get('lead_source_id'))) {
            return null;
        }

        $type = $sourceClass::query()->whereKey($get('lead_source_id'))->value('type');

        if ($type instanceof LeadSourceType) {
            return $type;
        }

        return is_string($type) ? LeadSourceType::tryFrom($type) : null;
    }

    /** @param class-string<Model> $sourceClass */
    private static function selectedLeadSourceNeedsDetails(string $sourceClass, Get $get): bool
    {
        if (self::selectedLeadSourceType($sourceClass, $get) === LeadSourceType::Other) {
            return true;
        }

        if (blank($get('lead_source_id'))) {
            return false;
        }

        return (bool) $sourceClass::query()->whereKey($get('lead_source_id'))->value('requires_details');
    }

    private static function enumValue(mixed $state): ?string
    {
        if ($state instanceof \BackedEnum) {
            return (string) $state->value;
        }

        return is_string($state) ? $state : null;
    }
}
