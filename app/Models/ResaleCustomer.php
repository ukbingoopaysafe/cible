<?php

namespace App\Models;

use App\Models\Concerns\HasCustomerIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResaleCustomer extends Model
{
    use HasCustomerIdentity;

    public function requests(): HasMany
    {
        return $this->hasMany(ResaleRequest::class, 'customer_id');
    }
}
