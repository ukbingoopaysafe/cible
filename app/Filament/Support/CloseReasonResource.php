<?php

namespace App\Filament\Support;

use App\Enums\Department;
use App\Models\RentalRequest;
use App\Models\ResaleRequest;
use App\Models\User;
use App\Services\CloseReasonService;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @extends Resource<Model> */
abstract class CloseReasonResource extends Resource
{
    abstract public static function department(): Department;

    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();

        return parent::getEloquentQuery()->when(
            ! $actor instanceof User || ! $actor->canWorkInDepartment(static::department()),
            fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
        );
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canManageDepartment(static::department()) ?? false;
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
        return static::canViewAny() && ! self::isUsed($record);
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('تصنيف الإغلاق')->searchable()->sortable(),
                IconColumn::make('is_other')->label('تصنيف أخرى')->boolean(),
                IconColumn::make('is_active')->label('متاح للاختيار')->boolean(),
                TextColumn::make('sort_order')->label('ترتيب العرض')->sortable(),
            ])
            ->recordActions([
                Action::make('rename')
                    ->label('تعديل التصنيف')
                    ->schema([TextInput::make('name')->label('اسم التصنيف')->maxLength(120)->required()])
                    ->fillForm(fn (Model $record): array => ['name' => $record->name])
                    ->action(function (Model $record, array $data): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(CloseReasonService::class)->updateName($actor, static::department(), (int) $record->getKey(), $data['name']);
                    })
                    ->successNotificationTitle('تم تعديل التصنيف'),
                Action::make('deactivate')
                    ->label('تعطيل')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Model $record): bool => $record->is_active)
                    ->action(fn (Model $record) => static::setActive($record, false)),
                Action::make('activate')
                    ->label('إعادة تفعيل')
                    ->visible(fn (Model $record): bool => ! $record->is_active)
                    ->action(fn (Model $record) => static::setActive($record, true)),
                Action::make('deleteReason')
                    ->label('حذف التصنيف')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->modalDescription('لا يمكن التراجع عن الحذف. لا يظهر هذا الإجراء إلا إذا لم يرتبط التصنيف بأي طلب.')
                    ->visible(fn (Model $record): bool => ! self::isUsed($record))
                    ->action(function (Model $record): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(CloseReasonService::class)->delete($actor, static::department(), (int) $record->getKey());
                    })
                    ->successNotificationTitle('تم حذف التصنيف'),
            ]);
    }

    public static function createAction(): Action
    {
        return Action::make('addCloseReason')
            ->label('إضافة تصنيف')
            ->schema([TextInput::make('name')->label('اسم التصنيف')->maxLength(120)->required()])
            ->action(function (array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(CloseReasonService::class)->create($actor, static::department(), $data);
            })
            ->successNotificationTitle('تمت إضافة تصنيف الإغلاق');
    }

    private static function setActive(Model $record, bool $active): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        app(CloseReasonService::class)->setActive($actor, static::department(), (int) $record->getKey(), $active);
    }

    private static function isUsed(Model $record): bool
    {
        $requestClass = static::department() === Department::Rental ? RentalRequest::class : ResaleRequest::class;

        return $requestClass::query()->where('outcome_reason_id', $record->getKey())->exists();
    }
}
