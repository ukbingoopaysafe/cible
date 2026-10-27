<?php

namespace App\Filament\Resources\Areas;

use App\Filament\Resources\Areas\Pages\ListAreas;
use App\Filament\Support\FilamentValidationHandler;
use App\Models\Area;
use App\Models\User;
use App\Services\AreaArchiveService;
use App\Services\AreaService;
use Filament\Actions\Action;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class AreaResource extends Resource
{
    protected static ?string $model = Area::class;

    protected static ?string $modelLabel = 'منطقة';

    protected static ?string $pluralModelLabel = 'المناطق الجغرافية';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-map-pin';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة الشركة';

    protected static ?int $navigationSort = 2;

    public static function getEloquentQuery(): Builder
    {
        return parent::getEloquentQuery()
            ->withCount(['rentalRequests', 'resaleRequests']);
    }

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && ($actor->is_company_manager || $actor->canAddRentalAreas());
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
        return ['index' => ListAreas::route('/')];
    }

    public static function table(Table $table): Table
    {
        return $table->columns([
            TextColumn::make('governorate')
                ->label('المحافظة')
                ->badge()
                ->color('gray')
                ->sortable(),
            TextColumn::make('name')
                ->label('اسم المنطقة')
                ->searchable()
                ->sortable(),
            TextColumn::make('rental_requests_count')
                ->label('طلبات الإيجار')
                ->counts('rentalRequests')
                ->sortable(),
            TextColumn::make('resale_requests_count')
                ->label('طلبات إعادة البيع')
                ->counts('resaleRequests')
                ->sortable(),
            TextColumn::make('deleted_at')
                ->label('الحالة')
                ->badge()
                ->formatStateUsing(fn ($state): string => filled($state) ? 'مؤرشفة' : 'نشطة')
                ->color(fn ($state): string => filled($state) ? 'warning' : 'success'),
            TextColumn::make('created_at')
                ->label('تاريخ الإضافة')
                ->dateTime('d/m/Y H:i')
                ->sortable(),
        ])->filters([
            SelectFilter::make('governorate')
                ->label('المحافظة')
                ->options(['القاهرة' => 'القاهرة', 'الجيزة' => 'الجيزة']),
            TrashedFilter::make()
                ->label('الأرشيف')
                ->visible(fn (): bool => auth()->user()?->is_company_manager ?? false),
        ])->recordActions([
            Action::make('archiveArea')
                ->label('أرشفة المنطقة')
                ->icon('heroicon-o-archive-box')
                ->color('warning')
                ->requiresConfirmation()
                ->modalHeading('أرشفة المنطقة الجغرافية')
                ->modalDescription('ستبقى كافة طلبات الإيجار وإعادة البيع السابقة المرتبطة بهذه المنطقة محفوظة ومتاحة، ولكن لن تظهر المنطقة في قائمة الاختيار للطلبات الجديدة.')
                ->visible(fn (Model $record): bool => ! $record->trashed()
                    && (auth()->user()?->is_company_manager ?? false))
                ->action(function (Model $record): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    app(AreaArchiveService::class)->archiveUnified($actor, (int) $record->getKey());
                    Notification::make()->title('تمت أرشفة المنطقة بنجاح')->success()->send();
                }),
            Action::make('restoreArea')
                ->label('استعادة المنطقة')
                ->icon('heroicon-o-arrow-uturn-left')
                ->visible(fn (Model $record): bool => $record->trashed()
                    && (auth()->user()?->is_company_manager ?? false))
                ->action(function (Model $record): void {
                    $actor = auth()->user();
                    abort_unless($actor instanceof User, 403);
                    app(AreaArchiveService::class)->restoreUnified($actor, (int) $record->getKey());
                    Notification::make()->title('تمت استعادة المنطقة بنجاح')->success()->send();
                }),
        ]);
    }

    public static function createAreaAction(): Action
    {
        return Action::make('createArea')
            ->label('إضافة منطقة جديدة')
            ->icon('heroicon-o-plus')
            ->schema([
                Select::make('governorate')
                    ->label('المحافظة')
                    ->options(['القاهرة' => 'القاهرة', 'الجيزة' => 'الجيزة'])
                    ->required(),
                TextInput::make('name')
                    ->label('اسم المنطقة')
                    ->required()
                    ->maxLength(100),
            ])
            ->action(function (array $data, Schema $schema): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);

                FilamentValidationHandler::run(
                    fn () => app(AreaService::class)->create($actor, $data),
                    $schema,
                    'تعذر إضافة المنطقة'
                );
            })
            ->successNotificationTitle('تمت إضافة المنطقة بنجاح');
    }
}
