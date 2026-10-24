<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalDealCommission extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['agreed_value' => 'decimal:2']; }
    public function deal(): BelongsTo { return $this->belongsTo(RentalDeal::class, 'deal_id'); }
}
