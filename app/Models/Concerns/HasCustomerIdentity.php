<?php

namespace App\Models\Concerns;

trait HasCustomerIdentity
{
    protected function initializeHasCustomerIdentity(): void
    {
        $this->fillable = array_values(array_unique([
            ...$this->fillable,
            'name',
            'nationality',
        ]));
    }
}
