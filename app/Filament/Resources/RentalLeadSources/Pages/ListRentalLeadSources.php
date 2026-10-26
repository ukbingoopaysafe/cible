<?php

namespace App\Filament\Resources\RentalLeadSources\Pages;

use App\Filament\Resources\RentalLeadSources\RentalLeadSourceResource;
use Filament\Resources\Pages\ListRecords;

class ListRentalLeadSources extends ListRecords
{
    protected static string $resource = RentalLeadSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [RentalLeadSourceResource::createAction()];
    }
}
