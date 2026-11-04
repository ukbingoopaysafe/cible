<?php

namespace App\Filament\Resources\ResaleCustomerFollowUps\Pages;

use App\Filament\Resources\ResaleCustomerFollowUps\ResaleCustomerFollowUpResource;
use Filament\Resources\Pages\ListRecords;

class ListResaleCustomerFollowUps extends ListRecords
{
    protected static string $resource = ResaleCustomerFollowUpResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
