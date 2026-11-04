<?php

namespace App\Filament\Resources\ContactChangeRequests\Pages;

use App\Filament\Resources\ContactChangeRequests\ContactChangeRequestResource;
use Filament\Resources\Pages\ListRecords;

class ListContactChangeRequests extends ListRecords
{
    protected static string $resource = ContactChangeRequestResource::class;
}
