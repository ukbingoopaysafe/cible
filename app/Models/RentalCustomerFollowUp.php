<?php

namespace App\Models;

use App\Enums\CustomerFollowUpCategory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalCustomerFollowUp extends Model
{
    protected $table = 'rental_aftercare';

    protected $guarded = ['id'];
    protected function casts(): array { return ['next_follow_up_at' => 'datetime', 'category' => CustomerFollowUpCategory::class]; }
    public function customer(): BelongsTo { return $this->belongsTo(RentalCustomer::class, 'customer_id'); }
    public function legacyCustomer(): BelongsTo { return $this->belongsTo(RentalCustomer::class, 'legacy_customer_id'); }
    public function request(): BelongsTo { return $this->belongsTo(RentalRequest::class, 'request_id'); }
    public function deal(): BelongsTo { return $this->belongsTo(RentalDeal::class, 'deal_id'); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function activities(): HasMany { return $this->hasMany(RentalCustomerFollowUpActivity::class, 'aftercare_id'); }
}
