<?php

namespace App\Filament\Resources\ResaleAuditEvents\Pages;

use App\Filament\Resources\ResaleAuditEvents\ResaleAuditEventResource;
use Filament\Resources\Pages\ListRecords;

class ListResaleAuditEvents extends ListRecords
{
    protected static string $resource = ResaleAuditEventResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
