<?php

namespace App\Models;

use App\Enums\ContactActivityChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalCustomerFollowUpActivity extends Model
{
    protected $table = 'rental_aftercare_activities';

    protected $guarded = ['id'];
    protected function casts(): array { return ['channel' => ContactActivityChannel::class, 'occurred_at' => 'datetime', 'next_follow_up_at' => 'datetime']; }
    public function customerFollowUp(): BelongsTo { return $this->belongsTo(RentalCustomerFollowUp::class, 'aftercare_id'); }
    public function deal(): BelongsTo { return $this->belongsTo(RentalDeal::class, 'deal_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}
