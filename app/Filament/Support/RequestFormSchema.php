<?php

namespace App\Filament\Support;

use App\Enums\ContactParty;
use App\Enums\Department;
use App\Enums\FinishingLevel;
use App\Enums\FloorPreference;
use App\Enums\RentalTerm;
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
use App\Services\CustomerPhoneNormalizer;
use App\Services\CustomerService;
use App\Support\RecordCode;
use Carbon\Carbon;
use Closure;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Infolists\Components\TextEntry;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Group;
use Filament\Schemas\Components\Section;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Support\Enums\FontWeight;
use Filament\Support\Enums\TextSize;
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
                                ->helperText('مثال: EG لمصر، SA للسعودية.')
                                ->rules([
                                    fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                                        if (filled($value) && ! PhoneNumber::isValidCountry(strtoupper(trim((string) $value)))) {
                                            $fail('رمز الدولة المحدد غير صالح (مثال: EG لمصر).');
                                        }
                                    },
                                ]),
                            TextInput::make('nationality')->label('الجنسية')->maxLength(100),
                            Checkbox::make('reviewed_similar_names')->label('تأكيد: هذا عميل مختلف رغم تشابه الاسم.'),
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
                                ->helperText('مثال: EG لمصر، SA للسعودية.')
                                ->rules([
                                    fn (): Closure => function (string $attribute, $value, Closure $fail): void {
                                        if (filled($value) && ! PhoneNumber::isValidCountry(strtoupper(trim((string) $value)))) {
                                            $fail('رمز الدولة المحدد غير صالح (مثال: EG لمصر).');
                                        }
                                    },
                                ]),
                            Checkbox::make('reviewed_similar_names')->label('تأكيد: هذا وسيط مختلف رغم تشابه الاسم.'),
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
                    Select::make('lead_source_id')
                        ->label('مصدر الوصول')
                        ->options(fn (): array => $sourceClass::query()
                            ->when(! $editing, fn ($query) => $query->where('is_active', true))
                            ->orderBy('name')->pluck('name', 'id')->all())
                        ->searchable()
                        ->live(),
                    TextInput::make('lead_source_other')
                        ->label('توضيح مصدر الوصول')
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
                    TextInput::make('bedrooms')->label('غرف النوم')->numeric()->integer()->minValue(1)->maxValue(100),
                    TextInput::make('occupants')->label('الأفراد')->numeric()->integer()->minValue(1)->maxValue(1000),
                    Toggle::make('household')->label('أسرة'),
                    Select::make('rental_term')
                        ->label('مدة الإيجار')
                        ->options(self::rentalTermOptions())
                        ->helperText('في الإيجار نصف السنوي والسنوي يكون السداد شهريًا.')
                        ->live(),
                    TextInput::make('rental_term_count')->label('مدة العقد (بالأرقام)')->numeric()->integer()->minValue(1)->maxValue(3650),
                    Select::make('finishing')->label('مستوى التشطيب')->options(self::finishingOptions())->live(),
                    TextInput::make('finishing_other')->label('التشطيب — توضيح')->maxLength(255)
                        ->visible(fn (Get $get): bool => $get('finishing') === FinishingLevel::Other->value)
                        ->required(fn (Get $get): bool => $get('finishing') === FinishingLevel::Other->value),
                    Select::make('floor_preference')->label('الدور المفضل')->options(self::floorPreferenceOptions()),
                    Toggle::make('elevator_required')->label('يشترط مصعد'),
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

        $components[] = Section::make('الميزانية والملاحظات')
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
                    TextEntry::make('customer.phone_e164')
                        ->label('هاتف العميل')
                        ->icon('heroicon-m-phone')
                        ->placeholder('—')
                        ->copyable()
                        ->copyMessage('تم نسخ رقم الهاتف')
                        ->url(fn (?Model $record): ?string => $record?->customer?->phone_e164 ? "tel:{$record->customer->phone_e164}" : null)
                        ->extraAttributes(['class' => 'font-mono text-primary-600 dark:text-primary-400']),
                    TextEntry::make('customer_whatsapp')
                        ->label('واتساب العميل')
                        ->icon('heroicon-m-chat-bubble-left-ellipsis')
                        ->state(fn (?Model $record): ?string => $record?->customer?->phone_e164 ? 'واتساب' : null)
                        ->placeholder('—')
                        ->url(function (?Model $record): ?string {
                            $phone = $record?->customer?->phone_e164;
                            if (blank($phone)) {
                                return null;
                            }
                            $digits = preg_replace('/\D+/', '', $phone);

                            return "https://wa.me/{$digits}";
                        })
                        ->openUrlInNewTab()
                        ->badge()
                        ->color('success')
                        ->visible(fn (?Model $record): bool => filled($record?->customer?->phone_e164)),
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
                    TextEntry::make('broker.phone_e164')
                        ->label('هاتف الوسيط')
                        ->icon('heroicon-m-phone')
                        ->placeholder('—')
                        ->copyable()
                        ->copyMessage('تم نسخ رقم هاتف الوسيط')
                        ->url(fn (?Model $record): ?string => $record?->broker?->phone_e164 ? "tel:{$record->broker->phone_e164}" : null)
                        ->extraAttributes(['class' => 'font-mono text-primary-600 dark:text-primary-400'])
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
                        ->label('توضيح مصدر الوصول')
                        ->placeholder('—')
                        ->visible(fn (?Model $record): bool => filled($record?->lead_source_other)),
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
                        ->label('مستوى التشطيب')
                        ->badge()
                        ->color('gray')
                        ->formatStateUsing(fn ($state): string => self::finishingOptions()[self::enumValue($state) ?? ''] ?? '—'),
                    TextEntry::make('bedrooms')
                        ->label('غرف النوم')
                        ->icon('heroicon-m-building-office')
                        ->placeholder('—'),
                    TextEntry::make('occupants')
                        ->label('الأفراد')
                        ->icon('heroicon-m-user-group')
                        ->placeholder('—'),
                    TextEntry::make('household')
                        ->label('طبيعة الساكنين')
                        ->badge()
                        ->color(fn ($state): string => $state === null ? 'gray' : ($state ? 'info' : 'warning'))
                        ->formatStateUsing(fn ($state): string => $state === null ? '—' : ($state ? 'أسرة' : 'أفراد')),
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
                ])
                ->columns(['default' => 1, 'sm' => 2, 'md' => 3]);
        }

        $mainComponents[] = Section::make('الميزانية')
            ->icon('heroicon-o-banknotes')
            ->schema([
                TextEntry::make('budget_declared')
                    ->label($department === Department::Rental ? 'الميزانية المعلنة (شهرياً)' : 'الميزانية المعلنة (إجمالي الشراء)')
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
                    ->label($department === Department::Rental ? 'الميزانية المستهدفة (شهرياً)' : 'الميزانية المستهدفة (إجمالي الشراء)')
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
                    ->label('متطلبات إضافية')
                    ->placeholder('لا توجد متطلبات إضافية مسجلة')
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
                    ->label('سبب الإغلاق')
                    ->placeholder('—')
                    ->columnSpanFull(),
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
                        ->formatStateUsing(function ($state, ?Model $record): string {
                            if (! $state) {
                                return 'غير محدد';
                            }
                            $date = Carbon::parse($state);
                            $formatted = $date->format('d/m/Y H:i');
                            if ($record?->status === RequestStatus::Active) {
                                if ($date->isPast()) {
                                    return "{$formatted} (متأخرة)";
                                }
                                if ($date->isToday()) {
                                    return "{$formatted} (اليوم)";
                                }
                            }

                            return $formatted;
                        }),
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
                        ->dateTime('d/m/Y H:i'),
                    TextEntry::make('updated_at')
                        ->label('آخر تحديث')
                        ->icon('heroicon-m-clock')
                        ->dateTime('d/m/Y H:i'),
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
