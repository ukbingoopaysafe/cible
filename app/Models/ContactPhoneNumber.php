<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContactPhoneNumber extends Model
{
    protected $fillable = [
        'phone_e164',
        'phone_country',
        'is_primary',
        'sort_order',
    ];

    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'sort_order' => 'integer',
        ];
    }

    public function phoneable(): MorphTo
    {
        return $this->morphTo();
    }
}
