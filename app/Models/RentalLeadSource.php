<?php

namespace App\Models;

use App\Enums\LeadSourceType;
use Illuminate\Database\Eloquent\Model;

class RentalLeadSource extends Model
{
    protected $fillable = ['name', 'type', 'is_active'];

    protected function casts(): array
    {
        return ['type' => LeadSourceType::class, 'is_active' => 'boolean'];
    }
}
