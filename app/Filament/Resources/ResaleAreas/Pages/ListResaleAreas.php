<?php

namespace App\Filament\Resources\ResaleAreas\Pages;

use App\Filament\Resources\ResaleAreas\ResaleAreaResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListResaleAreas extends ListRecords
{
    protected static string $resource = ResaleAreaResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [ResaleAreaResource::createAreaAction()];
    }
}
