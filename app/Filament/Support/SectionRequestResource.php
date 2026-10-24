<?php

namespace App\Filament\Support;

use App\Enums\Department;
use App\Enums\ContactActivityChannel;
use App\Enums\RequestOutcome;
use App\Enums\RequestStatus;
use App\Exports\RentalRequestExporter;
use App\Exports\ResaleRequestExporter;
use App\Models\User;
use App\Models\RentalCustomer;
use App\Models\ResaleCustomer;
use App\Models\RentalRequestReassignment;
use App\Models\ResaleRequestReassignment;
use App\Services\RequestService;
use App\Services\DealService;
use App\Services\ContactActivityService;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Actions\EditAction;
use Filament\Actions\ViewAction;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\Repeater;
use Filament\Forms\Components\Radio;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @extends Resource<Model> */
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

        return $query->with(['customer', 'broker', 'area', 'owner', 'leadSource', 'closeReason', 'deal.commissions'])
            ->withCount('activities')
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

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                ExportAction::make()
                    ->label('تصدير Excel')
                    ->exporter(static::department() === Department::Rental ? RentalRequestExporter::class : ResaleRequestExporter::class)
                    ->formats([ExportFormat::Xlsx])
                    ->columnMapping(false),
            ])
            ->columns([
                TextColumn::make('customer.name')->label('العميل')->placeholder('غير معروف')->searchable(),
                TextColumn::make('customer.phone_e164')->label('هاتف العميل')->placeholder('—')->searchable(),
                TextColumn::make('broker.name')->label('الوسيط')->state(fn (Model $record): ?string => $record->broker?->name ?? $record->broker_name_snapshot)->placeholder('—')->searchable(),
                TextColumn::make('area.name')->label('المنطقة')->description(fn (Model $record): string => $record->area?->governorate ?? ''),
                TextColumn::make('owner.name')->label('المسؤول')->searchable(),
                TextColumn::make('pending_reassignment_count')->label('طلب تغيير المسؤول')
                    ->formatStateUsing(fn ($state): string => (int) $state > 0 ? 'بانتظار المدير' : '—')
                    ->badge()
                    ->color(fn ($state): string => (int) $state > 0 ? 'warning' : 'gray'),
                TextColumn::make('stage')->label('المرحلة')->badge()->formatStateUsing(fn ($state): string => RequestFormSchema::stageOptions(static::department())[$state instanceof \BackedEnum ? $state->value : $state] ?? (string) $state),
                TextColumn::make('status')->label('الحالة')->badge()->formatStateUsing(fn ($state): string => match ($state instanceof RequestStatus ? $state : RequestStatus::from($state)) {
                    RequestStatus::Active => 'نشط',
                    RequestStatus::Paused => 'متوقف مؤقتًا',
                    RequestStatus::Closed => 'مغلق',
                }),
                TextColumn::make('next_follow_up_at')->label('المتابعة القادمة')->dateTime('d/m/Y H:i')->placeholder('—')->sortable(),
                TextColumn::make('activities_count')->label('سجل التواصل')->sortable(),
                TextColumn::make('deal.agreed_total_value')->label('الصفقة')->money(fn (Model $record): string => $record->deal?->currency ?? 'EGP')->placeholder('—'),
            ])
            ->filters([
                SelectFilter::make('status')->label('الحالة')->options([
                    RequestStatus::Active->value => 'نشط',
                    RequestStatus::Paused->value => 'متوقف مؤقتًا',
                    RequestStatus::Closed->value => 'مغلق',
                ]),
                SelectFilter::make('customer_link')
                    ->label('ارتباط العميل')
                    ->options([
                        'linked' => 'مرتبط بعميل',
                        'unlinked' => 'بانتظار ربط العميل',
                    ])
                    ->query(fn (Builder $query, array $data): Builder => match ($data['value'] ?? null) {
                        'linked' => $query->whereNotNull('customer_id'),
                        'unlinked' => $query->whereNull('customer_id'),
                        default => $query,
                    }),
                SelectFilter::make('owner_id')
                    ->label('المسؤول')
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
            ->recordActions([
                ViewAction::make()->label('عرض الطلب'),
                EditAction::make()->label('تعديل التفاصيل'),
                self::recordContactAction(),
                self::contactHistoryAction(),
                self::linkCustomerAction(),
                self::setFollowUpAction(),
                self::requestReassignmentAction(),
                self::directReassignmentAction(),
                self::approveReassignmentAction(),
                self::rejectReassignmentAction(),
                self::changeStageAction(),
                self::pauseAction(),
                self::resumeAction(),
                self::closeAction(),
                self::createDealAction(),
                self::viewDealAction(),
            ])
            ->defaultSort('next_follow_up_at');
    }

    private static function linkCustomerAction(): Action
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
                Toggle::make('make_contact')->label('اجعل العميل جهة الاتصال الحالية'),
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
            })
            ->successNotificationTitle('تم ربط العميل بالطلب');
    }

    private static function changeStageAction(): Action
    {
        return Action::make('changeStage')
            ->label('تغيير المرحلة')
            ->icon('heroicon-o-arrow-path')
            ->schema([
                Select::make('stage')->label('المرحلة الجديدة')->options(RequestFormSchema::stageOptions(static::department()))->required(),
                Textarea::make('notes')->label('ملاحظة (اختيارية)')->rows(2),
            ])
            ->visible(fn (Model $record): bool => $record->status === RequestStatus::Active)
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->changeStage($actor, static::department(), (int) $record->getKey(), $data['stage'], $data['notes'] ?? null);
            })
            ->successNotificationTitle('تم تحديث مرحلة الطلب');
    }

    private static function setFollowUpAction(): Action
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
            })
            ->successNotificationTitle('تم تحديث موعد المتابعة');
    }

    private static function recordContactAction(): Action
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
                    ->label('الإجراء التالي (اختياري)')
                    ->helperText('هذا الوصف لا يغير موعد المتابعة القادم؛ حدّث الموعد من إجراء المتابعة عند الحاجة.')
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
            })
            ->successNotificationTitle('تم تسجيل التواصل');
    }

    private static function contactHistoryAction(): Action
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

    private static function requestReassignmentAction(): Action
    {
        return Action::make('requestReassignment')
            ->label('طلب تغيير المسؤول')
            ->icon('heroicon-o-user-plus')
            ->schema([
                Select::make('target_user_id')->label('المسؤول المقترح')
                    ->options(fn (Model $record): array => self::reassignmentStaffOptions($record))
                    ->searchable()->required(),
                Textarea::make('reason')->label('سبب طلب التغيير')->rows(3)->required()->maxLength(10000),
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
            })
            ->successNotificationTitle('أُرسل طلب إعادة التعيين للمدير');
    }

    private static function directReassignmentAction(): Action
    {
        return Action::make('directReassignment')
            ->label('إعادة تعيين المسؤول')
            ->icon('heroicon-o-arrow-path-rounded-square')
            ->schema([
                Select::make('target_user_id')->label('المسؤول الجديد')
                    ->options(fn (Model $record): array => self::reassignmentStaffOptions($record))
                    ->searchable()->required(),
                Textarea::make('reason')->label('سبب إعادة التعيين')->rows(3)->required()->maxLength(10000),
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
            })
            ->successNotificationTitle('تمت إعادة تعيين مسؤول الطلب');
    }

    private static function approveReassignmentAction(): Action
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
            })
            ->successNotificationTitle('تمت الموافقة على إعادة التعيين');
    }

    private static function rejectReassignmentAction(): Action
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

    private static function pendingReassignment(int $requestId): Model|null
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

    private static function pauseAction(): Action
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
            })
            ->successNotificationTitle('تم إيقاف الطلب مؤقتًا');
    }

    private static function resumeAction(): Action
    {
        return Action::make('resume')
            ->label('استئناف')
            ->icon('heroicon-o-play-circle')
            ->schema([
                DateTimePicker::make('next_follow_up_at')->label('موعد المتابعة القادمة')->native(false)->seconds(false)->minDate(now()->addMinute())->required(),
                Textarea::make('notes')->label('ملاحظة (اختيارية)')->rows(2),
            ])
            ->visible(fn (Model $record): bool => $record->status === RequestStatus::Paused)
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->resume($actor, static::department(), (int) $record->getKey(), $data['next_follow_up_at'], $data['notes'] ?? null);
            })
            ->successNotificationTitle('تم استئناف الطلب');
    }

    private static function closeAction(): Action
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
                    ->label('تصنيف مساعد (اختياري)')
                    ->options(fn (): array => self::closeReasonOptions())
                    ->dehydrated(fn (Get $get): bool => $get('outcome') === RequestOutcome::Unsuccessful->value)
                    ->visible(fn (Get $get): bool => $get('outcome') === RequestOutcome::Unsuccessful->value),
                Textarea::make('outcome_notes')->label('ملاحظات إضافية (اختيارية)')->rows(2),
            ])
            ->visible(fn (Model $record): bool => $record->status !== RequestStatus::Closed)
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RequestService::class)->close($actor, static::department(), (int) $record->getKey(), $data);
            })
            ->successNotificationTitle('تم إغلاق الطلب');
    }

    private static function createDealAction(): Action
    {
        return Action::make('createDeal')
            ->label('إنشاء صفقة')
            ->icon('heroicon-o-banknotes')
            ->color('success')
            ->schema([
                DatePicker::make('deal_date')->label('تاريخ الصفقة')->default(now())->required(),
                TextInput::make('agreed_total_value')->label('القيمة الإجمالية المتفق عليها')->numeric()->minValue(0.01)->required(),
                Select::make('currency')->label('العملة')->options(['EGP' => 'جنيه مصري', 'USD' => 'دولار أمريكي'])->default('EGP')->required(),
                TextInput::make('unit_code')->label('كود الوحدة في التطبيق الخارجي (اختياري)')->numeric()->maxLength(40),
                TextInput::make('unit_type')->label('نوع الوحدة (اختياري)')->maxLength(80),
                TextInput::make('unit_area')->label('المساحة بالمتر (اختياري)')->numeric()->minValue(0.01),
                TextInput::make('unit_location')->label('وصف موقع الوحدة (اختياري)')->maxLength(255),
                TextInput::make('contact_representative')->label('ممثل التواصل وقت الصفقة (اختياري)')->maxLength(160),
                TextInput::make('field_agent_name')->label('اسم المندوب الميداني (اختياري)')->maxLength(160),
                ...(static::department() === Department::Rental ? [
                    Select::make('rental_term')->label('مدة الإيجار')->options([
                        'daily' => 'يومي', 'monthly' => 'شهري', 'half_yearly' => 'نصف سنوي', 'yearly' => 'سنوي',
                    ])->default(fn (Model $record): ?string => $record->rental_term?->value)->live()->afterStateUpdated(fn (Set $set, ?string $state) => $set('payment_schedule', $state === 'daily' ? 'daily' : 'monthly'))->required(),
                    TextInput::make('rental_term_count')->label('عدد مدد العقد الكامل')->numeric()->minValue(1)->maxValue(120)->default(fn (Model $record): ?int => $record->rental_term_count)->required(),
                    Select::make('payment_schedule')->label('دورية الدفع')->options([
                        'daily' => 'يومي', 'monthly' => 'شهري',
                    ])->default(fn (Get $get): string => $get('rental_term') === 'daily' ? 'daily' : 'monthly')->required()->disabled()->dehydrated(),
                ] : [
                    TextInput::make('seller_name')->label('اسم البائع (اختياري)')->maxLength(160),
                    TextInput::make('seller_phone')->label('هاتف البائع (اختياري)')->tel()->maxLength(30),
                ]),
                Repeater::make('commissions')->label('اتفاقات العمولة (اختيارية)')->schema([
                    TextInput::make('payer')->label('الملتزم بالدفع')->required()->maxLength(160),
                    TextInput::make('recipient')->label('المستفيد')->required()->maxLength(160),
                    Select::make('calculation_type')->label('طريقة الاتفاق')->options(['amount' => 'قيمة ثابتة', 'percentage' => 'نسبة مئوية'])->required(),
                    TextInput::make('agreed_value')->label('القيمة أو النسبة')->numeric()->minValue(0.01)->required(),
                    Textarea::make('conditions')->label('شروط الاتفاق (اختيارية)')->rows(2),
                ])->columns(2)->defaultItems(0)->maxItems(20),
                Textarea::make('notes')->label('ملاحظات الصفقة (اختيارية)')->rows(3),
            ])
            ->visible(function (Model $record): bool {
                $dealClass = static::department() === Department::Rental
                    ? \App\Models\RentalDeal::class
                    : \App\Models\ResaleDeal::class;

                return $record->status === RequestStatus::Closed
                    && $record->outcome === RequestOutcome::Successful
                    && ! $dealClass::query()->where('request_id', $record->getKey())->exists();
            })
            ->action(function (Model $record, array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(DealService::class)->createFromRequest($actor, static::department(), (int) $record->getKey(), $data);
            })
            ->successNotificationTitle('تم إنشاء الصفقة وربطها بالطلب');
    }

    private static function viewDealAction(): Action
    {
        return Action::make('viewDeal')
            ->label('عرض الصفقة')
            ->icon('heroicon-o-eye')
            ->schema([
                Placeholder::make('deal_date')->label('تاريخ الصفقة')->content(fn (Model $record): string => $record->deal?->deal_date?->format('d/m/Y') ?? '—'),
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
            ? \App\Models\RentalRequestCloseReason::class
            : \App\Models\ResaleRequestCloseReason::class;

        return $class::query()->where('is_active', true)->orderBy('sort_order')->pluck('name', 'id')->all();
    }
}
