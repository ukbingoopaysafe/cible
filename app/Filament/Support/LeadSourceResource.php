<?php

namespace App\Filament\Support;

use App\Enums\Department;
use App\Enums\LeadSourceType;
use App\Models\User;
use App\Services\LeadSourceService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\IconColumn;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @extends Resource<Model> */
abstract class LeadSourceResource extends Resource
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
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('name')->label('مصدر الوصول')->searchable(),
                TextColumn::make('type')->label('تصنيف المصدر')->formatStateUsing(fn ($state): string => $state instanceof LeadSourceType ? $state->label() : '—'),
                IconColumn::make('is_active')->label('متاح للاختيار')->boolean(),
                IconColumn::make('requires_details')->label('يتطلب تفاصيل')->boolean(),
                TextColumn::make('updated_at')->label('آخر تحديث')->formatStateUsing(fn ($state) => app(\App\Support\DateTimePresenter::class)->bidi(app(\App\Support\DateTimePresenter::class)->formatRelativeDateTime($state, now())))->html(),
            ])
            ->recordActions([
                Action::make('deactivate')
                    ->label('تعطيل')
                    ->color('danger')
                    ->requiresConfirmation()
                    ->visible(fn (Model $record): bool => $record->is_active && $record->type !== LeadSourceType::Other)
                    ->action(fn (Model $record) => static::setActive($record, false)),
                Action::make('activate')
                    ->label('إعادة تفعيل')
                    ->visible(fn (Model $record): bool => ! $record->is_active && ! in_array($record->type, [LeadSourceType::LegacyContactMethod, LeadSourceType::UnclassifiedLegacy], true))
                    ->action(fn (Model $record) => static::setActive($record, true)),
                Action::make('classify')
                    ->label('تصنيف المصدر')
                    ->visible(fn (Model $record): bool => $record->type === LeadSourceType::UnclassifiedLegacy)
                    ->schema([
                        Select::make('type')
                            ->label('تصنيف المصدر')
                            ->options(LeadSourceType::managerCreatableOptions())
                            ->required(),
                    ])
                    ->action(function (Model $record, array $data): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(LeadSourceService::class)->classify($actor, static::department(), (int) $record->getKey(), $data['type']);
                    }),
            ]);
    }

    public static function createAction(): Action
    {
        return Action::make('addLeadSource')
            ->label('إضافة مصدر')
            ->schema([
                TextInput::make('name')->label('اسم المصدر')->maxLength(100)->required(),
                Select::make('type')
                    ->label('تصنيف المصدر')
                    ->options(LeadSourceType::managerCreatableOptions())
                    ->required(),
            ])
            ->action(function (array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(LeadSourceService::class)->create($actor, static::department(), $data);
            })
            ->successNotificationTitle('تمت إضافة مصدر الوصول');
    }

    private static function setActive(Model $record, bool $active): void
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);
        app(LeadSourceService::class)->setActive($actor, static::department(), (int) $record->getKey(), $active);
    }
}
