<?php

namespace App\Filament\Resources\RentalRequests\Pages;

use App\Filament\Resources\RentalRequests\RentalRequestResource;
use Filament\Actions\CreateAction;
use Filament\Resources\Pages\ListRecords;

class ListRentalRequests extends ListRecords
{
    protected static string $resource = RentalRequestResource::class;

    protected function getHeaderActions(): array
    {
        return [
            CreateAction::make()
                ->label('إضافة طلب إيجار'),
        ];
    }
}
