<?php

namespace App\Filament\Resources\RentalCustomerFollowUps\Pages;

use App\Filament\Resources\RentalCustomerFollowUps\RentalCustomerFollowUpResource;
use Filament\Resources\Pages\ListRecords;

class ListRentalCustomerFollowUps extends ListRecords
{
    protected static string $resource = RentalCustomerFollowUpResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
