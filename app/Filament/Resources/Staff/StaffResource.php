<?php

namespace App\Filament\Resources\Staff;

use App\Enums\Department;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Models\User;
use App\Services\StaffManagementService;
use Filament\Actions\Action;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Forms\Components\Toggle;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;

    protected static ?string $modelLabel = 'مستخدم';

    protected static ?string $pluralModelLabel = 'إدارة المستخدمين';

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
                ->label('تصفية حسب الدور')
                ->options([
                    '1' => 'المديرون',
                    '0' => 'الموظفون',
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
                        'is_company_manager' => $record->is_company_manager ? 1 : 0,
                        'departments' => $record->departmentMemberships()
                            ->whereIn('department', array_map(fn (Department $d): string => $d->value, $managed))
                            ->pluck('department')->all(),
                        'can_add_rental_areas' => $record->can_add_rental_areas,
                    ];
                })
                ->action(function (User $record, array $data): void {
                    app(StaffManagementService::class)->updateEmployee(auth()->user(), (int) $record->getKey(), $data);
                })
                ->successNotificationTitle('تم تحديث بيانات المستخدم بنجاح'),
            Action::make('disableMfa')
                ->label('تعطيل MFA')
                ->icon('heroicon-o-shield-exclamation')
                ->color('danger')
                ->visible(fn (User $record): bool => $record->hasMultiFactorAuthentication())
                ->requiresConfirmation()
                ->modalHeading('تعطيل المصادقة الثنائية (MFA)')
                ->modalDescription(fn (User $record): string => "هل أنت متأكد من رغبتك في إلغاء تفعيل المصادقة الثنائية لحساب {$record->name} ({$record->email})؟ سيتمكن صاحب الحساب من تسجيل الدخول بكلمة المرور فقط.")
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
            ->label('إضافة مستخدم')
            ->icon('heroicon-o-user-plus')
            ->schema(static::formFields(editing: false))
            ->action(fn (array $data) => app(StaffManagementService::class)->createEmployee(auth()->user(), $data))
            ->successNotificationTitle('تم إنشاء الحساب بنجاح');
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
                ->label('نوع الحساب (الدور)')
                ->options([
                    0 => 'موظف',
                    1 => 'مدير',
                ])
                ->default(0)
                ->required()
                ->disabled((bool) $isSelf)
                ->helperText($isSelf ? 'لا يمكنك إلغاء صلاحية المدير عن حسابك الحالي تجنبًا لفقدان الوصول للإدارة.' : 'يمتلك المدير صلاحية الوصول والإدارة لجميع الأقسام تلقائيًا.')
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
                ->visible(fn (Get $get): bool => ! (bool) $get('is_company_manager') && in_array(Department::Rental->value, $get('departments') ?? [], true)),
        ];
    }
}
