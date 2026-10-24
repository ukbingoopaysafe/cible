<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResaleArea extends Model
{
    protected $fillable = ['governorate', 'name'];

    public function requests(): HasMany
    {
        return $this->hasMany(ResaleRequest::class, 'area_id');
    }
}
