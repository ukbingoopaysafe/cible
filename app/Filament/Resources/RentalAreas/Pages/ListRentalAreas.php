<?php

namespace App\Filament\Resources\RentalAreas\Pages;

use App\Filament\Resources\RentalAreas\RentalAreaResource;
use Filament\Resources\Pages\ListRecords;

class ListRentalAreas extends ListRecords
{
    protected static string $resource = RentalAreaResource::class;

    protected function getHeaderActions(): array
    {
        return [RentalAreaResource::createAreaAction()];
    }
}
