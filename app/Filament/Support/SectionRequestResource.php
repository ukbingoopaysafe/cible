<?php

namespace App\Filament\Support;

use App\Enums\ContactActivityChannel;
use App\Enums\Department;
use App\Enums\RentalRequestStage;
use App\Enums\RequestOutcome;
use App\Enums\RequestStatus;
use App\Enums\ResaleRequestStage;
use App\Exports\RentalRequestExporter;
use App\Exports\ResaleRequestExporter;
use App\Models\RentalCustomer;
use App\Models\RentalDeal;
use App\Models\RentalRecommendation;
use App\Models\RentalRequestCloseReason;
use App\Models\RentalRequestReassignment;
use App\Models\RentalViewing;
use App\Models\ResaleCustomer;
use App\Models\ResaleDeal;
use App\Models\ResaleRecommendation;
use App\Models\ResaleRequestCloseReason;
use App\Models\ResaleRequestReassignment;
use App\Models\ResaleViewing;
use App\Models\User;
use App\Services\ContactActivityService;
use App\Services\DealService;
use App\Services\RecommendationService;
use App\Services\RequestService;
use App\Support\DateTimeLocale;
use App\Support\DateTimePresenter;
use App\Support\RecordCode;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\EditAction;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Hidden;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\HtmlString;

/** @extends resource<Model> */
abstract class SectionRequestResource extends Resource
{
    abstract public static function department(): Department;

    public static function form(Schema $schema): Schema
    {
        return RequestFormSchema::configure($schema, static::department());
    }

    public static function editForm(Schema $schema): Schema
    {
        return RequestFormSchema::configure($schema, static::department(), editing: true);
    }

    public static function infolist(Schema $schema): Schema
    {
        return RequestFormSchema::details($schema, static::department());
    }

