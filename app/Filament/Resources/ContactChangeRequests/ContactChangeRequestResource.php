<?php

namespace App\Filament\Resources\ContactChangeRequests;

use App\Enums\ContactChangeRequestStatus;
use App\Enums\Department;
use App\Filament\Resources\ContactChangeRequests\Pages\ListContactChangeRequests;
use App\Filament\Support\FilamentValidationHandler;
use App\Models\ContactChangeRequest;
use App\Models\RentalCustomer;
use App\Models\ResaleCustomer;
use App\Models\User;
use App\Services\BrokerService;
use App\Services\ContactChangeApprovalService;
use App\Services\CustomerService;
use Filament\Actions\Action;
use Filament\Forms\Components\Textarea;
use Filament\Resources\Pages\PageRegistration;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\SelectFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

class ContactChangeRequestResource extends Resource
{
    protected static ?string $model = ContactChangeRequest::class;

    protected static ?string $modelLabel = 'طلب تعديل بيانات';

    protected static ?string $pluralModelLabel = 'طلبات تعديل البيانات';

    protected static string|\BackedEnum|null $navigationIcon = 'heroicon-o-clipboard-document-check';

    protected static string|\UnitEnum|null $navigationGroup = 'إدارة الشركة';

    protected static ?int $navigationSort = 4;

    public static function getNavigationLabel(): string
    {
        return auth()->user()?->is_company_manager ? 'مراجعة التعديلات' : 'طلباتي';
    }

