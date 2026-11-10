<?php

namespace Tests\Fixtures\Livewire;

use Filament\Forms\Components\DatePicker;
use Filament\Forms\Components\DateTimePicker;
use Filament\Forms\Components\TimePicker;
use Filament\Schemas\Concerns\InteractsWithSchemas;
use Filament\Schemas\Contracts\HasSchemas;
use Filament\Schemas\Schema;
use Livewire\Component;

class DateTimePickerRenderingComponent extends Component implements HasSchemas
{
    use InteractsWithSchemas;

    public array $data = [];

    public function form(Schema $schema): Schema
    {
        return $schema
            ->statePath('data')
            ->components([
                DateTimePicker::make('scheduled_at')
                    ->label('موعد')
                    ->seconds(false),
                DateTimePicker::make('starts_at_with_seconds')
                    ->label('موعد بالثواني')
                    ->seconds(),
                DatePicker::make('deal_date')
                    ->label('تاريخ الصفقة'),
                TimePicker::make('reminder_time')
                    ->label('وقت التذكير'),
            ]);
    }

    public function render(): \Illuminate\Contracts\View\View
    {
        $schema = $this->getSchema('form');
        $dateTimePicker = $schema->getComponent('scheduled_at');
        $datePicker = $schema->getComponent('deal_date');

        return view('test-fixtures::date-time-picker', [
            'dateTimePickerMetadata' => [
                'locale' => $dateTimePicker->getLocale(),
                'format' => $dateTimePicker->getFormat(),
                'displayFormat' => $dateTimePicker->getDisplayFormat(),
                'timezone' => $dateTimePicker->getTimezone(),
                'native' => $dateTimePicker->isNative(),
            ],
            'datePickerMetadata' => [
                'locale' => $datePicker->getLocale(),
                'format' => $datePicker->getFormat(),
                'displayFormat' => $datePicker->getDisplayFormat(),
                'native' => $datePicker->isNative(),
            ],
        ]);
    }
}