    public static function getEloquentQuery(): Builder
    {
        $query = parent::getEloquentQuery();
        $actor = auth()->user();

        if (! $actor instanceof User || ! $actor->canWorkInDepartment(static::department())) {
            return $query->whereRaw('1 = 0');
        }

        return $query->with(['customer.phoneNumbers', 'broker.phoneNumbers', 'area', 'owner', 'leadSource', 'closeReason', 'deal.commissions'])
            ->withCount('activities')
            ->withCount(['recommendations', 'viewings'])
            ->withCount(['reassignments as pending_reassignment_count' => fn (Builder $relationQuery) => $relationQuery->where('status', 'pending')]);
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canWorkInDepartment(static::department()) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canView(Model $record): bool
    {
        $modelClass = static::getModel();

        return static::canViewAny() && $record instanceof $modelClass;
    }

    public static function canEdit(Model $record): bool
    {
        $modelClass = static::getModel();

        return static::canViewAny() && $record instanceof $modelClass;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getRecordTitle(?Model $record): string|null|Htmlable
    {
        if (! $record) {
            return null;
        }

        $code = RecordCode::request(static::department(), $record->id);
        $prefix = static::department() === Department::Rental ? 'طلب إيجار' : 'طلب إعادة بيع';

        return "{$prefix} {$code}";
    }

    public static function getStatusColor(RequestStatus|string|null $status): string
    {
        $value = $status instanceof RequestStatus ? $status : RequestStatus::tryFrom((string) $status);

        return match ($value) {
            RequestStatus::Active => 'success',
            RequestStatus::Paused => 'warning',
            RequestStatus::Closed => 'gray',
            default => 'gray',
        };
    }

    public static function getStageColor(RentalRequestStage|ResaleRequestStage|string|null $stage): string
    {
        $value = $stage instanceof \BackedEnum ? $stage->value : (string) $stage;

        return match ($value) {
            'intake', 'استقبال' => 'gray',
            'qualification', 'تأهيل' => 'info',
            'unit_search', 'بحث عن وحدات' => 'primary',
            'recommendation', 'ترشيح', 'عرض / ترشيح' => 'primary',
            'viewing', 'معاينة' => 'warning',
            'negotiation', 'تفاوض' => 'success',
            'contract_signed', 'signed', 'contract', 'تم توقيع العقد' => 'success',
            'cancelled', 'canceled', 'ملغي', 'إلغاء' => 'gray',
            default => 'gray',
        };
    }

    public static function getFollowUpColor(Model $record): string
    {
        if (! $record->next_follow_up_at || $record->status !== RequestStatus::Active) {
            return 'gray';
        }

        if ($record->next_follow_up_at->lt(now())) {
            return 'danger';
        }

        if ($record->next_follow_up_at->lte(now()->endOfDay())) {
            return 'warning';
        }

        return 'gray';
    }

    public static function getFollowUpIcon(Model $record): ?string
    {
        if (! $record->next_follow_up_at || $record->status !== RequestStatus::Active) {
            return null;
        }

        if ($record->next_follow_up_at->lt(now())) {
            return 'heroicon-m-exclamation-triangle';
        }

        if ($record->next_follow_up_at->lte(now()->endOfDay())) {
            return 'heroicon-m-clock';
        }

        return null;
    }

    public static function getFollowUpTooltip(Model $record): ?string
    {
        if (! $record->next_follow_up_at) {
            return null;
        }

        if ($record->status !== RequestStatus::Active) {
            return null;
        }

        if ($record->next_follow_up_at->lt(now())) {
            return 'موعد المتابعة متأخر';
        }

        if ($record->next_follow_up_at->lte(now()->endOfDay())) {
            return 'متابعة مستحقة اليوم';
        }

        return 'متابعة قادمة';
    }

    public static function formatPhoneForDisplay(?string $phone): ?Htmlable
    {
        if (blank($phone)) {
            return null;
        }

        return new HtmlString('<span dir="ltr" class="inline-block" style="unicode-bidi: isolate; direction: ltr;">'.e($phone).'</span>');
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchPlaceholder('بحث بالكود، العميل، الهاتف، أو المسؤول...')
            ->headerActions([
                ExportAction::make()
                    ->label('تصدير')
                    ->exporter(static::department() === Department::Rental ? RentalRequestExporter::class : ResaleRequestExporter::class)
                    ->formats([ExportFormat::Xlsx])
                    ->columnMapping(false)
                    ->options(fn (): array => ['cible_datetime_locale' => app(DateTimeLocale::class)->getRegionalTag()]),
            ])
            ->columns([
                TextColumn::make('code')
                    ->label('الكود')
                    ->state(fn (Model $record): ?string => RecordCode::request(static::department(), $record->id))
                    ->extraHeaderAttributes(['class' => 'whitespace-nowrap'])
                    ->extraCellAttributes(['class' => 'record-code-cell whitespace-nowrap'])
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('id', $direction))
                    ->searchable(query: fn (Builder $query, string $search): Builder => RecordCode::applySearch($query, $search, static::department(), RecordCode::TYPE_REQUEST)),
                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->placeholder('غير معروف')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->whereHas('customer', function (Builder $q) use ($search): void {
                            $q->where('name', 'like', "%{$search}%")
                                ->orWhereHas('phoneNumbers', fn (Builder $phones): Builder => $phones->where('phone_e164', 'like', "%{$search}%"));
                        });
                    })
                    ->toggleable(),
                TextColumn::make('customer_primary_phone')
                    ->label('هاتف العميل')
                    ->state(fn (Model $record): ?string => $record->customer?->primaryPhone()?->phone_e164)
                    ->formatStateUsing(fn (?string $state): ?Htmlable => static::formatPhoneForDisplay($state))
                    ->placeholder('—')
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'customer.phoneNumbers',
                        fn (Builder $phones): Builder => $phones->where('phone_e164', 'like', "%{$search}%"),
                    ))
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('broker.name')
                    ->label('الوسيط')
                    ->state(fn (Model $record): ?string => $record->broker?->name ?? $record->broker_name_snapshot)
                    ->description(fn (Model $record): ?Htmlable => static::formatPhoneForDisplay(
                        $record->broker
                            ? $record->broker->primaryPhone()?->phone_e164
                            : $record->broker_phone_snapshot,
                    ))
                    ->placeholder('—')
                    ->searchable(query: function (Builder $query, string $search): Builder {
                        return $query->where(function (Builder $query) use ($search): void {
                            $query->whereHas('broker', function (Builder $broker) use ($search): void {
                                $broker->where('name', 'like', "%{$search}%")
                                    ->orWhereHas('phoneNumbers', fn (Builder $phones): Builder => $phones->where('phone_e164', 'like', "%{$search}%"));
                            })
                                ->orWhere('broker_name_snapshot', 'like', "%{$search}%")
                                ->orWhere('broker_phone_numbers_snapshot', 'like', "%{$search}%");
                        });
                    })
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('contact_phone_numbers')
                    ->label('أرقام الاتصال')
                    ->state(fn (Model $record): ?string => static::hasContactPhoneNumbers($record) ? 'عرض الأرقام' : null)
                    ->placeholder('—')
                    ->icon('heroicon-o-phone')
                    ->color('warning')
                    ->tooltip('عرض أرقام العميل والوسيط')
                    ->action(static::contactPhoneNumbersAction())
                    ->disabledClick(fn (Model $record): bool => ! static::hasContactPhoneNumbers($record))
                    ->toggleable(),
                TextColumn::make('area.name')->label('المنطقة')->description(fn (Model $record): string => $record->area?->governorate ?? '')->toggleable(),
                TextColumn::make('owner.name')->label('المسؤول')
                    ->searchable()
                    ->icon(fn (Model $record): ?string => (int) $record->pending_reassignment_count > 0 ? 'heroicon-m-arrows-right-left' : null)
                    ->iconColor(fn (Model $record): ?string => (int) $record->pending_reassignment_count > 0 ? 'warning' : null)
                    ->tooltip(fn (Model $record): ?string => (int) $record->pending_reassignment_count > 0 ? 'يوجد طلب نقل مسؤولية معلق' : null)
                    ->description(fn (Model $record): ?string => (int) $record->pending_reassignment_count > 0 ? 'طلب نقل معلق' : null)
                    ->toggleable(),
                TextColumn::make('pending_reassignment_count')->label('طلب تغيير المسؤول')
                    ->formatStateUsing(fn ($state): string => (int) $state > 0 ? 'بانتظار المدير' : '—')
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'warning' : 'gray')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('stage')
                    ->label('المرحلة')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => RequestFormSchema::stageOptions(static::department())[$state instanceof \BackedEnum ? $state->value : $state] ?? (string) $state)
                    ->color(fn ($state): string => static::getStageColor($state))
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->badge()
                    ->formatStateUsing(fn ($state): string => match ($state instanceof RequestStatus ? $state : RequestStatus::from($state)) {
                        RequestStatus::Active => 'نشط',
                        RequestStatus::Paused => 'متوقف مؤقتًا',
                        RequestStatus::Closed => 'مغلق',
                    })
                    ->color(fn ($state): string => static::getStatusColor($state))
                    ->toggleable(),
                TextColumn::make('next_follow_up_at')
                    ->label('المتابعة القادمة')
                    ->formatStateUsing(fn ($state) => $state ? app(DateTimePresenter::class)->bidi(
                        app(DateTimePresenter::class)->formatDateTime($state),
                    ) : null)
                    ->html()
                    ->placeholder('—')
                    ->badge()
                    ->color(fn (Model $record): string => static::getFollowUpColor($record))
                    ->icon(fn (Model $record): ?string => static::getFollowUpIcon($record))
                    ->tooltip(fn (Model $record): ?string => static::getFollowUpTooltip($record))
                    ->sortable()
                    ->toggleable(),
                TextColumn::make('activities_count')->label('التواصل')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('recommendations_count')->label('الترشيحات')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('viewings_count')->label('المعاينات')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deal.agreed_total_value')->label('الصفقة')->money(fn (Model $record): string => $record->deal?->currency ?? 'EGP')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options([
                    RequestStatus::Active->value => 'نشط',
                    RequestStatus::Paused->value => 'متوقف مؤقتًا',
                    RequestStatus::Closed->value => 'مغلق',
                ]),
                SelectFilter::make('customer_link')
                    ->label('العميل')
                    ->options([
                        'linked' => 'مرتبط',
                        'unlinked' => 'غير مرتبط',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'linked' => $query->whereNotNull('customer_id'),
                        'unlinked' => $query->whereNull('customer_id'),
                        default => $query,
                    }),
                SelectFilter::make('owner_id')
                    ->label('المسؤول')
                    ->attribute('owner_user_id')
                    ->options(fn (): array => User::query()
                        ->whereHas('departmentMemberships', fn (Builder $query) => $query->where('department', static::department()->value))
                        ->orderBy('name')
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
                SelectFilter::make('stage')->label('المرحلة')->options(RequestFormSchema::stageOptions(static::department())),
                SelectFilter::make('follow_up_window')
                    ->label('موعد المتابعة')
                    ->options([
                        'overdue' => 'متأخرة',
                        'today' => 'اليوم',
                        'next_7_days' => 'خلال 7 أيام',
                        'later' => 'بعد 7 أيام',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $window = $data['value'] ?? null;
                        if (blank($window)) {
                            return $query;
                        }

                        $current = now();
                        $todayEnd = $current->copy()->endOfDay();
                        $tomorrow = $current->copy()->addDay()->startOfDay();
                        $weekEnd = $current->copy()->addDays(7)->endOfDay();

                        return match ($window) {
                            'overdue' => $query->where('status', RequestStatus::Active->value)
                                ->where('next_follow_up_at', '<', $current),
                            'today' => $query->where('status', RequestStatus::Active->value)
                                ->whereBetween('next_follow_up_at', [$current, $todayEnd]),
                            'next_7_days' => $query->where('status', RequestStatus::Active->value)
                                ->whereBetween('next_follow_up_at', [$tomorrow, $weekEnd]),
                            'later' => $query->where('status', RequestStatus::Active->value)
                                ->where('next_follow_up_at', '>', $weekEnd),
                            default => $query,
                        };
                    }),
            ])
            ->filtersFormMaxHeight('26rem')
            ->columnManagerMaxHeight('24rem')
            ->recordActions([
                ViewAction::make()->iconButton()->tooltip('عرض الطلب'),
                static::recordContactAction()->iconButton()->tooltip('تسجيل تواصل'),
                static::getRequestActionsGroup(),
            ])
            ->defaultSort('next_follow_up_at');
    }

    private static function hasContactPhoneNumbers(Model $record): bool
    {
        return $record->customer?->phoneNumbers?->isNotEmpty()
            || $record->broker?->phoneNumbers?->isNotEmpty()
            || filled($record->broker_phone_snapshot)
            || count($record->broker_phone_numbers_snapshot ?? []) > 0;
    }

    private static function contactPhoneNumbersAction(): Action
    {
        return Action::make('viewContactPhoneNumbers')
            ->label('أرقام الاتصال')
            ->modalHeading('أرقام الاتصال')
            ->modalContent(function (Model $record) {
                $brokerSnapshot = $record->broker_phone_numbers_snapshot ?? [];
                if ($brokerSnapshot === [] && filled($record->broker_phone_snapshot)) {
                    $brokerSnapshot = [[
                        'phone_e164' => $record->broker_phone_snapshot,
                        'phone_country' => null,
                        'is_primary' => true,
                    ]];
                }

                return view('filament.modals.request-contact-phone-numbers', [
                    'sections' => [
                        [
                            'label' => 'العميل — '.($record->customer?->name ?? 'غير معروف'),
                            'phones' => $record->customer?->phoneNumbers ?? collect(),
                        ],
                        [
                            'label' => 'الوسيط — '.($record->broker?->name ?? $record->broker_name_snapshot ?? 'غير معروف'),
                            'phones' => $record->broker?->phoneNumbers ?? collect($brokerSnapshot),
                        ],
                    ],
                ]);
            })
            ->visible(fn (Model $record): bool => static::hasContactPhoneNumbers($record))
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->action(fn () => null);
    }

    public static function getRequestActionsGroup(): ActionGroup
    {
        $categoryGroups = [
            'customer' => ActionGroup::make([
                EditAction::make()->label('تعديل الطلب'),
                static::linkCustomerAction(),
                static::setFollowUpAction(),
                static::contactHistoryAction(),
                static::changeStageAction(),
            ])
                ->label('العميل والمتابعة')
                ->icon('heroicon-o-user')
                ->dropdown(false),

            'reassignment' => ActionGroup::make([
                static::requestReassignmentAction(),
                static::directReassignmentAction(),
                static::approveReassignmentAction(),
                static::rejectReassignmentAction(),
                static::cancelReassignmentAction(),
            ])
                ->label('نقل المسؤولية')
                ->icon('heroicon-o-arrows-right-left')
                ->dropdown(false),

            'recommendations' => ActionGroup::make([
                static::createRecommendationAction(),
                static::changeRecommendationStatusAction(),
                static::scheduleViewingAction(),
                static::recordViewingResultAction(),
                static::recordFieldInspectionAction(),
                static::viewPropertyHistoryAction(),
            ])
                ->label('الترشيحات والمعاينات')
                ->icon('heroicon-o-home-modern')
                ->dropdown(false),

            'deal' => ActionGroup::make([
                static::createDealAction(),
                static::viewDealAction(),
                static::pauseAction(),
                static::resumeAction(),
                static::closeAction(),
            ])
                ->label('الصفقة والإغلاق')
                ->icon('heroicon-o-check-circle')
                ->dropdown(false),
        ];

        // The admin theme uses this attribute to attach the category heading to
        // its actions, so headings stay correct when a whole category is hidden.
        foreach ($categoryGroups as $category => $categoryGroup) {
            foreach ($categoryGroup->getActions() as $action) {
                $action->extraAttributes([
                    'data-action-category' => $category,
                ]);
            }
        }

        return ActionGroup::make(array_values($categoryGroups))
            ->label('الإجراءات')
            ->icon('heroicon-m-ellipsis-vertical')
            ->iconButton()
            ->color('gray')
            ->tooltip('خيارات وإجراءات الطلب')
            ->dropdownMaxHeight('22rem')
            ->dropdownTeleport()
            ->dropdownFlip()
            ->extraDropdownAttributes(['class' => 'fi-request-actions-dropdown']);
    }

    public static function linkCustomerAction(): Action
    {
        $customerClass = static::department() === Department::Rental ? RentalCustomer::class : ResaleCustomer::class;

        return Action::make('linkCustomer')
            ->label('ربط عميل')
            ->icon('heroicon-o-user-plus')
            ->schema([
                Select::make('customer_id')
                    ->label('العميل')
                    ->options(fn (): array => $customerClass::query()->orderBy('name')->pluck('name', 'id')->all())
                    ->searchable()
                    ->required(),
                Toggle::make('make_contact')->label('تعيين كجهة اتصال رئيسية'),
            ])
            ->visible(fn (Model $record): bool => blank($record->customer_id))
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->linkCustomer(
                    $actor,
                    static::department(),
                    (int) $record->getKey(),
                    (int) $data['customer_id'],
                    (bool) ($data['make_contact'] ?? false),
                );
                $record->refresh();
            })
            ->successNotificationTitle('تم ربط العميل بالطلب');
    }

    public static function createRecommendationAction(): Action
    {
        return Action::make('createRecommendation')
            ->label('إضافة ترشيح')
            ->icon('heroicon-o-home-modern')
            ->visible(fn (Model $record): bool => $record->status === RequestStatus::Active)
            ->schema([
                TextInput::make('unit_code')->label('كود الوحدة')->maxLength(100),
                TextInput::make('listing_code')->label('كود القائمة (Listing)')->numeric()->maxLength(30),
                TextInput::make('unit_type')->label('نوع الوحدة')->maxLength(80),
                TextInput::make('unit_area')->label('المساحة (م²)')->numeric()->minValue(0.01),
                Textarea::make('location_description')->label('وصف الموقع')->rows(2)->maxLength(10000),
                TextInput::make('asking_price')->label('السعر المعروض')->numeric()->minValue(0),
                Select::make('currency')->label('العملة')->options(['EGP' => 'جنيه مصري', 'USD' => 'دولار أمريكي'])->default('EGP')->required(),
                Textarea::make('notes')->label('ملاحظات الترشيح')->rows(3)->maxLength(10000),
            ])
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RecommendationService::class)->createRecommendation($actor, static::department(), (int) $record->getKey(), $data);
                $record->refresh();
            })
            ->successNotificationTitle('تمت إضافة الترشيح');
    }

    public static function changeRecommendationStatusAction(): Action
    {
        return Action::make('changeRecommendationStatus')
            ->label('تحديث حالة ترشيح')
            ->icon('heroicon-o-arrow-path')
            ->visible(fn (Model $record): bool => $record->status === RequestStatus::Active && $record->recommendations()->exists())
            ->schema([
                Select::make('recommendation_id')->label('الوحدة المرشحة')->options(fn (Model $record): array => $record->recommendations()->orderByDesc('id')->get()->mapWithKeys(fn ($item): array => [$item->id => trim(($item->unit_code ?: 'وحدة').' — '.($item->unit_type ?: 'بدون نوع').' — '.$item->getKey())])->all())->searchable()->required(),
                Select::make('status')->label('الحالة')->options(['proposed' => 'مقترحة', 'shared' => 'أُرسلت للعميل', 'selected' => 'اختارها العميل', 'rejected' => 'رفضها العميل', 'withdrawn' => 'سُحبت'])->required(),
            ])
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                $recommendationClass = static::department() === Department::Rental ? RentalRecommendation::class : ResaleRecommendation::class;
                abort_unless($recommendationClass::query()->whereKey($data['recommendation_id'])->where('request_id', $record->getKey())->exists(), 404);
                app(RecommendationService::class)->updateRecommendationStatus($actor, static::department(), (int) $data['recommendation_id'], $data['status']);
                $record->refresh();
            })
            ->successNotificationTitle('تم تحديث حالة الترشيح');
    }

    public static function recordFieldInspectionAction(): Action
    {
        return Action::make('recordFieldInspection')
            ->label('فحص الشقة')
            ->modalHeading('فحص الشقة')
            ->icon('heroicon-o-clipboard-document-check')
            ->visible(fn (Model $record): bool => $record->status === RequestStatus::Active && $record->recommendations()->exists())
            ->schema([
                Select::make('recommendation_id')->label('الوحدة')->options(fn (Model $record): array => $record->recommendations()->orderByDesc('id')->get()->mapWithKeys(fn ($item): array => [$item->id => trim(($item->unit_code ?: 'وحدة').' — '.($item->unit_type ?: 'بدون نوع').' — '.$item->getKey())])->all())->searchable()->required(),
                TextInput::make('field_representative_name')->label('المندوب الميداني')->maxLength(160)->required(),
                DateTimePicker::make('inspected_at')->label('وقت الفحص')->default(now())->native(false)->seconds(false)->maxDate(now())->required(),
                Select::make('photo_match')->label('مدى مطابقة الوحدة للصور')->options(['matches' => 'مطابقة', 'partly_matches' => 'مطابقة جزئيًا', 'does_not_match' => 'غير مطابقة'])->required(),
                Select::make('contact_role')
                    ->label('صفة جهة التواصل')
                    ->options(['owner' => 'مالك الوحدة', 'broker' => 'وسيط'])
                    ->live()
                    ->required(),
                TextInput::make('contact_name')->label('اسم جهة التواصل')->maxLength(255)->required(),
                PhoneField::make('رقم هاتف جهة التواصل', required: true, phoneField: 'phone', countryField: 'phone_country'),
                Placeholder::make('request_broker_identity')
                    ->label('وسيط الطلب')
                    ->content(fn (Model $record): string => $record->broker?->name ?? 'لا يوجد وسيط مرتبط بالطلب')
                    ->visible(fn (Get $get, Model $record): bool => $get('contact_role') === 'broker' && filled($record->broker_id)),
                Toggle::make('link_request_broker')
                    ->label('ربط بوسيط الطلب')
                    ->default(false)
                    ->visible(fn (Get $get, Model $record): bool => $get('contact_role') === 'broker' && filled($record->broker_id)),
                Hidden::make('shown_request_broker_id')
                    ->default(fn (Model $record): ?int => $record->broker_id)
                    ->dehydrated(),
                Textarea::make('notes')->label('ملاحظات الفحص')->rows(2)->maxLength(10000),
            ])
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                $recommendationClass = static::department() === Department::Rental ? RentalRecommendation::class : ResaleRecommendation::class;
                abort_unless($recommendationClass::query()->whereKey($data['recommendation_id'])->where('request_id', $record->getKey())->exists(), 404);
                app(RecommendationService::class)->recordInspection($actor, static::department(), (int) $data['recommendation_id'], $data);
                $record->refresh();
            })
            ->successNotificationTitle('تم تسجيل فحص الشقة');
    }

    public static function scheduleViewingAction(): Action
    {
        return Action::make('scheduleViewing')
            ->label('تحديد معاينة')
            ->icon('heroicon-o-calendar-days')
            ->visible(fn (Model $record): bool => $record->status === RequestStatus::Active)
            ->schema([
                Select::make('recommendation_id')->label('الوحدة المرشحة')->options(fn (Model $record): array => $record->recommendations()->orderByDesc('id')->get()->mapWithKeys(fn ($item): array => [$item->id => trim(($item->unit_code ?: 'وحدة').' — '.($item->unit_type ?: 'بدون نوع').' — '.$item->getKey())])->all())->searchable(),
                DateTimePicker::make('scheduled_at')->label('موعد المعاينة')->native(false)->seconds(false)->minDate(now()->addMinute())->required(),
                TextInput::make('field_representative_name')->label('المندوب الميداني')->maxLength(160),
            ])
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RecommendationService::class)->scheduleViewing($actor, static::department(), (int) $record->getKey(), $data);
                $record->refresh();
            })
            ->successNotificationTitle('تم تحديد موعد المعاينة');
    }

    public static function recordViewingResultAction(): Action
    {
        return Action::make('recordViewingResult')
            ->label('تسجيل نتيجة معاينة')
            ->icon('heroicon-o-chat-bubble-left-right')
            ->visible(fn (Model $record): bool => $record->status === RequestStatus::Active && $record->viewings()->exists())
            ->schema([
                Select::make('viewing_id')->label('المعاينة')->options(fn (Model $record): array => $record->viewings()->orderByDesc('scheduled_at')->get()->mapWithKeys(fn ($item): array => [$item->id => app(DateTimePresenter::class)->bidiText(app(DateTimePresenter::class)->formatDateTime($item->scheduled_at)).' — '.($item->field_representative_name ?: 'دون مندوب مسجل')])->all())->searchable()->required(),
                Select::make('result')->label('النتيجة')->options(static::department() === Department::Rental
                    ? [
                        'reserved' => 'حجز',
                        'unsuitable' => 'غير مناسبة',
                        'other_apartment_viewing' => 'يرغب بمعاينة شقة أخرى',
                        'rented_same_unit' => 'تم التأجير',
                        'awaiting_customer_reply' => 'انتظار الرد',
                    ]
                    : [
                        'interested' => 'مهتم',
                        'not_interested' => 'غير مهتم',
                        'second_viewing' => 'يرغب بمعاينة أخرى',
                        'negotiating' => 'بدأ التفاوض',
                        'cancelled' => 'أُلغيت',
                    ])->live()->required(),
                Textarea::make('customer_feedback')->label('رأي العميل')->rows(3)->maxLength(10000),
                Textarea::make('rejection_reason')
                    ->label(static::department() === Department::Rental ? 'سبب عدم المناسبة' : 'سبب عدم الاهتمام')
                    ->visible(fn (Get $get): bool => static::department() !== Department::Rental || $get('result') === 'unsuitable')
                    ->required(fn (Get $get): bool => $get('result') === (static::department() === Department::Rental ? 'unsuitable' : 'not_interested'))
                    ->rows(2)
                    ->maxLength(10000),
            ])
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                $viewingClass = static::department() === Department::Rental ? RentalViewing::class : ResaleViewing::class;
                abort_unless($viewingClass::query()->whereKey($data['viewing_id'])->where('request_id', $record->getKey())->exists(), 404);
                app(RecommendationService::class)->recordViewingResult($actor, static::department(), (int) $data['viewing_id'], $data);
                $record->refresh();
            })
            ->successNotificationTitle('تم تسجيل نتيجة المعاينة');
    }

    public static function viewPropertyHistoryAction(): Action
    {
        return Action::make('viewPropertyHistory')
            ->label('سجل الترشيحات والمعاينات')
            ->icon('heroicon-o-clipboard-document-list')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalContent(fn (Model $record) => view('filament.modals.request-property-history', ['record' => $record]));
    }

    public static function changeStageAction(): Action
    {
        return Action::make('changeStage')
            ->label('تغيير المرحلة')
            ->icon('heroicon-o-arrow-path')
            ->schema([
                Select::make('stage')->label('المرحلة الجديدة')->options(RequestFormSchema::stageOptions(static::department()))->required(),
                Textarea::make('notes')->label('ملاحظات')->rows(2),
            ])
            ->visible(fn (Model $record): bool => $record->status === RequestStatus::Active)
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->changeStage($actor, static::department(), (int) $record->getKey(), $data['stage'], $data['notes'] ?? null);
                $record->refresh();
            })
            ->successNotificationTitle('تم تحديث مرحلة الطلب');
    }

    public static function setFollowUpAction(): Action
    {
        return Action::make('setFollowUp')
            ->label('تحديد المتابعة')
            ->icon('heroicon-o-calendar-days')
            ->schema([
                DateTimePicker::make('next_follow_up_at')
                    ->label('موعد المتابعة القادمة')
                    ->native(false)
                    ->seconds(false)
                    ->minDate(now()->addMinute())
                    ->required(),
            ])
            ->visible(fn (Model $record): bool => $record->status === RequestStatus::Active)
            ->fillForm(fn (Model $record): array => ['next_follow_up_at' => $record->next_follow_up_at])
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->setNextFollowUp(
                    $actor,
                    static::department(),
                    (int) $record->getKey(),
                    $data['next_follow_up_at'],
                );
                $record->refresh();
            })
            ->successNotificationTitle('تم تحديث موعد المتابعة');
    }

    public static function recordContactAction(): Action
    {
        return Action::make('recordContact')
            ->label('تسجيل تواصل')
            ->icon('heroicon-o-chat-bubble-left-ellipsis')
            ->visible(fn (Model $record): bool => $record->status !== RequestStatus::Closed)
            ->schema([
                Select::make('channel')
                    ->label('وسيلة التواصل')
                    ->options(collect(ContactActivityChannel::cases())->mapWithKeys(
                        fn (ContactActivityChannel $channel): array => [$channel->value => $channel->label()],
                    )->all())
                    ->required(),
                DateTimePicker::make('occurred_at')
                    ->label('وقت التواصل')
                    ->default(now())
                    ->native(false)
                    ->seconds(false)
                    ->maxDate(now())
                    ->required(),
                Textarea::make('result')
                    ->label('نتيجة التواصل')
                    ->rows(3)
                    ->maxLength(10000)
                    ->required(),
                Textarea::make('notes')
                    ->label('ملاحظات إضافية')
                    ->rows(3)
                    ->maxLength(10000),
                Textarea::make('next_action')
                    ->label('الإجراء التالي')
                    ->helperText('لتغيير موعد المتابعة، استخدم إجراء تحديد المتابعة.')
                    ->rows(2)
                    ->maxLength(10000),
            ])
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(ContactActivityService::class)->record(
                    $actor,
                    static::department(),
                    (int) $record->getKey(),
                    $data,
                );
                $record->refresh();
            })
            ->successNotificationTitle('تم تسجيل التواصل');
    }

    public static function contactHistoryAction(): Action
    {
        return Action::make('contactHistory')
            ->label('عرض سجل التواصل')
            ->icon('heroicon-o-clock')
            ->modalHeading('سجل التواصل')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('إغلاق')
            ->modalContent(fn (Model $record) => view('filament.modals.contact-activity-history', [
                'activities' => app(ContactActivityService::class)->history(
                    auth()->user(),
                    static::department(),
                    (int) $record->getKey(),
                ),
            ]));
    }

    public static function requestReassignmentAction(): Action
    {
        return Action::make('requestReassignment')
            ->label('طلب تغيير المسؤول')
            ->icon('heroicon-o-user-plus')
            ->schema([
                Select::make('target_user_id')->label('المسؤول المقترح')
                    ->options(fn (Model $record): array => self::reassignmentStaffOptions($record))
                    ->searchable()->required(),
                Textarea::make('reason')->label('سبب الطلب')->rows(3)->required()->maxLength(10000),
            ])
            ->visible(function (Model $record): bool {
                $actor = auth()->user();

                return $actor instanceof User
                    && $actor->canWorkInDepartment(static::department())
                    && ! $actor->canManageDepartment(static::department())
                    && $record->status !== RequestStatus::Closed
                    && (int) ($record->pending_reassignment_count ?? 0) === 0;
            })
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->requestReassignment(
                    $actor,
                    static::department(),
                    (int) $record->getKey(),
                    (int) $data['target_user_id'],
                    $data['reason'],
                );
                $record->refresh();
            })
            ->successNotificationTitle('تم إرسال الطلب للمدير');
    }

    public static function directReassignmentAction(): Action
    {
        return Action::make('directReassignment')
            ->label('إعادة تعيين المسؤول')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->schema([
                Select::make('target_user_id')->label('المسؤول الجديد')
                    ->options(fn (Model $record): array => self::reassignmentStaffOptions($record))
                    ->searchable()->required(),
                Textarea::make('reason')->label('السبب')->rows(3)->required()->maxLength(10000),
            ])
            ->visible(function (Model $record): bool {
                $actor = auth()->user();

                return $actor instanceof User
                    && $actor->canManageDepartment(static::department())
                    && $record->status !== RequestStatus::Closed
                    && (int) ($record->pending_reassignment_count ?? 0) === 0;
            })
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->reassignDirectly(
                    $actor,
                    static::department(),
                    (int) $record->getKey(),
                    (int) $data['target_user_id'],
                    $data['reason'],
                );
                $record->refresh();
            })
            ->successNotificationTitle('تمت إعادة تعيين مسؤول الطلب');
    }

    public static function cancelReassignmentAction(): Action
    {
        return Action::make('cancelReassignment')
            ->label('إلغاء طلب تغيير المسؤول')
            ->icon('heroicon-o-x-circle')
            ->color('gray')
            ->requiresConfirmation()
            ->modalDescription('سيُلغى الطلب المعلق ويظل المسؤول الحالي كما هو.')
            ->visible(function (Model $record): bool {
                $actor = auth()->user();
                $pending = self::pendingReassignment((int) $record->getKey());

                return $actor instanceof User
                    && $actor->canWorkInDepartment(static::department())
                    && ! $actor->canManageDepartment(static::department())
                    && $pending !== null
                    && (int) $pending->requested_by === (int) $actor->id;
            })
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->cancelReassignment($actor, static::department(), (int) $record->getKey());
                $record->refresh();
            })
            ->successNotificationTitle('تم إلغاء طلب إعادة التعيين');
    }

    public static function approveReassignmentAction(): Action
    {
        return Action::make('approveReassignment')
            ->label('الموافقة على تغيير المسؤول')
            ->color('success')
            ->requiresConfirmation()
            ->modalDescription(fn (Model $record): string => self::pendingReassignmentDescription((int) $record->getKey()))
            ->visible(function (Model $record): bool {
                $actor = auth()->user();

                return $actor instanceof User
                    && $actor->canManageDepartment(static::department())
                    && self::hasPendingReassignment($record);
            })
            ->action(function (Model $record): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->decideReassignment($actor, static::department(), (int) $record->getKey(), true);
                $record->refresh();
            })
            ->successNotificationTitle('تمت الموافقة على إعادة التعيين');
    }

    public static function rejectReassignmentAction(): Action
    {
        return Action::make('rejectReassignment')
            ->label('رفض طلب تغيير المسؤول')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription(fn (Model $record): string => self::pendingReassignmentDescription((int) $record->getKey()))
            ->visible(function (Model $record): bool {
                $actor = auth()->user();

                return $actor instanceof User
                    && $actor->canManageDepartment(static::department())
                    && self::hasPendingReassignment($record);
            })
            ->action(function (Model $record): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->decideReassignment($actor, static::department(), (int) $record->getKey(), false);
                $record->refresh();
            })
            ->successNotificationTitle('تم رفض طلب إعادة التعيين');
    }

    /** @return array<int, string> */
    private static function reassignmentStaffOptions(Model $record): array
    {
        $actor = auth()->user();

        return User::query()
            ->where('is_company_manager', false)
            ->whereKeyNot((int) $record->owner_user_id)
            ->when($actor instanceof User, fn ($query) => $query->whereKeyNot((int) $actor->id))
            ->whereHas('departmentMemberships', fn ($query) => $query->where('department', static::department()->value))
            ->orderBy('name')
            ->pluck('name', 'id')
            ->all();
    }

    private static function pendingReassignment(int $requestId): ?Model
    {
        $class = static::department() === Department::Rental
            ? RentalRequestReassignment::class
            : ResaleRequestReassignment::class;

        return $class::query()->with('requester', 'target')->where('request_id', $requestId)
            ->where('status', 'pending')->latest('id')->first();
    }

    private static function hasPendingReassignment(Model $record): bool
    {
        return (int) ($record->pending_reassignment_count ?? 0) > 0;
    }

    private static function pendingReassignmentDescription(int $requestId): string
    {
        $pending = self::pendingReassignment($requestId);

        return $pending
            ? "من: {$pending->requester?->name} — إلى: {$pending->target?->name}\nالسبب: {$pending->reason}"
            : 'لا يوجد طلب إعادة تعيين معلق.';
    }

    public static function pauseAction(): Action
    {
        return Action::make('pause')
            ->label('إيقاف مؤقت')
            ->icon('heroicon-o-pause-circle')
            ->requiresConfirmation()
            ->visible(fn (Model $record): bool => $record->status === RequestStatus::Active)
            ->action(function (Model $record): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->pause($actor, static::department(), (int) $record->getKey());
                $record->refresh();
            })
            ->successNotificationTitle('تم إيقاف الطلب مؤقتًا');
    }

    public static function resumeAction(): Action
    {
        return Action::make('resume')
            ->label('استئناف')
            ->icon('heroicon-o-play-circle')
            ->schema([
                DateTimePicker::make('next_follow_up_at')->label('موعد المتابعة القادمة')->native(false)->seconds(false)->minDate(now()->addMinute())->required(),
                Textarea::make('notes')->label('ملاحظات')->rows(2),
            ])
            ->visible(fn (Model $record): bool => $record->status === RequestStatus::Paused)
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->resume($actor, static::department(), (int) $record->getKey(), $data['next_follow_up_at'], $data['notes'] ?? null);
                $record->refresh();
            })
            ->successNotificationTitle('تم استئناف الطلب');
    }

    public static function closeAction(): Action
    {
        return Action::make('close')
            ->label('إغلاق الطلب')
            ->icon('heroicon-o-check-circle')
            ->color('gray')
            ->schema([
                Radio::make('outcome')
                    ->label('نتيجة الطلب')
                    ->options([
                        RequestOutcome::Successful->value => 'ناجح',
                        RequestOutcome::Unsuccessful->value => 'غير ناجح',
                    ])
                    ->live()
                    ->required(),
                Textarea::make('outcome_reason_text')
                    ->label('سبب الإغلاق')
                    ->rows(3)
                    ->dehydrated(fn (Get $get): bool => $get('outcome') === RequestOutcome::Unsuccessful->value)
                    ->visible(fn (Get $get): bool => $get('outcome') === RequestOutcome::Unsuccessful->value)
                    ->required(fn (Get $get): bool => $get('outcome') === RequestOutcome::Unsuccessful->value),
                Select::make('outcome_reason_id')
                    ->label('تصنيف الإغلاق')
                    ->options(fn (): array => self::closeReasonOptions())
                    ->dehydrated(fn (Get $get): bool => $get('outcome') === RequestOutcome::Unsuccessful->value)
                    ->visible(fn (Get $get): bool => $get('outcome') === RequestOutcome::Unsuccessful->value),
                Textarea::make('outcome_notes')->label('ملاحظات إضافية')->rows(2),
            ])
            ->visible(fn (Model $record): bool => $record->status !== RequestStatus::Closed)
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->close($actor, static::department(), (int) $record->getKey(), $data);
                $record->refresh();
            })
            ->successNotificationTitle('تم إغلاق الطلب');
    }

    public static function createDealAction(): Action
    {
        return Action::make('createDeal')
            ->label('إنشاء صفقة')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->schema([
                DatePicker::make('deal_date')->label('تاريخ الصفقة')->default(now())->required(),
                TextInput::make('agreed_total_value')->label('القيمة الإجمالية المتفق عليها')->numeric()->minValue(0.01)->required(),
                Select::make('currency')->label('العملة')->options(['EGP' => 'جنيه مصري', 'USD' => 'دولار أمريكي'])->default('EGP')->required(),
                TextInput::make('unit_code')->label('كود الوحدة')->numeric()->maxLength(40),
                TextInput::make('unit_type')->label('نوع الوحدة')->maxLength(80),
                TextInput::make('unit_area')->label('المساحة (م²)')->numeric()->minValue(0.01),
                TextInput::make('unit_location')->label('موقع الوحدة')->maxLength(255),
                TextInput::make('contact_representative')->label('ممثل التواصل')->maxLength(160),
                TextInput::make('field_agent_name')->label('المندوب الميداني')->maxLength(160),
                ...(static::department() === Department::Rental ? [
                    Select::make('rental_term')->label('مدة الإيجار')->options([
                        'daily' => 'يومي', 'monthly' => 'شهري', 'half_yearly' => 'نصف سنوي', 'yearly' => 'سنوي',
                    ])->default(fn (Model $record): ?string => $record->rental_term?->value)->live()->afterStateUpdated(fn (Set $set, ?string $state) => $set('payment_schedule', $state === 'daily' ? 'daily' : 'monthly'))->required(),
                    TextInput::make('rental_term_count')->label('مدة العقد')->numeric()->minValue(1)->maxValue(120)->default(fn (Model $record): ?int => $record->rental_term_count)->required(),
                    Select::make('payment_schedule')->label('دورية الدفع')->options([
                        'daily' => 'يومي', 'monthly' => 'شهري',
                    ])->default(fn (Get $get): string => $get('rental_term') === 'daily' ? 'daily' : 'monthly')->required()->disabled()->dehydrated(),
                ] : [
                    TextInput::make('seller_name')->label('اسم البائع')->maxLength(160),
                    PhoneField::make('هاتف البائع', required: false, phoneField: 'seller_phone', countryField: 'seller_phone_country'),
                ]),
                Repeater::make('commissions')->label('اتفاقات العمولة')->schema([
                    TextInput::make('payer')->label('الملتزم بالدفع')->required()->maxLength(160),
                    TextInput::make('recipient')->label('المستفيد')->required()->maxLength(160),
                    Select::make('calculation_type')->label('طريقة الاتفاق')->options(['amount' => 'قيمة ثابتة', 'percentage' => 'نسبة مئوية'])->required(),
                    TextInput::make('agreed_value')->label('القيمة أو النسبة')->numeric()->minValue(0.01),
                    Textarea::make('conditions')->label('شروط الاتفاق')->rows(2),
                ])->columns(2)->defaultItems(0)->maxItems(20),
                Textarea::make('notes')->label('ملاحظات الصفقة')->rows(3),
            ])
            ->visible(function (Model $record): bool {
                $dealClass = static::department() === Department::Rental
                    ? RentalDeal::class
                    : ResaleDeal::class;

                return $record->status === RequestStatus::Closed
                    && $record->outcome === RequestOutcome::Successful
                    && ! $dealClass::query()->where('request_id', $record->getKey())->exists();
            })
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(DealService::class)->createFromRequest($actor, static::department(), (int) $record->getKey(), $data);
                $record->refresh();
            })
            ->successNotificationTitle('تم إنشاء الصفقة وربطها بالطلب');
    }

    public static function viewDealAction(): Action
    {
        return Action::make('viewDeal')
            ->label('عرض الصفقة')
            ->icon('heroicon-o-eye')
            ->schema([
                Placeholder::make('deal_date')->label('تاريخ الصفقة')->content(fn (Model $record) => $record->deal?->deal_date
                    ? app(DateTimePresenter::class)->bidi(app(DateTimePresenter::class)->formatDate($record->deal->deal_date))
                    : '—'),
                Placeholder::make('deal_value')->label('القيمة الإجمالية')->content(fn (Model $record): string => $record->deal ? number_format((float) $record->deal->agreed_total_value, 2).' '.$record->deal->currency : '—'),
                Placeholder::make('deal_unit')->label('الوحدة')->content(fn (Model $record): string => implode(' — ', array_filter([$record->deal?->unit_type, $record->deal?->unit_area ? $record->deal->unit_area.' م²' : null, $record->deal?->unit_location, $record->deal?->unit_code ? 'كود '.$record->deal->unit_code : null])) ?: '—'),
                Placeholder::make('deal_contact')->label('ممثل التواصل / المندوب')->content(fn (Model $record): string => implode(' / ', array_filter([$record->deal?->contact_representative, $record->deal?->field_agent_name])) ?: '—'),
                Placeholder::make('deal_context')->label(static::department() === Department::Rental ? 'مدة العقد ودورية الدفع' : 'بيانات البائع')->content(function (Model $record): string {
                    $deal = $record->deal;
                    if (! $deal) {
                        return '—';
                    }
                    if (static::department() === Department::Rental) {
                        $terms = ['daily' => 'يومي', 'monthly' => 'شهري', 'half_yearly' => 'نصف سنوي', 'yearly' => 'سنوي'];
                        $payments = ['daily' => 'يومي', 'monthly' => 'شهري'];

                        return ($terms[$deal->rental_term] ?? '—').' × '.($deal->rental_term_count ?? '—').' — سداد '.($payments[$deal->payment_schedule] ?? '—');
                    }

                    return implode(' — ', array_filter([$deal->seller_name, $deal->seller_phone])) ?: '—';
                }),
                Placeholder::make('deal_commissions')->label('اتفاقات العمولة')->content(fn (Model $record): string => $record->deal?->commissions->map(fn (Model $commission): string => $commission->payer.' → '.$commission->recipient.': '.($commission->calculation_type === 'percentage' ? $commission->agreed_value.'%' : $commission->agreed_value.' '.$record->deal->currency).($commission->conditions ? ' ('.$commission->conditions.')' : ''))->implode("\n") ?: 'لا توجد اتفاقات مسجلة'),
                Placeholder::make('deal_notes')->label('ملاحظات الصفقة')->content(fn (Model $record): string => $record->deal?->notes ?: '—'),
            ])
            ->visible(fn (Model $record): bool => $record->deal !== null);
    }

    /** @return array<int, string> */
    private static function closeReasonOptions(): array
    {
        $class = static::department() === Department::Rental
            ? RentalRequestCloseReason::class
            : ResaleRequestCloseReason::class;

        return $class::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all();
    }
}
