<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class RentalLeadSource extends Model
{
    protected $fillable = ['name', 'is_other', 'is_active'];

    protected function casts(): array
    {
        return ['is_other' => 'boolean', 'is_active' => 'boolean'];
    }
}
