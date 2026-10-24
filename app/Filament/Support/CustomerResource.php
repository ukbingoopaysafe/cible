<?php

namespace App\Filament\Support;

use App\Enums\Department;
use App\Exports\RentalCustomerExporter;
use App\Exports\ResaleCustomerExporter;
use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Filament\Resources\ResaleRequests\ResaleRequestResource;
use App\Models\User;
use App\Services\CustomerService;
use Filament\Actions\Action;
use Filament\Actions\ExportAction;
use Filament\Actions\Exports\Enums\ExportFormat;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @extends Resource<Model> */
abstract class CustomerResource extends Resource
{
    abstract public static function department(): Department;

    public static function getEloquentQuery(): Builder
    {
        $actor = auth()->user();

        return parent::getEloquentQuery()
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
            ->headerActions([
                ExportAction::make()
                    ->label('تصدير Excel')
                    ->exporter(static::department() === Department::Rental ? RentalCustomerExporter::class : ResaleCustomerExporter::class)
                    ->formats([ExportFormat::Xlsx])
                    ->columnMapping(false),
            ])
            ->columns([
                TextColumn::make('name')->label('اسم العميل')->searchable()->sortable(),
                TextColumn::make('phone_e164')->label('الهاتف الموحد')->searchable(),
                TextColumn::make('nationality')->label('الجنسية')->placeholder('—'),
                TextColumn::make('requests_count')->label('عدد الطلبات')->sortable(),
                TextColumn::make('created_at')->label('تاريخ التسجيل')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([
                Action::make('openWhatsApp')
                    ->label('فتح WhatsApp')
                    ->icon('heroicon-o-chat-bubble-left-right')
                    ->color('success')
                    ->url(fn (Model $record): string => static::whatsAppUrl($record->phone_e164))
                    ->openUrlInNewTab()
                    ->visible(fn (Model $record): bool => static::hasValidWhatsAppPhone($record->phone_e164)),
                Action::make('viewCustomerRequests')
                    ->label('طلبات العميل')
                    ->icon('heroicon-o-clipboard-document-list')
                    ->modalHeading(fn (Model $record): string => "طلبات العميل: {$record->name}")
                    ->modalSubmitAction(false)
                    ->modalCancelActionLabel('إغلاق')
                    ->modalContent(fn (Model $record) => view('filament.modals.customer-requests', [
                        'requests' => $record->requests()->with(['area', 'owner'])->latest()->get(),
                        'requestResource' => static::department() === Department::Rental ? RentalRequestResource::class : ResaleRequestResource::class,
                        'stageOptions' => RequestFormSchema::stageOptions(static::department()),
                    ])),
                Action::make('createCustomerRequest')
                    ->label(static::department() === Department::Rental ? 'إنشاء طلب إيجار' : 'إنشاء طلب مشتري')
                    ->icon('heroicon-o-plus-circle')
                    ->url(fn (Model $record): string => (static::department() === Department::Rental ? RentalRequestResource::class : ResaleRequestResource::class)::getUrl('create', ['customer_id' => $record->getKey()])),
                Action::make('editCustomer')
                    ->label('تعديل بيانات العميل')
                    ->icon('heroicon-o-pencil-square')
                    ->schema(static::formFields())
                    ->fillForm(fn (Model $record): array => [
                        'name' => $record->name,
                        'phone' => $record->phone_e164,
                        'phone_country' => $record->phone_country,
                        'nationality' => $record->nationality,
                    ])
                    ->action(function (Model $record, array $data): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        $reviewed = (bool) ($data['reviewed_similar_names'] ?? false);
                        unset($data['reviewed_similar_names']);
                        app(CustomerService::class)->update(
                            $actor,
                            static::department(),
                            (int) $record->getKey(),
                            $data,
                            $reviewed,
                        );
                    })
                    ->successNotificationTitle('تم تحديث بيانات العميل'),
            ]);
    }

    public static function createCustomerAction(): Action
    {
        return Action::make('createCustomer')
            ->label('إضافة عميل')
            ->schema(static::formFields())
            ->action(function (array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                $reviewed = (bool) ($data['reviewed_similar_names'] ?? false);
                unset($data['reviewed_similar_names']);
                app(CustomerService::class)->create($actor, static::department(), $data, $reviewed);
            })
            ->successNotificationTitle('تمت إضافة العميل');
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private static function formFields(): array
    {
        return [
            TextInput::make('name')->label('الاسم')->required()->maxLength(255),
            TextInput::make('phone')->label('رقم الهاتف')->tel()->required()->maxLength(40),
            TextInput::make('phone_country')->label('رمز الدولة')->default('EG')->required()->length(2)
                ->helperText('رمز الدولة الدولي، مثل EG لمصر.'),
            TextInput::make('nationality')->label('الجنسية')->maxLength(100),
            Checkbox::make('reviewed_similar_names')->label('راجعت تنبيه تشابه الاسم وأؤكد أن هذا عميل مختلف.'),
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