    public static function getNavigationBadge(): ?string
    {
        $actor = auth()->user();
        if (! $actor instanceof User) {
            return null;
        }

        $query = ContactChangeRequest::query()
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $rental): void {
                        $rental->where('department', Department::Rental->value)
                            ->whereIn('subject_type', ['rental_customer', 'rental_broker']);
                    })
                    ->orWhere(function (Builder $resale): void {
                        $resale->where('department', Department::Resale->value)
                            ->whereIn('subject_type', ['resale_customer', 'resale_broker']);
                    });
            })
            ->where('status', ContactChangeRequestStatus::Pending->value);

        if (! $actor->is_company_manager) {
            $departments = $actor->departmentMemberships()->pluck('department')->all();
            $query->where('requested_by', $actor->id)->whereIn('department', $departments);
        }

        $count = $query->count();

        return $count > 0 ? (string) $count : null;
    }

    public static function getNavigationBadgeColor(): ?string
    {
        return 'warning';
    }

    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();
        $query = parent::getEloquentQuery()
            ->where(function (Builder $query): void {
                $query
                    ->where(function (Builder $rental): void {
                        $rental->where('department', Department::Rental->value)
                            ->whereIn('subject_type', ['rental_customer', 'rental_broker']);
                    })
                    ->orWhere(function (Builder $resale): void {
                        $resale->where('department', Department::Resale->value)
                            ->whereIn('subject_type', ['resale_customer', 'resale_broker']);
                    });
            })
            ->with(['requester', 'decider', 'subject']);

        if (! $actor instanceof User) {
            return $query->whereRaw('1 = 0');
        }

        if ($actor->is_company_manager) {
            return $query;
        }

        $departments = $actor->departmentMemberships()->pluck('department')->all();

        return $query
            ->where('requested_by', $actor->id)
            ->whereIn('department', $departments);
    }

    public static function canViewAny(): bool
    {
        $actor = auth()->user();

        return $actor instanceof User
            && ($actor->is_company_manager || $actor->departmentMemberships()->exists());
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

    /** @return array<string, PageRegistration> */
    public static function getPages(): array
    {
        return ['index' => ListContactChangeRequests::route('/')];
    }

    public static function table(Table $table): Table
    {
        return $table
            ->columns([
                TextColumn::make('created_at')
                    ->label('تاريخ الطلب')
                    ->dateTime('d/m/Y H:i')
                    ->sortable(),
                TextColumn::make('department')
                    ->label('القسم')
                    ->state(fn (ContactChangeRequest $record): string => $record->department->label())
                    ->badge()
                    ->color(fn (ContactChangeRequest $record): string => $record->department === Department::Rental ? 'info' : 'warning'),
                TextColumn::make('subject_type')
                    ->label('نوع السجل')
                    ->formatStateUsing(fn (string $state): string => self::subjectTypeLabel($state)),
                TextColumn::make('subject_name')
                    ->label('السجل')
                    ->state(fn (ContactChangeRequest $record): string => $record->subject?->name ?? 'السجل غير متاح')
                    ->weight(\Filament\Support\Enums\FontWeight::Medium),
                TextColumn::make('requester.name')
                    ->label('مقدم الطلب')
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('status')
                    ->label('الحالة')
                    ->state(fn (ContactChangeRequest $record): string => $record->status->label())
                    ->badge()
                    ->color(fn (ContactChangeRequest $record): string => match ($record->status) {
                        ContactChangeRequestStatus::Pending => 'warning',
                        ContactChangeRequestStatus::Approved => 'success',
                        ContactChangeRequestStatus::Rejected => 'danger',
                        ContactChangeRequestStatus::Cancelled => 'gray',
                    }),
                TextColumn::make('decider.name')
                    ->label('صاحب القرار')
                    ->placeholder('—')
                    ->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('reason')
                    ->label('سبب الرفض')
                    ->placeholder('—')
                    ->wrap()
                    ->toggleable(isToggledHiddenByDefault: true),
            ])
            ->filters([
                SelectFilter::make('status')
                    ->label('الحالة')
                    ->options(collect(ContactChangeRequestStatus::cases())
                        ->mapWithKeys(fn (ContactChangeRequestStatus $status): array => [$status->value => $status->label()])
                        ->all())
                    ->default(ContactChangeRequestStatus::Pending->value),
                SelectFilter::make('department')
                    ->label('القسم')
                    ->options(collect(Department::cases())
                        ->mapWithKeys(fn (Department $department): array => [$department->value => $department->label()])
                        ->all()),
                SelectFilter::make('subject_type')
                    ->label('نوع السجل')
                    ->options([
                        'rental_customer' => 'عميل إيجار',
                        'resale_customer' => 'عميل إعادة بيع',
                        'rental_broker' => 'وسيط إيجار',
                        'resale_broker' => 'وسيط إعادة بيع',
                    ]),
            ])
            ->recordActions([
                Action::make('viewChanges')
                    ->label('مراجعة التغييرات')
                    ->icon('heroicon-o-eye')
                    ->modalHeading(fn (ContactChangeRequest $record): string => 'تفاصيل طلب تعديل '.self::subjectTypeLabel($record->subject_type))
                    ->modalContent(fn (ContactChangeRequest $record) => view('filament.modals.contact-change-request', [
                        'changeRequest' => $record,
                        'similarNameLabels' => static::currentSimilarNameLabels($record),
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->action(fn () => null),
                Action::make('approve')
                    ->label('الموافقة على التعديل')
                    ->icon('heroicon-o-check-circle')
                    ->color('success')
                    ->visible(fn (ContactChangeRequest $record): bool => static::isManager() && $record->status === ContactChangeRequestStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading('الموافقة على تعديل البيانات')
                    ->modalDescription(function (ContactChangeRequest $record): string {
                        $description = 'سيتم تطبيق التغييرات على '.($record->subject?->name ?? 'السجل').' بعد إعادة التحقق من البيانات الحالية.';
                        $similarNames = static::currentSimilarNameLabels($record);

                        return $similarNames === []
                            ? $description
                            : $description.' توجد أسماء مشابهة حاليًا: '.implode('، ', $similarNames).'. راجعها قبل الموافقة.';
                    })
                    ->modalSubmitActionLabel('موافقة وتطبيق')
                    ->action(function (ContactChangeRequest $record): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);

                        FilamentValidationHandler::run(
                            fn () => app(ContactChangeApprovalService::class)->decide($actor, (int) $record->getKey(), true),
                            errorTitle: 'تعذر اعتماد التعديل'
                        );
                    })
                    ->successNotificationTitle('تمت الموافقة على التعديل'),
                Action::make('reject')
                    ->label('رفض الطلب')
                    ->icon('heroicon-o-x-circle')
                    ->color('danger')
                    ->visible(fn (ContactChangeRequest $record): bool => static::isManager() && $record->status === ContactChangeRequestStatus::Pending)
                    ->modalHeading('رفض طلب تعديل البيانات')
                    ->modalDescription('اكتب سبب الرفض ليعرف مقدم الطلب ما يحتاج إلى تصحيحه.')
                    ->modalSubmitActionLabel('رفض الطلب')
                    ->schema([
                        Textarea::make('reason')
                            ->label('سبب الرفض')
                            ->required()
                            ->maxLength(2000),
                    ])
                    ->action(function (ContactChangeRequest $record, array $data, \Filament\Schemas\Schema $schema): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);

                        FilamentValidationHandler::run(
                            fn () => app(ContactChangeApprovalService::class)->decide($actor, (int) $record->getKey(), false, $data['reason'] ?? null),
                            $schema,
                            'تعذر رفض الطلب'
                        );
                    })
                    ->successNotificationTitle('تم رفض الطلب'),
                Action::make('cancel')
                    ->label('إلغاء الطلب')
                    ->icon('heroicon-o-x-mark')
                    ->color('gray')
                    ->visible(fn (ContactChangeRequest $record): bool => ! static::isManager()
                        && auth()->id() === $record->requested_by
                        && $record->status === ContactChangeRequestStatus::Pending)
                    ->requiresConfirmation()
                    ->modalHeading('إلغاء طلب التعديل')
                    ->modalDescription('لن تتغير بيانات العميل أو الوسيط. يمكنك إرسال طلب جديد بعد الإلغاء.')
                    ->modalSubmitActionLabel('إلغاء الطلب')
                    ->action(function (ContactChangeRequest $record): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);

                        app(ContactChangeApprovalService::class)->cancel($actor, (int) $record->getKey());
                    })
                    ->successNotificationTitle('تم إلغاء طلب التعديل'),
            ])
            ->defaultSort('created_at', 'desc')
            ->columnManagerMaxHeight('24rem');
    }

    private static function isManager(): bool
    {
        return auth()->user()?->is_company_manager ?? false;
    }

    /** @return list<string> */
    private static function currentSimilarNameLabels(ContactChangeRequest $record): array
    {
        $name = trim((string) ($record->after_values['name'] ?? ''));
        $subject = $record->subject;
        if ($name === '' || ! $subject instanceof Model) {
            return [];
        }

        if ($subject instanceof RentalCustomer || $subject instanceof ResaleCustomer) {
            return app(CustomerService::class)->similarNameLabels($record->department, $name, (int) $subject->getKey());
        }

        $actor = auth()->user();
        if (! $actor instanceof User) {
            return [];
        }

        return app(BrokerService::class)->similarNameLabels($actor, $record->department, $name, (int) $subject->getKey());
    }

    private static function subjectTypeLabel(string $type): string
    {
        return match ($type) {
            'rental_customer' => 'عميل إيجار',
            'resale_customer' => 'عميل إعادة بيع',
            'rental_broker' => 'وسيط إيجار',
            'resale_broker' => 'وسيط إعادة بيع',
            default => 'سجل غير معروف',
        };
    }
}
