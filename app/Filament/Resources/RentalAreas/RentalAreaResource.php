<?php

namespace App\Filament\Resources\RentalAreas;

use App\Enums\Department;
use App\Filament\Resources\RentalAreas\Pages\ListRentalAreas;
use App\Models\RentalArea;
use App\Models\User;
use App\Services\AreaArchiveService;
use App\Services\RentalAreaService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class RentalAreaResource extends Resource
{
    protected static ?string $model = RentalArea::class;

    protected static ?string $modelLabel = 'منطقة إيجار';

    protected static ?string $pluralModelLabel = 'مناطق الإيجار';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map';

    protected static string|\UnitEnum|null $navigationGroup = 'الإيجار';

    protected static ?int $navigationSort = 35;

    protected static bool $shouldRegisterNavigation = false;

    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();

        return parent::getEloquentQuery()->when(
            ! $actor instanceof User || ! $actor->canWorkInDepartment(Department::Rental),
            fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
        );
    }

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && $actor->canWorkInDepartment(Department::Rental)
            && $actor->canAddRentalAreas();
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

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListRentalAreas::route('/')];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('governorate')->label('المحافظة')->sortable(),
            TextColumn::make('name')->label('المنطقة')->searchable()->sortable(),
            TextColumn::make('created_at')->label('تاريخ الإضافة')->dateTime('d/m/Y H:i')->sortable(),
            TextColumn::make('deleted_at')->label('الحالة')->badge()
                ->formatStateUsing(fn ($state): string => filled($state) ? 'مؤرشفة' : 'نشطة'),
        ])->filters([
            TrashedFilter::make()
                ->label('الأرشيف')
                ->visible(fn (): bool => auth()->user()?->is_company_manager ?? false),
        ])->recordActions([
            Action::make('archiveArea')
                ->label('أرشفة المنطقة')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->modalDescription('ستبقى طلبات هذه المنطقة التاريخية محفوظة، ولن تظهر المنطقة في الطلبات الجديدة. يمكن للمدير استعادتها لاحقًا.')
                ->visible(fn (Model $record): bool => ! $record->trashed()
                    && (auth()->user()?->is_company_manager ?? false))
                ->action(function (Model $record): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    app(AreaArchiveService::class)->archive($actor, Department::Rental, (int) $record->getKey());
                    Notification::make()->title('تمت أرشفة المنطقة')->success()->send();
                }),
            Action::make('restoreArea')
                ->label('استعادة المنطقة')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (Model $record): bool => $record->trashed()
                    && (auth()->user()?->is_company_manager ?? false))
                ->action(function (Model $record): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    app(AreaArchiveService::class)->restore($actor, Department::Rental, (int) $record->getKey());
                    Notification::make()->title('تمت استعادة المنطقة')->success()->send();
                }),
        ]);
    }

    public static function createAreaAction(): Action
    {
        return Action::make('createArea')
            ->label('إضافة منطقة')
            ->schema([
                Select::make('governorate')
                    ->label('المحافظة')
                    ->options(['القاهرة' => 'القاهرة', 'الجيزة' => 'الجيزة'])
                    ->required(),
                TextInput::make('name')->label('اسم المنطقة')->required()->maxLength(100),
            ])
            ->action(function (array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(RentalAreaService::class)->create($actor, $data);
            })
            ->successNotificationTitle('تمت إضافة المنطقة');
    }
}
