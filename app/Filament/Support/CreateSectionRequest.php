<?php

namespace App\Filament\Support;

use App\Enums\Department;
use App\Models\User;
use App\Services\RequestService;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;

abstract class CreateSectionRequest extends CreateRecord
{
    abstract protected static function department(): Department;

    protected function handleRecordCreation(array $data): Model
    {
        $actor = auth()->user();
        abort_unless($actor instanceof User, 403);

        return app(RequestService::class)->create($actor, static::department(), $data);
    }
}
