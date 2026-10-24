<?php

namespace App\Filament\Resources\RentalAuditEvents\Pages;

use App\Filament\Resources\RentalAuditEvents\RentalAuditEventResource;
use Filament\Resources\Pages\ListRecords;

class ListRentalAuditEvents extends ListRecords
{
    protected static string $resource = RentalAuditEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
