<?php

namespace App\Filament\Resources\ResaleCustomers\Pages;

use App\Filament\Resources\ResaleCustomers\ResaleCustomerResource;
use Filament\Resources\Pages\ListRecords;

class ListResaleCustomers extends ListRecords
{
    protected static string $resource = ResaleCustomerResource::class;

    protected function getHeaderActions(): array
    {
        return [ResaleCustomerResource::createCustomerAction()];
    }
}
