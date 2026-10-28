<?php

namespace App\Filament\Resources\Staff;

use App\Enums\Department;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Filament\Support\FilamentValidationHandler;
use App\Models\User;
use App\Services\StaffManagementService;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Placeholder;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\ImageColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'موظف';

    protected static ?string $pluralModelLabel = 'فريق العمل';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-user-group';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة الشركة';

    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->with('departmentMemberships');
    }

    public static function canViewAny(): bool
    {
        return auth()->user() instanceof User && auth()->user()->is_company_manager;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        return static::canViewAny();
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListStaff::route('/')];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            ImageColumn::make('avatar_url')
                ->label('الصورة')
                ->circular()
                ->disk('public')
                ->defaultImageUrl(asset('images/brand/default-avatar.svg')),
            TextColumn::make('name')
                ->label('الاسم')
                ->searchable()
                ->sortable(),
            TextColumn::make('email')
                ->label('البريد الإلكتروني')
                ->searchable(),
            TextColumn::make('is_company_manager')
                ->label('الدور والصلاحية')
                ->badge()
                ->state(fn (User $record): string => $record->is_company_manager ? 'مدير' : 'موظف')
                ->color(fn (User $record): string => $record->is_company_manager ? 'warning' : 'gray')
                ->icon(fn (User $record): string => $record->is_company_manager ? 'heroicon-m-shield-check' : 'heroicon-m-user'),
            TextColumn::make('departments')
                ->label('الأقسام')
                ->state(function (User $record): string {
                    if ($record->is_company_manager) {
                        return 'كافة الأقسام';
                    }
                    $depts = $record->departmentMemberships
                        ->map(fn ($membership): string => $membership->department->label());

                    return $depts->isNotEmpty() ? $depts->join('، ') : '—';
                }),
            TextColumn::make('can_add_rental_areas')
                ->label('إضافة مناطق')
                ->state(function (User $record): string {
                    if ($record->is_company_manager) {
                        return 'مسموح تلقائيًا';
                    }

                    return $record->can_add_rental_areas ? 'مسموح' : 'غير مسموح';
                }),
            TextColumn::make('mfa_status')
                ->label('المصادقة الثنائية (MFA)')
                ->badge()
                ->state(fn (User $record): string => $record->hasMultiFactorAuthentication() ? 'مفعّل' : 'غير مفعّل')
                ->color(fn (User $record): string => $record->hasMultiFactorAuthentication() ? 'success' : 'gray')
                ->icon(fn (User $record): string => $record->hasMultiFactorAuthentication() ? 'heroicon-m-check-badge' : 'heroicon-m-x-circle'),
            TextColumn::make('created_at')
                ->label('تاريخ الإنشاء')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ])->filters([
            SelectFilter::make('is_company_manager')
                ->label('الدور')
                ->options([
                    '1' => 'مدير',
                    '0' => 'موظف',
                ]),
        ])->recordActions([
            Action::make('editEmployee')
                ->label('تعديل')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (User $record): bool => static::canEdit($record))
                ->schema(fn (User $record): array => static::formFields(editing: true, record: $record))
                ->fillForm(function (User $record): array {
                    $managed = app(StaffManagementService::class)->managedDepartments(auth()->user());

                    return [
                        'name' => $record->name,
                        'email' => $record->email,
                        'avatar_url' => $record->avatar_url,
                        'is_company_manager' => $record->is_company_manager ? 1 : 0,
                        'departments' => $record->departmentMemberships()
                            ->whereIn('department', array_map(fn (Department $d): string => $d->value, $managed))
                            ->pluck('department')->all(),
                        'can_add_rental_areas' => $record->can_add_rental_areas,
                    ];
                })
                ->action(function (User $record, array $data, Schema $schema): void {
                    FilamentValidationHandler::run(
                        fn () => app(StaffManagementService::class)->updateEmployee(auth()->user(), (int) $record->getKey(), $data),
                        $schema,
                        'تعذر حفظ التعديلات'
                    );
                })
                ->successNotificationTitle('تم حفظ التعديلات'),
            Action::make('setupMyMfa')
                ->label('إعداد MFA لحسابك')
                ->icon('heroicon-o-shield-check')
                ->color('primary')
                ->visible(fn (User $record): bool => (auth()->id() === $record->id) && ! $record->hasMultiFactorAuthentication())
                ->url(fn (): string => filament()->getProfileUrl()),
            Action::make('mfaInfo')
                ->label('كيف يُفعّل المستخدم MFA؟')
                ->icon('heroicon-o-question-mark-circle')
                ->color('gray')
                ->visible(fn (User $record): bool => ! $record->hasMultiFactorAuthentication() && (auth()->id() !== $record->id))
                ->modalHeading('تفعيل المصادقة الثنائية (MFA)')
                ->modalDescription(fn (User $record): string => "لتفعيل المصادقة الثنائية، يفتح {$record->name} صفحة «الملف الشخصي» ويمسح رمز QR بتطبيق المصادقة (مثل Google Authenticator).")
                ->modalSubmitAction(false)
                ->modalCancelActionLabel('إغلاق'),
            Action::make('disableMfa')
                ->label('تعطيل MFA')
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger')
                ->visible(fn (User $record): bool => $record->hasMultiFactorAuthentication())
                ->requiresConfirmation()
                ->modalHeading('تعطيل المصادقة الثنائية (MFA)')
                ->modalDescription(fn (User $record): string => "تعطيل المصادقة الثنائية لحساب {$record->name}؟ سيكتفي الحساب بكلمة المرور فقط لتسجيل الدخول.")
                ->modalSubmitActionLabel('نعم، تعطيل MFA')
                ->action(function (User $record): void {
                    app(StaffManagementService::class)->disableMfa(auth()->user(), (int) $record->getKey());
                })
                ->successNotificationTitle('تم إلغاء تفعيل المصادقة الثنائية (MFA) بنجاح لهذا الحساب'),
        ]);
    }

    public static function createEmployeeAction(): Action
    {
        return Action::make('createEmployee')
            ->label('إضافة موظف')
            ->icon('heroicon-o-user-plus')
            ->schema(static::formFields(editing: false))
            ->action(function (array $data, Schema $schema): void {
                FilamentValidationHandler::run(
                    fn () => app(StaffManagementService::class)->createEmployee(auth()->user(), $data),
                    $schema,
                    'تعذر إضافة الموظف'
                );
            })
            ->successNotificationTitle('تمت إضافة الموظف بنجاح');
    }

    /** @return array<int, Component> */
    private static function formFields(bool $editing, ?User $record = null): array
    {
        $actor = auth()->user();
        $departments = $actor instanceof User
            ? app(StaffManagementService::class)->managedDepartments($actor)
            : [];

        $isSelf = $editing && $record && $actor && $actor->is($record);

        return [
            FileUpload::make('avatar_url')
                ->label('الصورة الشخصية')
                ->avatar()
                ->image()
                ->disk('public')
                ->directory('avatars')
                ->visibility('public')
                ->maxSize(2048)
                ->imageEditor()
                ->circleCropper(),
            TextInput::make('name')
                ->label('الاسم')
                ->required()
                ->maxLength(255),
            TextInput::make('email')
                ->label('البريد الإلكتروني')
                ->email()
                ->required()
                ->maxLength(255),
            TextInput::make('password')
                ->label($editing ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور')
                ->password()
                ->revealable()
                ->required(! $editing)
                ->minLength(12)
                ->dehydrated(fn ($state): bool => filled($state)),
            Select::make('is_company_manager')
                ->label('الدور')
                ->options([
                    0 => 'موظف',
                    1 => 'مدير',
                ])
                ->default(0)
                ->required()
                ->disabled((bool) $isSelf)
                ->helperText($isSelf ? 'لا يمكن إزالة صلاحية المدير عن حسابك الحالي.' : 'المدير لديه وصول كامل لجميع الأقسام.')
                ->live(),
            Select::make('departments')
                ->label('الأقسام المصرح بها')
                ->multiple()
                ->visible(fn (Get $get): bool => ! (bool) $get('is_company_manager'))
                ->required(fn (Get $get): bool => ! $editing && ! (bool) $get('is_company_manager'))
                ->minItems(fn (Get $get): int => (! (bool) $get('is_company_manager') && ! $editing) ? 1 : 0)
                ->options(collect($departments)->mapWithKeys(fn (Department $department): array => [$department->value => $department->label()])->all()),
            Toggle::make('can_add_rental_areas')
                ->label('السماح بإضافة مناطق')
                ->default(false)
                ->visible(fn (Get $get): bool => ! (bool) $get('is_company_manager')),
            Placeholder::make('mfa_status_info')
                ->label('المصادقة الثنائية (MFA)')
                ->content(function (?User $record): string {
                    if (! $record) {
                        return 'يمكن للموظف إعداد المصادقة الثنائية من صفحته الشخصية فور إنشاء حسابه.';
                    }
                    if ($record->hasMultiFactorAuthentication()) {
                        return 'مفعّلة على هذا الحساب.';
                    }

                    return 'غير مفعّلة. يمكن تفعيلها من صفحة الملف الشخصي.';
                }),
        ];
    }
}
