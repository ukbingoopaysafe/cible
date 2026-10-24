<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalRequestCloseReason extends Model
{
    protected $fillable = ['key', 'name', 'is_other', 'is_active', 'sort_order'];

    protected function casts(): array
    {
        return ['is_other' => 'boolean', 'is_active' => 'boolean'];
    }
}
