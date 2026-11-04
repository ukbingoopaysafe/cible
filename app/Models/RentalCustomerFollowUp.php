<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalCustomerFollowUp extends Model
{
    protected $table = 'rental_aftercare';

    protected $guarded = ['id'];
    protected function casts(): array { return ['next_follow_up_at' => 'datetime']; }
    public function customer(): BelongsTo { return $this->belongsTo(RentalCustomer::class, 'customer_id'); }
    public function owner(): BelongsTo { return $this->belongsTo(User::class, 'owner_user_id'); }
    public function activities(): HasMany { return $this->hasMany(RentalCustomerFollowUpActivity::class, 'aftercare_id'); }
}
