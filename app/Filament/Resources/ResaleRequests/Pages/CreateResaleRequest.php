<?php

namespace App\Filament\Resources\ResaleRequests\Pages;

use App\Enums\Department;
use App\Filament\Resources\ResaleRequests\ResaleRequestResource;
use App\Filament\Support\CreateSectionRequest;

class CreateResaleRequest extends CreateSectionRequest
{
    protected static string $resource = ResaleRequestResource::class;

    protected static function department(): Department
    {
        return Department::Resale;
    }
}
