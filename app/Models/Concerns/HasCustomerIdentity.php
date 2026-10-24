<?php

namespace App\Models\Concerns;

trait HasCustomerIdentity
{
    protected $fillable = [
        'name',
        'nationality',
    ];
}
