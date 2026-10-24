<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalDeal extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return ['deal_date' => 'date', 'agreed_total_value' => 'decimal:2', 'unit_area' => 'decimal:2'];
    }

    public function request(): BelongsTo { return $this->belongsTo(RentalRequest::class); }
    public function customer(): BelongsTo { return $this->belongsTo(RentalCustomer::class); }
    public function broker(): BelongsTo { return $this->belongsTo(RentalBroker::class); }
    public function commissions(): HasMany { return $this->hasMany(RentalDealCommission::class, 'deal_id'); }
}
