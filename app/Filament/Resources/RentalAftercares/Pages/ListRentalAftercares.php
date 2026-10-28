<?php

namespace App\Filament\Resources\RentalAftercares\Pages;

use App\Filament\Resources\RentalAftercares\RentalAftercareResource;
use Filament\Resources\Pages\ListRecords;

class ListRentalAftercares extends ListRecords
{
    protected static string $resource = RentalAftercareResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
