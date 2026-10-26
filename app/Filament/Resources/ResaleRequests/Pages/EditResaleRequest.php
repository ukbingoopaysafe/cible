<?php

namespace App\Filament\Resources\ResaleRequests\Pages;

use App\Enums\Department;
use App\Filament\Resources\ResaleRequests\ResaleRequestResource;
use App\Models\ResaleRequest;
use App\Models\User;
use App\Services\RequestService;
use Filament\Resources\Pages\EditRecord;
use Illuminate\Database\Eloquent\Model;

class EditResaleRequest extends EditRecord
{
    protected static string $resource = ResaleRequestResource::class;

    public function form(\Filament\Schemas\Schema $schema): \Filament\Schemas\Schema
    {
        return ResaleRequestResource::editForm($schema);
    }

    protected function handleRecordUpdate(Model $record, array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User && $record instanceof ResaleRequest, 403);

        return app(RequestService::class)->updateDetails($actor, Department::Resale, (int) $record->getKey(), $data);
    }
}
