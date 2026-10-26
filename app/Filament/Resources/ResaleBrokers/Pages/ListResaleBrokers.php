<?php

namespace App\Filament\Resources\ResaleBrokers\Pages;

use App\Filament\Resources\ResaleBrokers\ResaleBrokerResource;
use Filament\Resources\Pages\ListRecords;

class ListResaleBrokers extends ListRecords
{
    protected static string $resource = ResaleBrokerResource::class;

    protected function getHeaderActions(): array
    {
        return [ResaleBrokerResource::createBrokerAction()];
    }
}
