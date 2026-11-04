<?php

namespace App\Models;

use App\Models\Concerns\HasCustomerIdentity;
use App\Models\Concerns\HasContactPhoneNumbers;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;

class ResaleCustomer extends Model
{
    use HasCustomerIdentity;
    use HasContactPhoneNumbers;
    use SoftDeletes;

    public function requests(): HasMany
    {
        return $this->hasMany(ResaleRequest::class, 'customer_id');
    }

    public function customerFollowUp(): HasOne
    {
        return $this->hasOne(ResaleCustomerFollowUp::class, 'customer_id');
    }
}
