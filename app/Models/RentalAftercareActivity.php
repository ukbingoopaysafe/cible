<?php

namespace App\Models;

use App\Enums\ContactActivityChannel;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalAftercareActivity extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['channel' => ContactActivityChannel::class, 'occurred_at' => 'datetime', 'next_follow_up_at' => 'datetime']; }
    public function aftercare(): BelongsTo { return $this->belongsTo(RentalAftercare::class, 'aftercare_id'); }
    public function deal(): BelongsTo { return $this->belongsTo(RentalDeal::class, 'deal_id'); }
    public function actor(): BelongsTo { return $this->belongsTo(User::class, 'actor_user_id'); }
}
