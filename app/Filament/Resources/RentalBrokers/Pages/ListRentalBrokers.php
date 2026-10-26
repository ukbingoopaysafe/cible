<?php

namespace App\Filament\Resources\RentalBrokers\Pages;

use App\Filament\Resources\RentalBrokers\RentalBrokerResource;
use Filament\Resources\Pages\ListRecords;

class ListRentalBrokers extends ListRecords
{
    protected static string $resource = RentalBrokerResource::class;

    protected function getHeaderActions(): array
    {
        return [RentalBrokerResource::createBrokerAction()];
    }
}
