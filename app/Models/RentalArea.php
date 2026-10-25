<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class RentalArea extends Model
{
    use SoftDeletes;

    protected $fillable = ['governorate', 'name'];

    public function requests(): HasMany
    {
        return $this->hasMany(RentalRequest::class, 'area_id');
    }
}
