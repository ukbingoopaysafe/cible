<?php

namespace App\Filament\Resources\RentalRequests\Pages;

use App\Enums\Department;
use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Filament\Support\CreateSectionRequest;

class CreateRentalRequest extends CreateSectionRequest
{
    protected static string $resource = RentalRequestResource::class;

    protected static function department(): Department
    {
        return Department::Rental;
    }
}
