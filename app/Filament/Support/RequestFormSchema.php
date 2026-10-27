<?php

namespace App\Filament\Support;

use App\Enums\ContactParty;
use App\Enums\Department;
use App\Enums\FinishingLevel;
use App\Enums\FloorPreference;
use App\Enums\RentalTerm;
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
use App\Services\CustomerPhoneNormalizer;
use App\Services\CustomerService;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Validation\ValidationException;
use Propaganistas\LaravelPhone\PhoneNumber;

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
                            TextInput::make('name')->label('الاسم')->required()->maxLength(255),
                            TextInput::make('phone')
                                ->label('رقم الهاتف')
                                ->required()
                                ->maxLength(40)
                                ->rules([
                                    fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                                        if (blank($value)) {
                                            return;
                                        }
                                        $country = $get('phone_country') ?: 'EG';
                                        try {
                                            app(CustomerPhoneNormalizer::class)->normalize((string) $value, (string) $country);
                                        } catch (ValidationException $e) {
                                            $fail(collect($e->errors())->flatten()->first());
                                        }
                                    },
                                ]),
                            TextInput::make('phone_country')
                                ->label('رمز الدولة')
                                ->default('EG')
                                ->required()
                                ->length(2)
                                ->helperText('رمز الدولة الدولي، مثل EG لمصر.')
                                ->rules([
                                    fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                                        if (filled($value) && ! PhoneNumber::isValidCountry(strtoupper(trim((string) $value)))) {
                                            $fail('رمز الدولة المحدد غير صالح (مثال: EG لمصر).');
                                        }
                                    },
                                ]),
                            TextInput::make('nationality')->label('الجنسية')->maxLength(100),
                            Checkbox::make('reviewed_similar_names')->label('راجعت تنبيه تشابه الاسم وأؤكد أن هذا عميل مختلف.'),
                        ])
                        ->createOptionUsing(function (array $data, Schema $schema) use ($department): int {
                            $actor = auth()->user();
                            abort_unless($actor instanceof User, 403);
                            $reviewedSimilarNames = (bool) ($data['reviewed_similar_names'] ?? false);
                            unset($data['reviewed_similar_names']);

                            return FilamentValidationHandler::run(
                                fn (): int => (int) app(CustomerService::class)->create($actor, $department, $data, $reviewedSimilarNames)->getKey(),
                                $schema,
                                'تعذر إضافة العميل'
                            );
                        })
                        ->live(),
                    Select::make('broker_id')
                        ->label('الوسيط')
                        ->options(fn (): array => $brokerClass::query()->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->placeholder('اختر وسيطًا إن وجد')
                        ->createOptionForm([
                            TextInput::make('name')->label('الاسم')->required()->maxLength(255),
                            TextInput::make('phone')
                                ->label('رقم الهاتف')
                                ->maxLength(40)
                                ->rules([
                                    fn (Get $get): Closure => function (string $attribute, $value, Closure $fail) use ($get): void {
                                        if (blank($value)) {
                                            return;
                                        }
                                        $country = $get('phone_country') ?: 'EG';
                                        try {
                                            app(CustomerPhoneNormalizer::class)->normalize((string) $value, (string) $country);
                                        } catch (ValidationException $e) {
                                            $fail(collect($e->errors())->flatten()->first());
                                        }
                                    },
                                ]),
                            TextInput::make('phone_country')
                                ->label('رمز الدولة')
                                ->default('EG')
                                ->length(2)
                                ->helperText('رمز الدولة الدولي، مثل EG لمصر.')
                                ->rules([
                                    fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                                        if (filled($value) && ! PhoneNumber::isValidCountry(strtoupper(trim((string) $value)))) {
                                            $fail('رمز الدولة المحدد غير صالح (مثال: EG لمصر).');
                                        }
                                    },
                                ]),
                            Checkbox::make('reviewed_similar_names')->label('راجعت تنبيه تشابه الاسم وأؤكد أن هذا وسيط مختلف.'),
                        ])
                        ->createOptionUsing(function (array $data, Schema $schema) use ($department): int {
                            $actor = auth()->user();
                            abort_unless($actor instanceof User, 403);
                            $reviewedSimilarNames = (bool) ($data['reviewed_similar_names'] ?? false);
                            unset($data['reviewed_similar_names']);

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
                        ->label('المحافظة والمنطقة المطلوبة')
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
                    Select::make('lead_source_id')
                        ->label('مصدر الوصول')
                        ->options(fn (): array => $sourceClass::query()
                            ->when(! $editing, fn ($query) => $query->where('is_active', true))
                            ->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live(),
                    TextInput::make('lead_source_other')
                        ->label('مصدر الوصول — توضيح')
                        ->maxLength(255)
                        ->visible(fn (Get $get): bool => filled($get('lead_source_id')) && $sourceClass::query()->whereKey($get('lead_source_id'))->where('is_other', true)->exists())
                        ->required(fn (Get $get): bool => filled($get('lead_source_id')) && $sourceClass::query()->whereKey($get('lead_source_id'))->where('is_other', true)->exists()),
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
                ->schema([
                    Select::make('unit_type')->label('نوع الوحدة')->options(self::unitTypeOptions())->live(),
                    TextInput::make('unit_type_other')->label('نوع الوحدة — توضيح')->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('unit_type') === RequestUnitType::Other->value)
                        ->required(fn (Get $get): bool => $get('unit_type') === RequestUnitType::Other->value),
                    TextInput::make('bedrooms')->label('عدد غرف النوم')->numeric()->integer()->minValue(1)->maxValue(100),
                    TextInput::make('occupants')->label('عدد الأفراد')->numeric()->integer()->minValue(1)->maxValue(1000),
                    Toggle::make('household')->label('الأفراد أسرة'),
                    Select::make('rental_term')
                        ->label('مدة الإيجار')
                        ->options(self::rentalTermOptions())
                        ->helperText('في الإيجار نصف السنوي والسنوي يكون السداد شهريًا ويُكتب العقد لكامل المدة.')
                        ->live(),
                    TextInput::make('rental_term_count')->label('عدد وحدات المدة')->numeric()->integer()->minValue(1)->maxValue(3650),
                    Select::make('finishing')->label('مستوى التشطيب')->options(self::finishingOptions())->live(),
                    TextInput::make('finishing_other')->label('التشطيب — توضيح')->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('finishing') === FinishingLevel::Other->value)
                        ->required(fn (Get $get): bool => $get('finishing') === FinishingLevel::Other->value),
                    Select::make('floor_preference')->label('الدور المفضل')->options(self::floorPreferenceOptions()),
                    Toggle::make('elevator_required')->label('يشترط وجود مصعد'),
                ])
                ->columns(2);
        } else {
            $components[] = Section::make('متطلبات الشراء')
                ->schema([
                    Select::make('unit_type')->label('نوع الوحدة')->options(self::unitTypeOptions())->live(),
                    TextInput::make('unit_type_other')->label('نوع الوحدة — توضيح')->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('unit_type') === RequestUnitType::Other->value)
                        ->required(fn (Get $get): bool => $get('unit_type') === RequestUnitType::Other->value),
                    TextInput::make('area_min')->label('المساحة من (م²)')->numeric()->minValue(0.01),
                    TextInput::make('area_max')->label('المساحة إلى (م²)')->numeric()->minValue(0.01),
                ])
                ->columns(2);
        }

        $budgetDeclaredLabel = $department === Department::Rental ? 'الميزانية المعلنة (القسط الشهري)' : 'الميزانية المعلنة (إجمالي الشراء)';
        $budgetTargetedLabel = $department === Department::Rental ? 'الميزانية المستهدفة (القسط الشهري)' : 'الميزانية المستهدفة (إجمالي الشراء)';

        $components[] = Section::make('الميزانية والمتطلبات الإضافية')
            ->schema([
                TextInput::make('budget_declared')->label($budgetDeclaredLabel)->numeric()->minValue(0.01),
                TextInput::make('budget_targeted')->label($budgetTargetedLabel)->numeric()->minValue(0.01),
                Select::make('budget_currency')
                    ->label('العملة')
                    ->options(['EGP' => 'جنيه مصري', 'USD' => 'دولار أمريكي'])
                    ->default('EGP'),
                Textarea::make('other_requirements')->label('متطلبات أخرى')->rows(3)->columnSpanFull(),
                Textarea::make('notes')->label('ملاحظات داخلية')->rows(3)->columnSpanFull(),
            ])
            ->columns(2);

        return $schema->components($components);
    }

    public static function details(Schema $schema, Department $department): Schema
    {
        $stageOptions = self::stageOptions($department);
        $components = [
            Section::make('الحالة والمتابعة')
                ->schema([
                    TextEntry::make('status')->label('الحالة')->badge()->formatStateUsing(fn ($state): string => match (self::enumValue($state)) {
                        'active' => 'نشط',
                        'paused' => 'متوقف مؤقتًا',
                        'closed' => 'مغلق',
                        default => '—',
                    }),
                    TextEntry::make('stage')->label('المرحلة')->badge()->formatStateUsing(fn ($state): string => $stageOptions[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('outcome')->label('النتيجة')->formatStateUsing(fn ($state): string => match (self::enumValue($state)) {
                        'successful' => 'ناجح',
                        'unsuccessful' => 'غير ناجح',
                        default => '—',
                    }),
                    TextEntry::make('next_follow_up_at')->label('موعد المتابعة القادمة')->dateTime('d/m/Y H:i')->placeholder('—'),
                    TextEntry::make('owner.name')->label('الموظف المسؤول'),
                    TextEntry::make('created_at')->label('تاريخ التسجيل')->dateTime('d/m/Y H:i'),
                ])
                ->columns(2),
            Section::make('أطراف الطلب والموقع')
                ->schema([
                    TextEntry::make('customer.name')->label('العميل')->placeholder('غير معروف'),
                    TextEntry::make('broker.name')->label('الوسيط')->state(fn ($record): ?string => $record?->broker?->name ?? $record?->broker_name_snapshot)->placeholder('—'),
                    TextEntry::make('contact_party')->label('جهة الاتصال')->formatStateUsing(fn ($state): string => match (self::enumValue($state)) {
                        'customer' => 'العميل',
                        'broker' => 'الوسيط',
                        default => '—',
                    }),
                    TextEntry::make('area.name')->label('المنطقة')->placeholder('—'),
                    TextEntry::make('area.governorate')->label('المحافظة')->placeholder('—'),
                    TextEntry::make('leadSource.name')->label('مصدر الوصول')->placeholder('—'),
                    TextEntry::make('lead_source_other')->label('توضيح المصدر')->placeholder('—'),
                ])
                ->columns(2),
        ];

        if ($department === Department::Rental) {
            $components[] = Section::make('متطلبات الإيجار والميزانية')
                ->schema([
                    TextEntry::make('unit_type')->label('نوع الوحدة')->formatStateUsing(fn ($state): string => self::unitTypeOptions()[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('unit_type_other')->label('نوع الوحدة — توضيح')->placeholder('—'),
                    TextEntry::make('bedrooms')->label('عدد غرف النوم')->placeholder('—'),
                    TextEntry::make('occupants')->label('عدد الأفراد')->placeholder('—'),
                    TextEntry::make('household')->label('الأفراد أسرة')->formatStateUsing(fn ($state): string => $state === null ? '—' : ($state ? 'نعم' : 'لا')),
                    TextEntry::make('rental_term')->label('مدة الإيجار')->formatStateUsing(fn ($state): string => self::rentalTermOptions()[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('rental_term_count')->label('عدد وحدات المدة')->placeholder('—'),
                    TextEntry::make('finishing')->label('مستوى التشطيب')->formatStateUsing(fn ($state): string => self::finishingOptions()[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('floor_preference')->label('الدور المفضل')->formatStateUsing(fn ($state): string => self::floorPreferenceOptions()[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('elevator_required')->label('يشترط وجود مصعد')->formatStateUsing(fn ($state): string => $state === null ? '—' : ($state ? 'نعم' : 'لا')),
                    TextEntry::make('budget_declared')->label('الميزانية المعلنة (قسط شهري)')->numeric(decimalPlaces: 2)->placeholder('—'),
                    TextEntry::make('budget_targeted')->label('الميزانية المستهدفة (قسط شهري)')->numeric(decimalPlaces: 2)->placeholder('—'),
                    TextEntry::make('budget_currency')->label('العملة')->formatStateUsing(fn ($state): string => $state === 'USD' ? 'دولار أمريكي' : ($state === 'EGP' ? 'جنيه مصري' : '—')),
                    TextEntry::make('other_requirements')->label('متطلبات أخرى')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('notes')->label('ملاحظات داخلية')->placeholder('—')->columnSpanFull(),
                ])
                ->columns(2);
        } else {
            $components[] = Section::make('متطلبات الشراء والميزانية')
                ->schema([
                    TextEntry::make('unit_type')->label('نوع الوحدة')->formatStateUsing(fn ($state): string => self::unitTypeOptions()[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('area_min')->label('المساحة من (م²)')->numeric(decimalPlaces: 2)->placeholder('—'),
                    TextEntry::make('area_max')->label('المساحة إلى (م²)')->numeric(decimalPlaces: 2)->placeholder('—'),
                    TextEntry::make('budget_declared')->label('الميزانية المعلنة (إجمالي الشراء)')->numeric(decimalPlaces: 2)->placeholder('—'),
                    TextEntry::make('budget_targeted')->label('الميزانية المستهدفة (إجمالي الشراء)')->numeric(decimalPlaces: 2)->placeholder('—'),
                    TextEntry::make('budget_currency')->label('العملة')->formatStateUsing(fn ($state): string => $state === 'USD' ? 'دولار أمريكي' : ($state === 'EGP' ? 'جنيه مصري' : '—')),
                    TextEntry::make('other_requirements')->label('متطلبات أخرى')->placeholder('—')->columnSpanFull(),
                    TextEntry::make('notes')->label('ملاحظات داخلية')->placeholder('—')->columnSpanFull(),
                ])
                ->columns(2);
        }

        $components[] = Section::make('الإغلاق')
            ->schema([
                TextEntry::make('outcome_reason_text')->label('سبب الإغلاق')->placeholder('—'),
                TextEntry::make('closeReason.name')->label('التصنيف المساعد')->placeholder('—'),
                TextEntry::make('outcome_notes')->label('ملاحظات إضافية')->placeholder('—'),
            ])
            ->columns(2)
            ->visible(fn (Model $record): bool => filled($record->outcome));

        return $schema->components($components);
    }

    /** @return array<string, string> */
    public static function stageOptions(Department $department): array
    {
        return [
            'intake' => 'استقبال',
            'qualification' => 'تأهيل',
            'unit_search' => 'بحث عن وحدات',
            'recommendation' => $department === Department::Rental ? 'ترشيح' : 'عرض / ترشيح',
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
    private static function finishingOptions(): array
    {
        return [
            FinishingLevel::Unfinished->value => 'بدون تشطيب',
            FinishingLevel::HalfFinished->value => 'نصف تشطيب',
            FinishingLevel::SuperLux->value => 'سوبر لوكس',
            FinishingLevel::UltraSuperLux->value => 'ألترا سوبر لوكس',
            FinishingLevel::Hotel->value => 'فندقي',
            FinishingLevel::Smart->value => 'ذكي',
            FinishingLevel::Other->value => 'أخرى',
        ];
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

    private static function enumValue(mixed $state): ?string
    {
        if ($state instanceof \BackedEnum) {
            return (string) $state->value;
        }

        return is_string($state) ? $state : null;
    }
}
