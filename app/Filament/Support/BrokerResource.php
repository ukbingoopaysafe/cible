<?php

namespace App\Filament\Support;

use App\Enums\Department;
use App\Enums\ContactChangeRequestStatus;
use App\Models\RentalActivity;
use App\Models\ResaleActivity;
use App\Models\User;
use App\Services\BrokerService;
use App\Support\PhoneCountries;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\Hidden;
use Filament\Resources\Resource;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @extends resource<Model> */
abstract class BrokerResource extends Resource
{
    abstract public static function department(): Department;

    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();

        return parent::getEloquentQuery()
            ->with('phoneNumbers')
            ->withCount('requests')
            ->withCount(['contactChangeRequests as pending_contact_change_requests_count' => fn (Builder $query): Builder => $query->where('status', ContactChangeRequestStatus::Pending->value)])
            ->when(
                ! $actor instanceof User || ! $actor->canWorkInDepartment(static::department()),
                fn (Builder $query): Builder => $query->whereRaw('1 = 0'),
            );
    }

    public static function canViewAny(): bool
    {
        return auth()->user()?->canWorkInDepartment(static::department()) ?? false;
    }

    public static function canCreate(): bool
    {
        return static::canViewAny();
    }

    public static function canEdit(Model $record): bool
    {
        $modelClass = static::getModel();

        return static::canViewAny() && $record instanceof $modelClass;
    }

    public static function canDelete(Model $record): bool
    {
        $modelClass = static::getModel();
        $actor = auth()->user();

        return static::canViewAny() && $actor instanceof User && $actor->canManageDepartment(static::department()) && $record instanceof $modelClass;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchPlaceholder('بحث بالاسم أو الهاتف...')
            ->columns([
                TextColumn::make('name')
                    ->label('اسم الوسيط')
                    ->description(fn (Model $record): ?string => (int) ($record->pending_contact_change_requests_count ?? 0) > 0 ? 'طلب تعديل بانتظار المدير' : null)
                    ->searchable()
                    ->sortable(),
                TextColumn::make('primary_phone')
                    ->label('الهاتف الأساسي')
                    ->state(fn (Model $record): ?string => $record->primaryPhone()?->phone_e164)
                    ->formatStateUsing(fn (?string $state): ?Htmlable => SectionRequestResource::formatPhoneForDisplay($state))
                    ->description(fn (Model $record): ?string => ($count = $record->phoneNumbers->where('is_primary', false)->count()) > 0
                        ? "{$count} رقم إضافي"
                        : null)
                    ->searchable(query: fn (Builder $query, string $search): Builder => $query->whereHas(
                        'phoneNumbers',
                        fn (Builder $phones): Builder => $phones->where('phone_e164', 'like', "%{$search}%"),
                    ))
                    ->placeholder('—'),
                TextColumn::make('primary_phone_country')
                    ->label('الدولة')
                    ->state(fn (Model $record): ?string => PhoneCountries::display($record->primaryPhone()?->phone_country))
                    ->placeholder('—')
                    ->toggleable(),
                TextColumn::make('requests_count')->label('عدد الطلبات')->sortable(),
                TextColumn::make('created_at')->label('تاريخ التسجيل')->dateTime('d/m/Y H:i')->sortable()->toggleable(),
            ])
            ->recordActions([
                static::getBrokerActionsGroup(),
            ]);
    }

    public static function getBrokerActionsGroup(): ActionGroup
    {
        $categoryGroups = [
            'broker-profile' => ActionGroup::make([
                Action::make('viewContactPhoneNumbers')
                    ->label('أرقام الهاتف')
                    ->icon('heroicon-o-phone')
                    ->modalHeading(fn (Model $record): string => "أرقام الهاتف: {$record->name}")
                    ->modalContent(fn (Model $record) => view('filament.modals.contact-phone-numbers', [
                        'phones' => $record->phoneNumbers,
                    ]))
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->action(fn () => null),
                Action::make('editBroker')
                    ->label('تعديل الوسيط')
                    ->icon('heroicon-o-pencil-square')
                    ->modalHeading('تعديل بيانات الوسيط')
                    ->modalDescription('حدّث اسم الوسيط وأرقام هاتفه. أول رقم في القائمة هو الأساسي تلقائيًا.')
                    ->modalSubmitActionLabel('حفظ التعديلات')
                    ->disabled(fn (Model $record): bool => ! (auth()->user()?->is_company_manager ?? false)
                        && (int) ($record->pending_contact_change_requests_count ?? 0) > 0)
                    ->tooltip(fn (Model $record): ?string => ! (auth()->user()?->is_company_manager ?? false)
                        && (int) ($record->pending_contact_change_requests_count ?? 0) > 0 ? 'يوجد طلب تعديل بانتظار قرار المدير.' : null)
                    ->schema(array_merge(
                        [Hidden::make('record_snapshot')->dehydrated()],
                        BrokerFormSchema::fields(static::department(), editing: true),
                    ))
                    ->fillForm(fn (Model $record): array => [
                        'name' => $record->name,
                        'record_snapshot' => json_encode(app(BrokerService::class)->auditValues($record), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
                    ] + ContactPhonesField::fromContact($record))
                    ->action(function (Model $record, array $data, Schema $schema): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        $snapshot = json_decode((string) ($data['record_snapshot'] ?? ''), true);
                        $expectedSnapshot = is_array($snapshot) ? $snapshot : null;
                        unset($data['record_snapshot']);
                        $data = ContactPhonesField::toServiceData($data);
                        $reviewed = (bool) ($data['reviewed_similar_names'] ?? false);
                        unset($data['reviewed_similar_names']);

                        FilamentValidationHandler::run(
                            fn () => app(BrokerService::class)->update(
                                $actor,
                                static::department(),
                                (int) $record->getKey(),
                                $data,
                                $reviewed,
                                $expectedSnapshot,
                            ),
                            $schema,
                            'تعذر تحديث بيانات الوسيط'
                        );
                    })
                    ->successNotificationTitle(fn (): string => auth()->user()?->is_company_manager ? 'تم تحديث بيانات الوسيط' : 'تم إرسال طلب التعديل للمدير'),
            ])
                ->label('بيانات الوسيط')
                ->icon('heroicon-o-user')
                ->dropdown(false),

            'broker-delete' => ActionGroup::make([
                Action::make('deleteBroker')
                    ->label('حذف الوسيط نهائيًا')
                    ->color('danger')
                    ->icon('heroicon-o-trash')
                    ->visible(fn (Model $record): bool => static::canDelete($record))
                    ->modalHeading('تأكيد حذف الوسيط نهائيًا')
                    ->modalDescription(fn (Model $record): string => "سيتم حذف {$record->name} نهائيًا. الطلبات المرتبطة: {$record->requests()->count()}، أنشطة التواصل مع الوسيط: ".static::activityCount($record).'. لا يمكن التراجع عن هذا الإجراء؛ تُحفظ بيانات الوسيط التاريخية على الطلبات المغلقة.')
                    ->modalSubmitActionLabel('حذف الوسيط')
                    ->schema([
                        Textarea::make('reason')
                            ->label('سبب الحذف')
                            ->required()
                            ->maxLength(2000)
                            ->helperText('سيُسجَّل سبب الحذف في سجل التدقيق.'),
                    ])
                    ->requiresConfirmation()
                    ->action(function (Model $record, array $data): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(BrokerService::class)->delete($actor, static::department(), (int) $record->getKey(), $data['reason']);
                    })
                    ->successNotificationTitle('تم حذف الوسيط وتسجيل العملية'),
            ])
                ->label('الحذف')
                ->icon('heroicon-o-trash')
                ->dropdown(false),
        ];

        // The admin theme attaches the category heading to its actions, so
        // headings stay correct when a whole category is hidden.
        foreach ($categoryGroups as $category => $categoryGroup) {
            foreach ($categoryGroup->getActions() as $action) {
                $action->extraAttributes([
                    'data-action-category' => $category,
                ]);
            }
        }

        return ActionGroup::make(array_values($categoryGroups))
            ->label('المزيد')
            ->icon('heroicon-m-ellipsis-vertical')
            ->iconButton()
            ->color('gray')
            ->tooltip('خيارات الوسيط')
            ->dropdownMaxHeight('18rem')
            ->dropdownTeleport()
            ->dropdownFlip()
            ->extraDropdownAttributes(['class' => 'fi-broker-actions-dropdown']);
    }

    private static function activityCount(Model $broker): int
    {
        $requestIds = $broker->requests()->pluck($broker->requests()->getRelated()->getQualifiedKeyName());
        $activityClass = static::department() === Department::Rental ? RentalActivity::class : ResaleActivity::class;
        $foreignKey = static::department() === Department::Rental ? 'rental_request_id' : 'resale_request_id';

        return $activityClass::query()->where(function ($query) use ($broker, $requestIds, $foreignKey): void {
            $query->where('broker_id_snapshot', $broker->getKey())
                ->orWhere(function ($legacy) use ($broker, $requestIds, $foreignKey): void {
                    $legacy->whereNull('broker_id_snapshot')->whereIn($foreignKey, $requestIds)
                        ->where('contact_party', 'broker')->where('contact_name_snapshot', $broker->name);
                });
        })->count();
    }

    public static function createBrokerAction(): Action
    {
        return Action::make('createBroker')
            ->label('إضافة وسيط')
            ->modalHeading('إضافة وسيط')
            ->modalDescription('أضف بيانات الوسيط وأرقامه. أول رقم في القائمة هو الأساسي تلقائيًا.')
            ->modalSubmitActionLabel('إضافة الوسيط')
            ->schema(BrokerFormSchema::fields(static::department()))
            ->action(function (array $data, Schema $schema): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                $data = ContactPhonesField::toServiceData($data);
                $reviewed = (bool) ($data['reviewed_similar_names'] ?? false);
                unset($data['reviewed_similar_names']);

                FilamentValidationHandler::run(
                    fn () => app(BrokerService::class)->create($actor, static::department(), $data, $reviewed),
                    $schema,
                    'تعذر إضافة الوسيط'
                );
            })
            ->successNotificationTitle('تمت إضافة الوسيط');
    }

}
