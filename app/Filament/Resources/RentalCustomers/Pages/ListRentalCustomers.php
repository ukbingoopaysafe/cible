<?php

namespace App\Filament\Resources\RentalCustomers\Pages;

use App\Filament\Resources\RentalCustomers\RentalCustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListRentalCustomers extends ListRecords
{
    protected static string $resource = RentalCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [RentalCustomerResource::createCustomerAction()];
    }
}
