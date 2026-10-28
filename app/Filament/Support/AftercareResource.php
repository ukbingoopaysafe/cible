<?php

namespace App\Filament\Support;

use App\Enums\ContactActivityChannel;
use App\Enums\Department;
use App\Models\RentalCustomer;
use App\Models\RentalDeal;
use App\Models\ResaleCustomer;
use App\Models\ResaleDeal;
use App\Models\User;
use App\Services\AftercareService;
use App\Support\RecordCode;
use Carbon\Carbon;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

abstract class AftercareResource extends Resource
{
    abstract public static function department(): Department;

    public static function canViewAny(): bool
    {
        return auth()->user()?->canWorkInDepartment(static::department()) ?? false;
    }

    public static function canCreate(): bool
    {
        return false;
    }

    public static function canEdit(Model $record): bool
    {
        return false;
    }

    public static function canDelete(Model $record): bool
    {
        return false;
    }

    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();

        return parent::getEloquentQuery()
            ->with(['customer', 'owner', 'activities.actor', 'activities.deal'])
            ->withCount('activities')
            ->when(
                ! $actor instanceof User,
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            );
    }

    public static function getNavigationBadge(): ?string
    {
        $actor = auth()->user();
        if (! $actor instanceof User || ! $actor->canWorkInDepartment(static::department())) {
            return null;
        }

        $count = static::getEloquentQuery()
            ->where('next_follow_up_at', '<=', now()->endOfDay())
            ->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        $overdueCount = static::getEloquentQuery()
            ->where('next_follow_up_at', '<', now())
            ->count();

        return $overdueCount > 0 ? 'danger' : 'warning';
    }

    public static function table(Table $table): Table
    {
        return $table
            ->headerActions([
                self::startAftercareHeaderAction(),
            ])
            ->columns([
                TextColumn::make('customer_code')
                    ->label('كود العميل')
                    ->state(fn (Model $record): ?string => RecordCode::customer(static::department(), $record->customer_id))
                    ->extraHeaderAttributes(['class' => 'whitespace-nowrap'])
                    ->extraCellAttributes(['class' => 'record-code-cell whitespace-nowrap'])
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('customer_id', $direction))
                    ->searchable(query: fn (Builder $query, string $search): Builder => RecordCode::applySearch($query, $search, static::department(), RecordCode::TYPE_CUSTOMER, 'customer_id')),

                TextColumn::make('customer.name')
                    ->label('العميل')
                    ->searchable()
                    ->sortable()
                    ->weight('bold'),

                TextColumn::make('customer.phone_e164')
                    ->label('الهاتف')
                    ->searchable()
                    ->extraCellAttributes(['class' => 'whitespace-nowrap']),

                TextColumn::make('owner.name')
                    ->label('المسؤول')
                    ->placeholder('—')
                    ->searchable()
                    ->sortable()
                    ->toggleable(),

                TextColumn::make('activities_count')
                    ->label('التواصل')
                    ->badge()
                    ->color('info')
                    ->sortable(),

                TextColumn::make('next_follow_up_at')
                    ->label('المتابعة القادمة')
                    ->formatStateUsing(function ($state): string {
                        if (! $state) {
                            return '—';
                        }
                        $date = Carbon::parse($state);
                        if ($date->isPast()) {
                            return 'متأخرة • '.$date->format('d/m/Y H:i');
                        }
                        if ($date->isToday()) {
                            return 'اليوم • '.$date->format('H:i');
                        }

                        return $date->format('d/m/Y H:i');
                    })
                    ->badge()
                    ->color(function ($state): string {
                        if (! $state) {
                            return 'gray';
                        }
                        $date = Carbon::parse($state);
                        if ($date->isPast()) {
                            return 'danger';
                        }
                        if ($date->isToday()) {
                            return 'warning';
                        }

                        return 'success';
                    })
                    ->sortable(),

                TextColumn::make('created_at')
                    ->label('تاريخ البدء')
                    ->dateTime('d/m/Y')
                    ->sortable()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('urgency')
                    ->label('حالة المتابعة')
                    ->options([
                        'overdue' => 'متأخرة',
                        'today' => 'اليوم',
                        'upcoming' => 'قادمة',
                        'unscheduled' => 'بدون موعد',
                    ])
                    ->query(function (Builder $query, array $data): Builder {
                        $value = $data['value'] ?? null;
                        $now = now();

                        return match ($value) {
                            'overdue' => $query->where('next_follow_up_at', '<', $now),
                            'today' => $query->whereBetween('next_follow_up_at', [$now, $now->copy()->endOfDay()]),
                            'upcoming' => $query->where('next_follow_up_at', '>', $now->copy()->endOfDay()),
                            'unscheduled' => $query->whereNull('next_follow_up_at'),
                            default => $query,
                        };
                    }),

                SelectFilter::make('owner_user_id')
                    ->label('المسؤول')
                    ->options(fn (): array => User::query()
                        ->where('is_company_manager', true)
                        ->orWhereHas('departmentMemberships', fn ($q) => $q->where('department', static::department()->value))
                        ->pluck('name', 'id')
                        ->all())
                    ->searchable(),
            ])
            ->recordActions([
                Action::make('openWhatsApp')
                    ->label('فتح WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('فتح WhatsApp')
                    ->url(fn (Model $record): string => static::whatsAppUrl($record->customer?->phone_e164))
                    ->openUrlInNewTab()
                    ->visible(fn (Model $record): bool => static::hasValidWhatsAppPhone($record->customer?->phone_e164)),

                Action::make('recordActivity')
                    ->label('تسجيل تواصل')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->color('primary')
                    ->iconButton()
                    ->tooltip('تسجيل تواصل')
                    ->schema([
                        Select::make('deal_id')
                            ->label('الصفقة المرتبطة')
                            ->options(fn (Model $record): array => (static::department() === Department::Rental ? RentalDeal::class : ResaleDeal::class)::query()
                                ->where('customer_id', $record->customer_id)
                                ->orderByDesc('deal_date')
                                ->get()
                                ->mapWithKeys(fn (Model $deal): array => [$deal->id => $deal->deal_date->format('d/m/Y').' — '.number_format((float) $deal->agreed_total_value, 2).' '.$deal->currency])
                                ->all())
                            ->searchable(),
                        Select::make('channel')
                            ->label('وسيلة التواصل')
                            ->options(collect(ContactActivityChannel::cases())->mapWithKeys(fn (ContactActivityChannel $channel): array => [$channel->value => $channel->label()])->all())
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
                            ->required()
                            ->maxLength(10000),
                        Textarea::make('notes')
                            ->label('ملاحظات')
                            ->rows(2)
                            ->maxLength(10000),
                        Textarea::make('referral_details')
                            ->label('إحالة أو فرصة مستقبلية')
                            ->rows(2)
                            ->maxLength(10000),
                        DateTimePicker::make('next_follow_up_at')
                            ->label('المتابعة القادمة')
                            ->native(false)
                            ->seconds(false)
                            ->minDate(now()->addMinute()),
                    ])
                    ->action(function (Model $record, array $data): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(AftercareService::class)->recordActivity($actor, static::department(), (int) $record->customer_id, $data);
                    })
                    ->successNotificationTitle('تم تسجيل التواصل وتحديث موعد المتابعة'),

                ActionGroup::make([
                    Action::make('aftercareHistory')
                        ->label('سجل المتابعات')
                        ->icon('heroicon-o-clock')
                        ->modalHeading(fn (Model $record): string => "سجل متابعة العميل: {$record->customer?->name}")
                        ->modalSubmitAction(false)
                        ->modalCancelActionLabel('إغلاق')
                        ->modalContent(fn (Model $record) => view('filament.modals.aftercare-history', [
                            'aftercare' => $record->load(['owner', 'activities.actor', 'activities.deal']),
                        ])),

                    Action::make('reassignOwner')
                        ->label('تغيير المسؤول')
                        ->icon('heroicon-o-user-plus')
                        ->schema([
                            Select::make('owner_user_id')
                                ->label('المسؤول الجديد')
                                ->options(fn (): array => User::query()
                                    ->where('is_company_manager', true)
                                    ->orWhereHas('departmentMemberships', fn ($q) => $q->where('department', static::department()->value))
                                    ->pluck('name', 'id')
                                    ->all())
                                ->required()
                                ->searchable(),
                        ])
                        ->visible(fn (): bool => auth()->user()?->canManageDepartment(static::department()) ?? false)
                        ->action(function (Model $record, array $data): void {
                            $record->update(['owner_user_id' => $data['owner_user_id']]);
                        })
                        ->successNotificationTitle('تم تحديث المسؤول'),
                ]),
            ])
            ->defaultSort('next_follow_up_at', 'asc');
    }

    public static function startAftercareHeaderAction(): Action
    {
        return Action::make('startAftercare')
            ->label('بدء متابعة عميل')
            ->icon('heroicon-o-plus-circle')
            ->schema([
                Select::make('customer_id')
                    ->label('العميل')
                    ->options(function (): array {
                        $customerClass = static::department() === Department::Rental ? RentalCustomer::class : ResaleCustomer::class;

                        return $customerClass::query()
                            ->whereHas('deals')
                            ->whereDoesntHave('aftercare')
                            ->pluck('name', 'id')
                            ->all();
                    })
                    ->helperText('يظهر فقط العملاء الذين لديهم صفقات مسجلة ولم تبدأ متابعتهم بعد.')
                    ->required()
                    ->searchable(),
            ])
            ->action(function (array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                app(AftercareService::class)->startForCustomer($actor, static::department(), (int) $data['customer_id']);
            })
            ->successNotificationTitle('تم بدء سجل المتابعة بنجاح');
    }

    protected static function hasValidWhatsAppPhone(?string $phone): bool
    {
        return is_string($phone) && preg_match('/^\+[1-9]\d{7,14}$/', $phone) === 1;
    }

    protected static function whatsAppUrl(?string $phone): string
    {
        abort_unless(static::hasValidWhatsAppPhone($phone), 404);

        return 'https://wa.me/'.substr((string) $phone, 1);
    }
}
