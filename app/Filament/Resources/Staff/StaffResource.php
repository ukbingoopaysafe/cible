<?php

namespace App\Filament\Resources\Staff;

use App\Enums\Department;
use App\Filament\Resources\Staff\Pages\ListStaff;
use App\Models\User;
use App\Services\StaffManagementService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class StaffResource extends Resource
{
    protected static ?string $model = User::class;
    protected static ?string $modelLabel = 'موظف';
    protected static ?string $pluralModelLabel = 'إدارة الموظفين';
    protected static string | \BackedEnum | null $navigationIcon = 'heroicon-o-user-group';
    protected static string | \UnitEnum | null $navigationGroup = 'إدارة الشركة';
    protected static ?int $navigationSort = 1;

    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();
        $departments = $actor instanceof User && $actor->is_company_manager
            ? $actor->departmentMemberships()->pluck('department')->all()
            : [];

        return parent::getEloquentQuery()
            ->with('departmentMemberships')
            ->where('is_company_manager', false)
            ->whereHas('departmentMemberships', fn (Builder $query) => $query->whereIn('department', $departments));
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
        return static::canViewAny() && $record instanceof User && ! $record->is_company_manager;
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
            TextColumn::make('name')->label('اسم الموظف')->searchable()->sortable(),
            TextColumn::make('email')->label('البريد الإلكتروني')->searchable(),
            TextColumn::make('departments')
                ->label('الأقسام')
                ->state(fn (User $record): string => $record->departmentMemberships
                    ->map(fn ($membership): string => $membership->department->label())->join('، ')),
            TextColumn::make('created_at')->label('تاريخ إنشاء الحساب')->dateTime('d/m/Y H:i')->sortable(),
        ])->recordActions([
            Action::make('editEmployee')
                ->label('تعديل')
                ->icon('heroicon-o-pencil-square')
                ->visible(fn (User $record): bool => static::canEdit($record))
                ->schema(static::formFields(editing: true))
                ->fillForm(function (User $record): array {
                    $managed = app(StaffManagementService::class)->managedDepartments(auth()->user());

                    return [
                        'name' => $record->name,
                        'email' => $record->email,
                        'departments' => $record->departmentMemberships()
                            ->whereIn('department', array_map(fn (Department $d): string => $d->value, $managed))
                            ->pluck('department')->all(),
                    ];
                })
                ->action(function (User $record, array $data): void {
                    app(StaffManagementService::class)->updateEmployee(auth()->user(), (int) $record->getKey(), $data);
                })
                ->successNotificationTitle('تم تحديث بيانات الموظف'),
        ]);
    }

    public static function createEmployeeAction(): Action
    {
        return Action::make('createEmployee')
            ->label('إضافة موظف')
            ->schema(static::formFields(editing: false))
            ->action(fn (array $data) => app(StaffManagementService::class)->createEmployee(auth()->user(), $data))
            ->successNotificationTitle('تم إنشاء حساب الموظف');
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private static function formFields(bool $editing): array
    {
        $actor = auth()->user();
        $departments = $actor instanceof User
            ? app(StaffManagementService::class)->managedDepartments($actor)
            : [];

        return [
            TextInput::make('name')->label('اسم الموظف')->required()->maxLength(255),
            TextInput::make('email')->label('البريد الإلكتروني')->email()->required()->maxLength(255),
            TextInput::make('password')
                ->label($editing ? 'كلمة مرور جديدة (اختياري)' : 'كلمة المرور')
                ->password()->revealable()->required(! $editing)->minLength(12)->dehydrated(fn ($state): bool => filled($state)),
            Select::make('departments')
                ->label('الأقسام المصرح بها')
                ->multiple()->required(! $editing)->minItems($editing ? 0 : 1)
                ->options(collect($departments)->mapWithKeys(fn (Department $department): array => [$department->value => $department->label()])->all()),
        ];
    }
}
