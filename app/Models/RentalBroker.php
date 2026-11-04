<?php

namespace App\Models;

use App\Models\Concerns\HasContactPhoneNumbers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalBroker extends Model
{
    use HasContactPhoneNumbers;

    protected $fillable = ['name'];

    public function requests(): HasMany
    {
        return $this->hasMany(RentalRequest::class, 'broker_id');
    }
}
