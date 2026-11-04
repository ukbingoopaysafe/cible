<?php

namespace App\Filament\Support;

use App\Enums\Department;
use App\Models\User;
use App\Services\BrokerService;
use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;
use Filament\Schemas\Components\Utilities\Get;
use Filament\Schemas\Components\Utilities\Set;
use Illuminate\Database\Eloquent\Model;

final class BrokerFormSchema
{
    /** @return array<int, Component> */
    public static function fields(Department $department, bool $editing = false): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])
                ->schema([
                    TextInput::make('name')
                        ->label('اسم الوسيط')
                        ->required()
                        ->maxLength(255)
                        ->live(debounce: 500)
                        ->afterStateUpdated(function (Set $set): void {
                            $set('reviewed_similar_names', false);
                        })
                        ->columnSpanFull(),
                    ContactPhonesField::make(required: false)
                        ->columnSpanFull(),
                    Checkbox::make('reviewed_similar_names')
                        ->label('راجعت تنبيه تشابه الاسم وأؤكد أن هذا وسيط مختلف.')
                        ->helperText($editing ? 'يظهر التنبيه عند حفظ اسم قد يتشابه مع وسيط آخر.' : 'يظهر التنبيه عند إضافة اسم قد يتشابه مع وسيط آخر.')
                        ->visible(function (Get $get, ?Model $record = null) use ($department): bool {
                            $actor = auth()->user();

                            return $actor instanceof User && app(BrokerService::class)->hasSimilarNames(
                                $actor,
                                $department,
                                (string) $get('name'),
                                $record ? (int) $record->getKey() : null,
                            );
                        })
                        ->columnSpanFull(),
                ]),
        ];
    }
}
