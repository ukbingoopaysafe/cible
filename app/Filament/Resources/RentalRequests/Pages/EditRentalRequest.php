<?php

namespace App\Filament\Resources\RentalRequests\Pages;

use App\Enums\Department;
use App\Filament\Resources\RentalRequests\RentalRequestResource;
use App\Models\RentalRequest;
use App\Models\User;
use App\Services\RequestService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditRentalRequest extends EditRecord
{
    protected static string $resource = RentalRequestResource::class;

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return RentalRequestResource::editForm($schema);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $record instanceof RentalRequest, 403);

        return app(RequestService::class)->updateDetails($actor, Department::Rental, (int) $record->getKey(), $data);
    }
}
