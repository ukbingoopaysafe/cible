<?php

namespace App\Models;

use App\Models\Concerns\HasCustomerIdentity;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResaleCustomer extends Model
{
    use HasCustomerIdentity;
    use SoftDeletes;

    public function requests(): HasMany
    {
        return $this->hasMany(ResaleRequest::class, 'customer_id');
    }

    public function aftercare(): HasOne
    {
        return $this->hasOne(ResaleAftercare::class, 'customer_id');
    }
}
