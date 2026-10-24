<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalBroker extends Model
{
    protected $fillable = ['name', 'phone_e164', 'phone_country'];

    public function requests(): HasMany
    {
        return $this->hasMany(RentalRequest::class, 'broker_id');
    }
}
