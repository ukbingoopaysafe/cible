<?php

namespace App\Filament\Resources\ResaleAftercares\Pages;

use App\Filament\Resources\ResaleAftercares\ResaleAftercareResource;
use Filament\Resources\Pages\ListRecords;

class ListResaleAftercares extends ListRecords
{
    protected static string $resource = ResaleAftercareResource::class;

    protected function getHeaderActions(): array
    {
        return [];
    }
}
