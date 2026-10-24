<?php

namespace App\Models;

use App\Models\Concerns\HasCustomerIdentity;
use Illuminate\Database\Eloquent\Model;

class ResaleCustomer extends Model
{
    use HasCustomerIdentity;
}
