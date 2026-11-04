<?php

namespace App\Filament\Support;

use Filament\Forms\Components\Checkbox;
use Filament\Forms\Components\Component;
use Filament\Forms\Components\TextInput;
use Filament\Schemas\Components\Grid;

final class BrokerFormSchema
{
    /** @return array<int, Component> */
    public static function fields(bool $editing = false): array
    {
        return [
            Grid::make(['default' => 1, 'sm' => 2])
                ->schema([
                    TextInput::make('name')
                        ->label('اسم الوسيط')
                        ->required()
                        ->maxLength(255)
                        ->columnSpanFull(),
                    ContactPhonesField::make(required: false)
                        ->columnSpanFull(),
                    Checkbox::make('reviewed_similar_names')
                        ->label('راجعت تنبيه تشابه الاسم وأؤكد أن هذا وسيط مختلف.')
                        ->helperText($editing ? 'يظهر التنبيه عند حفظ اسم قد يتشابه مع وسيط آخر.' : 'يظهر التنبيه عند إضافة اسم قد يتشابه مع وسيط آخر.')
                        ->columnSpanFull(),
                ]),
        ];
    }
}
