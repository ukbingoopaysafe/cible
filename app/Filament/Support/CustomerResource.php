<?php

namespace App\Filament\Support;

use App\Enums\ContactActivityChannel;
use App\Enums\Department;
use App\Exports\RentalCustomerExporter;
use App\Exports\ResaleCustomerExporter;
use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Filament\Resources\ResaleRequests\ResaleRequestResource;
use App\Models\RentalDeal;
use App\Models\ResaleDeal;
use App\Models\User;
use App\Services\AftercareService;
use App\Services\CustomerService;
use App\Services\CustomerSpreadsheetImportService;
use App\Support\PhoneCountries;
use App\Support\RecordCode;
use Filament\Actions\Action;
use Filament\Actions\ActionGroup;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\FileUpload;
use Filament\Forms\Components\Select;
use Filament\Forms\Components\Textarea;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Schema;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Filters\TrashedFilter;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Livewire\Features\SupportFileUploads\TemporaryUploadedFile;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Writer\XLSX\Writer;

/** @extends resource<Model> */
abstract class CustomerResource extends Resource
{
    abstract public static function department(): Department;

    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();

        return parent::getEloquentQuery()
            ->with('aftercare.owner')
            ->withCount('requests')
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
        return false;
    }

    public static function table(Table $table): Table
    {
        return $table
            ->searchPlaceholder('بحث بالكود، الاسم، أو الهاتف...')
            ->headerActions([
                self::importCustomersAction(),
                self::downloadImportTemplateAction(),
                ExportAction::make()
                    ->label('تصدير')
                    ->exporter(static::department() === Department::Rental ? RentalCustomerExporter::class : ResaleCustomerExporter::class)
                    ->formats([ExportFormat::Xlsx])
                    ->columnMapping(false),
            ])
            ->columns([
                TextColumn::make('code')
                    ->label('الكود')
                    ->state(fn (Model $record): ?string => RecordCode::customer(static::department(), $record->id))
                    ->extraHeaderAttributes(['class' => 'whitespace-nowrap'])
                    ->extraCellAttributes(['class' => 'record-code-cell whitespace-nowrap'])
                    ->sortable(query: fn (Builder $query, string $direction): Builder => $query->orderBy('id', $direction))
                    ->searchable(query: fn (Builder $query, string $search): Builder => RecordCode::applySearch($query, $search, static::department(), RecordCode::TYPE_CUSTOMER)),
                TextColumn::make('name')->label('الاسم')->searchable()->sortable()->toggleable(),
                TextColumn::make('phone_e164')->label('الهاتف')->searchable()->toggleable(),
                TextColumn::make('nationality')->label('الجنسية')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('requests_count')->label('الطلبات')->sortable()->toggleable(),
                TextColumn::make('aftercare.owner.name')->label('مسؤول ما بعد الصفقة')->placeholder('—')->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('aftercare.next_follow_up_at')->label('متابعة ما بعد الصفقة')->dateTime('d/m/Y H:i')->placeholder('—')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('created_at')->label('تاريخ التسجيل')->dateTime('d/m/Y H:i')->sortable()->toggleable(isToggledHiddenByDefault: true),
                TextColumn::make('deleted_at')->label('الحالة')->badge()
                    ->state(fn (Model $record): string => $record->trashed() ? 'مؤرشف' : 'نشط')
                    ->color(fn (string $state): string => $state === 'مؤرشف' ? 'gray' : 'success')
                    ->toggleable(),
            ])
            ->filters([
                TrashedFilter::make()
                    ->label('الأرشيف')
                    ->visible(fn (): bool => auth()->user()?->is_company_manager ?? false),
            ])
            ->columnManagerMaxHeight('24rem')
            ->recordActions([
                Action::make('openWhatsApp')
                    ->label('فتح WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->iconButton()
                    ->tooltip('فتح WhatsApp')
                    ->url(fn (Model $record): string => static::whatsAppUrl($record->phone_e164))
                    ->openUrlInNewTab()
                    ->visible(fn (Model $record): bool => ! $record->trashed() && static::hasValidWhatsAppPhone($record->phone_e164)),
                Action::make('viewCustomerRequests')
                    ->label('طلبات العميل')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->iconButton()
                    ->tooltip('طلبات العميل')
                    ->modalHeading(fn (Model $record): string => "طلبات العميل: {$record->name}")
                    ->modalDescription('سجل الطلبات المرتبطة بهذا العميل في هذا القسم.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(fn (Model $record) => view('filament.modals.customer-requests', [
                        'requests' => $record->requests()->with(['area', 'owner'])->latest()->get(),
                        'requestResource' => static::department() === Department::Rental ? RentalRequestResource::class : ResaleRequestResource::class,
                        'stageOptions' => RequestFormSchema::stageOptions(static::department()),
                    ])),
                static::getCustomerActionsGroup(),
            ]);
    }

    public static function getCustomerActionsGroup(): ActionGroup
    {
        $categoryGroups = [
            'profile' => ActionGroup::make([
                Action::make('editCustomer')
                    ->label('تعديل العميل')
                    ->icon('heroicon-o-pencil-square')
                    ->schema(static::formFields())
                    ->modalSubmitActionLabel('حفظ التعديلات')
                    ->visible(fn (Model $record): bool => ! $record->trashed())
                    ->fillForm(fn (Model $record): array => [
                        'name' => $record->name,
                        'phone' => PhoneCountries::nationalNumber($record->phone_e164, $record->phone_country),
                        'phone_country' => PhoneCountries::selectable($record->phone_country),
                        'nationality' => $record->nationality,
                    ])
                    ->action(function (Model $record, array $data, Schema $schema): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        $reviewed = (bool) ($data['reviewed_similar_names'] ?? false);
                        unset($data['reviewed_similar_names']);

                        FilamentValidationHandler::run(
                            fn () => app(CustomerService::class)->update(
                                $actor,
                                static::department(),
                                (int) $record->getKey(),
                                $data,
                                $reviewed,
                            ),
                            $schema,
                            'تعذر تحديث بيانات العميل'
                        );
                    })
                    ->successNotificationTitle('تم حفظ التعديلات'),
                Action::make('createCustomerRequest')
                    ->label(static::department() === Department::Rental ? 'إنشاء طلب إيجار' : 'إنشاء طلب إعادة بيع')
                    ->icon('heroicon-o-plus-circle')
                    ->visible(fn (Model $record): bool => ! $record->trashed())
                    ->url(fn (Model $record): string => (static::department() === Department::Rental ? RentalRequestResource::class : ResaleRequestResource::class)::getUrl('create', ['customer_id' => $record->getKey()])),
            ])
                ->label('بيانات العميل')
                ->icon('heroicon-o-user')
                ->dropdown(false),

            'aftercare' => ActionGroup::make([
                Action::make('startAftercare')
                    ->label('بدء ما بعد الصفقة')
                    ->icon('heroicon-o-heart')
                    ->action(function (Model $record): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(AftercareService::class)->startForCustomer($actor, static::department(), (int) $record->getKey());
                    })
                    ->visible(function (Model $record): bool {
                        if ($record->trashed()) {
                            return false;
                        }
                        $dealClass = static::department() === Department::Rental ? RentalDeal::class : ResaleDeal::class;

                        return $record->aftercare === null && $dealClass::query()->where('customer_id', $record->getKey())->exists();
                    })
                    ->successNotificationTitle('تم بدء سجل ما بعد الصفقة'),
                Action::make('recordAftercareActivity')
                    ->label('تسجيل تواصل ما بعد الصفقة')
                    ->icon('heroicon-o-chat-bubble-left-ellipsis')
                    ->modalHeading('تسجيل تواصل ما بعد الصفقة')
                    ->modalDescription('سيُسجَّل التواصل ويُحدَّث موعد المتابعة القادمة لهذا العميل.')
                    ->modalSubmitActionLabel('تسجيل التواصل')
                    ->schema([
                        Grid::make(['default' => 1, 'sm' => 2])
                            ->schema([
                                Select::make('deal_id')->label('الصفقة المرتبطة')->options(fn (Model $record): array => (static::department() === Department::Rental ? RentalDeal::class : ResaleDeal::class)::query()
                                    ->where('customer_id', $record->getKey())->orderByDesc('deal_date')->get()
                                    ->mapWithKeys(fn (Model $deal): array => [$deal->id => $deal->deal_date->format('d/m/Y').' — '.number_format((float) $deal->agreed_total_value, 2).' '.$deal->currency])->all())->searchable(),
                                Select::make('channel')->label('وسيلة التواصل')->options(collect(ContactActivityChannel::cases())->mapWithKeys(fn (ContactActivityChannel $channel): array => [$channel->value => $channel->label()])->all())->required(),
                                DateTimePicker::make('occurred_at')->label('وقت التواصل')->default(now())->native(false)->seconds(false)->maxDate(now())->required(),
                                DateTimePicker::make('next_follow_up_at')->label('المتابعة القادمة')->native(false)->seconds(false)->minDate(now()->addMinute()),
                                Textarea::make('result')->label('نتيجة التواصل')->rows(3)->required()->maxLength(10000)->columnSpanFull(),
                                Textarea::make('notes')->label('ملاحظات')->rows(2)->maxLength(10000),
                                Textarea::make('referral_details')->label('إحالة أو فرصة مستقبلية')->rows(2)->maxLength(10000),
                            ]),
                    ])
                    ->action(function (Model $record, array $data): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(AftercareService::class)->recordActivity($actor, static::department(), (int) $record->getKey(), $data);
                    })
                    ->visible(fn (Model $record): bool => ! $record->trashed() && $record->aftercare !== null)
                    ->successNotificationTitle('تم تسجيل التواصل وتحديث موعد المتابعة'),
                Action::make('aftercareHistory')
                    ->label('سجل ما بعد الصفقة')
                    ->icon('heroicon-o-clock')
                    ->modalHeading(fn (Model $record): string => "سجل ما بعد الصفقة: {$record->name}")
                    ->modalDescription('ملخص المتابعة وآخر التفاعلات المسجلة مع العميل.')
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(fn (Model $record) => view('filament.modals.aftercare-history', [
                        'aftercare' => $record->aftercare?->load(['owner', 'activities.actor', 'activities.deal']),
                    ]))
                    ->visible(fn (Model $record): bool => $record->aftercare !== null),
            ])
                ->label('ما بعد الصفقة')
                ->icon('heroicon-o-heart')
                ->dropdown(false),

            'archive' => ActionGroup::make([
                Action::make('archiveCustomer')
                    ->label('أرشفة العميل')
                    ->icon('heroicon-o-archive-box')
                    ->color('warning')
                    ->requiresConfirmation()
                    ->modalSubmitActionLabel('أرشفة العميل')
                    ->modalDescription('سيتم إخفاء العميل من القوائم الجديدة مع الاحتفاظ بسجل طلباته. يمكن استعادته لاحقًا.')
                    ->visible(fn (Model $record): bool => ! $record->trashed()
                        && (auth()->user()?->canManageDepartment(static::department()) ?? false))
                    ->action(function (Model $record): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(CustomerService::class)->archive($actor, static::department(), (int) $record->getKey());
                        Notification::make()->title('تمت أرشفة العميل')->success()->send();
                    }),
                Action::make('restoreCustomer')
                    ->label('استعادة العميل')
                    ->icon('heroicon-o-arrow-uturn-left')
                    ->visible(fn (Model $record): bool => $record->trashed()
                        && (auth()->user()?->canManageDepartment(static::department()) ?? false))
                    ->action(function (Model $record): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        app(CustomerService::class)->restore($actor, static::department(), (int) $record->getKey());
                        Notification::make()->title('تمت استعادة العميل')->success()->send();
                    }),
            ])
                ->label('الأرشفة')
                ->icon('heroicon-o-archive-box')
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
            ->tooltip('خيارات العميل')
            ->dropdownMaxHeight('22rem')
            ->dropdownTeleport()
            ->dropdownFlip()
            ->extraDropdownAttributes(['class' => 'fi-customer-actions-dropdown']);
    }

    private static function importCustomersAction(): Action
    {
        return Action::make('importCustomers')
            ->label('استيراد Excel')
            ->icon('heroicon-o-arrow-up-tray')
            ->modalSubmitActionLabel('بدء الاستيراد')
            ->schema([
                FileUpload::make('file')
                    ->label('ملف الإكسيل (XLSX)')
                    ->required()
                    ->storeFiles(false)
                    ->acceptedFileTypes(['application/vnd.openxmlformats-officedocument.spreadsheetml.sheet'])
                    ->rules(['extensions:xlsx', 'max:10240'])
                    ->helperText('بحد أقصى 10 ميجابايت و5,000 صف. يجب مطابقة أعمدة القالب.'),
            ])
            ->action(function (array $data) {
                $actor = auth()->user();
                abort_unless($actor instanceof User && $data['file'] instanceof TemporaryUploadedFile, 403);
                $path = $data['file']->getRealPath();
                abort_unless(is_string($path), 422, 'تعذر الوصول إلى الملف المرفوع.');

                $result = app(CustomerSpreadsheetImportService::class)
                    ->import($actor, static::department(), $path);

                Notification::make()
                    ->title('اكتمل استيراد العملاء')
                    ->body('تم استيراد '.$result['successful'].' صف، وتعذر استيراد '.count($result['failed']).' صف.')
                    ->color($result['failed'] === [] ? 'success' : 'warning')
                    ->send();

                if ($result['failed'] === []) {
                    return;
                }

                $stream = fopen('php://temp', 'r+');
                fwrite($stream, "\xEF\xBB\xBF");
                fputcsv($stream, ['رقم الصف', ...['اسم العميل', 'رقم الهاتف', 'رمز الدولة', 'الجنسية'], 'سبب الخطأ'], ';', '"', '\\');
                foreach ($result['failed'] as $failure) {
                    $safeValues = array_map(static fn (string $value): string => preg_match('/^[\x00-\x20]*[=+\-@]/u', $value) === 1 ? "'{$value}" : $value, $failure['data']);
                    $safeError = preg_match('/^[\x00-\x20]*[=+\-@]/u', $failure['error']) === 1 ? "'{$failure['error']}" : $failure['error'];
                    fputcsv($stream, [$failure['row'], ...$safeValues, $safeError], ';', '"', '\\');
                }
                rewind($stream);
                $csv = stream_get_contents($stream);
                fclose($stream);

                return response()->streamDownload(static function () use ($csv): void {
                    echo $csv;
                }, 'customer-import-failures.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
            });
    }

    private static function downloadImportTemplateAction(): Action
    {
        return Action::make('downloadImportTemplate')
            ->label('تحميل القالب')
            ->icon('heroicon-o-document-arrow-down')
            ->color('gray')
            ->action(function () {
                $path = tempnam(sys_get_temp_dir(), 'cible-import-template-');
                abort_unless(is_string($path), 500);
                $writer = new Writer;
                $writer->openToFile($path);
                $writer->addRow(Row::fromValues(['اسم العميل', 'رقم الهاتف', 'رمز الدولة', 'الجنسية']));
                $writer->close();

                return response()->download($path, 'customer-import-template.xlsx')->deleteFileAfterSend();
            });
    }

    public static function createCustomerAction(): Action
    {
        return Action::make('createCustomer')
            ->label('إضافة عميل')
            ->modalSubmitActionLabel('إضافة العميل')
            ->schema(static::formFields())
            ->action(function (array $data, Schema $schema): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                $reviewed = (bool) ($data['reviewed_similar_names'] ?? false);
                unset($data['reviewed_similar_names']);

                FilamentValidationHandler::run(
                    fn () => app(CustomerService::class)->create($actor, static::department(), $data, $reviewed),
                    $schema,
                    'تعذر إضافة العميل'
                );
            })
            ->successNotificationTitle('تمت إضافة العميل');
    }

    /** @return array<int, Component> */
    private static function formFields(): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])
                ->schema([
                    TextInput::make('name')->label('الاسم')->required()->maxLength(255),
                    TextInput::make('nationality')->label('الجنسية')->maxLength(100),
                    PhoneField::make('رقم الهاتف', required: true)
                        ->columnSpanFull(),
                    Checkbox::make('reviewed_similar_names')
                        ->label('تأكيد: هذا عميل مختلف رغم تشابه الاسم.')
                        ->columnSpanFull(),
                ]),
        ];
    }

    private static function hasValidWhatsAppPhone(?string $phone): bool
    {
        return is_string($phone) && preg_match('/^\\+[1-9]\\d{7,14}$/', $phone) === 1;
    }

    private static function whatsAppUrl(string $phone): string
    {
        abort_unless(static::hasValidWhatsAppPhone($phone), 404);

        return 'https://wa.me/'.substr($phone, 1);
    }
}
