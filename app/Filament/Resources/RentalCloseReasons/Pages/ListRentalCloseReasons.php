<?php

namespace App\Filament\Resources\RentalCloseReasons\Pages;

use App\Filament\Resources\RentalCloseReasons\RentalCloseReasonResource;
use Filament\Actions\Action;
use Filament\Resources\Pages\ListRecords;

class ListRentalCloseReasons extends ListRecords
{
    protected static string $resource = RentalCloseReasonResource::class;

    /** @return array<Action> */
    protected function getHeaderActions(): array
    {
        return [RentalCloseReasonResource::createAction()];
    }
}
