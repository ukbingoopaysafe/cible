<?php

namespace App\Support;

use Filament\AvatarProviders\Contracts\AvatarProvider;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Database\Eloquent\Model;

class DefaultAvatarProvider implements AvatarProvider
{
    public function get(Model | Authenticatable $record): string
    {
        return asset('images/brand/default-avatar.svg');
    }
}

