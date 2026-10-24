<?php

namespace App\Filament\Support;

use App\Enums\Department;
use App\Models\User;
use App\Services\BrokerService;
use Filament\Actions\Action;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\TextInput;
use Filament\Resources\Resource;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Tables\Columns\TextColumn;
use Filament\Tables\Table;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/** @extends Resource<Model> */
abstract class BrokerResource extends Resource
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
            ->columns([
                TextColumn::make('name')->label('اسم الوسيط')->searchable()->sortable(),
                TextColumn::make('phone_e164')->label('الهاتف الموحد')->searchable()->placeholder('—'),
                TextColumn::make('phone_country')->label('الدولة')->placeholder('—'),
                TextColumn::make('requests_count')->label('عدد الطلبات')->sortable(),
                TextColumn::make('created_at')->label('تاريخ التسجيل')->dateTime('d/m/Y H:i')->sortable(),
            ])
            ->recordActions([
                Action::make('editBroker')
                    ->label('تعديل بيانات الوسيط')
                    ->icon('heroicon-o-pencil-square')
                    ->schema(static::formFields(editing: true))
                    ->fillForm(fn (Model $record): array => [
                        'name' => $record->name,
                        'phone' => $record->phone_e164,
                        'phone_country' => $record->phone_country,
                    ])
                    ->action(function (Model $record, array $data): void {
                        $actor = auth()->user();
                        abort_unless($actor instanceof User, 403);
                        $reviewed = (bool) ($data['reviewed_similar_names'] ?? false);
                        unset($data['reviewed_similar_names']);
                        app(BrokerService::class)->update(
                            $actor,
                            static::department(),
                            (int) $record->getKey(),
                            $data,
                            $reviewed,
                        );
                    })
                    ->successNotificationTitle('تم تحديث بيانات الوسيط'),
            ]);
    }

    public static function createBrokerAction(): Action
    {
        return Action::make('createBroker')
            ->label('إضافة وسيط')
            ->schema(static::formFields(editing: false))
            ->action(function (array $data): void {
                $actor = auth()->user();
                abort_unless($actor instanceof User, 403);
                $reviewed = (bool) ($data['reviewed_similar_names'] ?? false);
                unset($data['reviewed_similar_names']);
                app(BrokerService::class)->create($actor, static::department(), $data, $reviewed);
            })
            ->successNotificationTitle('تمت إضافة الوسيط');
    }

    /** @return array<int, \Filament\Forms\Components\Component> */
    private static function formFields(bool $editing): array
    {
        return [
            TextInput::make('name')->label('اسم الوسيط')->required()->maxLength(255),
            TextInput::make('phone')->label('رقم الهاتف')->tel()->maxLength(40)->helperText('يُوحّد النظام الرقم ويتحقق من عدم استخدامه لوسيط آخر في القسم.'),
            TextInput::make('phone_country')
                ->label('رمز الدولة')
                ->default('EG')
                ->length(2)
                ->helperText('رمز الدولة الدولي، مثل EG لمصر.')
                ->visible(fn (Get $get): bool => filled($get('phone')))
                ->required(fn (Get $get): bool => filled($get('phone')))
                ->dehydrated(fn (Get $get): bool => filled($get('phone'))),
            Checkbox::make('reviewed_similar_names')
                ->label('راجعت تنبيه تشابه الاسم وأؤكد أن هذا وسيط مختلف.')
                ->helperText($editing ? 'يظهر التنبيه عند حفظ اسم قد يتشابه مع وسيط آخر.' : 'يظهر التنبيه عند إضافة اسم قد يتشابه مع وسيط آخر.'),
        ];
    }
}
