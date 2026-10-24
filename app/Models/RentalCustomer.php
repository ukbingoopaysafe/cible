<?php

namespace App\Models;

use App\Models\Concerns\HasCustomerIdentity;
use Illuminate\Database\Eloquent\Model;

class RentalCustomer extends Model
{
    use HasCustomerIdentity;
}
