<?php

namespace App\Filament\Resources\ResaleLeadSources\Pages;

use App\Filament\Resources\ResaleLeadSources\ResaleLeadSourceResource;
use Filament\Resources\Pages\ListRecords;

class ListResaleLeadSources extends ListRecords
{
    protected static string $resource = ResaleLeadSourceResource::class;

    protected function getHeaderActions(): array
    {
        return [ResaleLeadSourceResource::createAction()];
    }
}
