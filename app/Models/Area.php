<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\SoftDeletes;

class Area extends Model
{
    use SoftDeletes;

    protected $table = 'areas';

    protected $fillable = [
        'governorate',
        'name',
    ];

    public function rentalRequests(): HasMany
    {
        return $this->hasMany(RentalRequest::class, 'area_id');
    }

    public function resaleRequests(): HasMany
    {
        return $this->hasMany(ResaleRequest::class, 'area_id');
    }
}
