<?php

namespace App\Models;

use App\Models\Concerns\HasCustomerIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalCustomer extends Model
{
    use HasCustomerIdentity;

    public function requests(): HasMany
    {
        return $this->hasMany(RentalRequest::class, 'customer_id');
    }
}
