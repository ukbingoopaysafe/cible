<?php

namespace App\Filament\Resources\ResaleCloseReasons\Pages;

use App\Filament\Resources\ResaleCloseReasons\ResaleCloseReasonResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListResaleCloseReasons extends ListRecords
{
    protected static string $resource = ResaleCloseReasonResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [ResaleCloseReasonResource::createAction()];
    }
}
