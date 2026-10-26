<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RentalViewing extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['scheduled_at' => 'datetime']; }
    public function request(): BelongsTo { return $this->belongsTo(RentalRequest::class); }
    public function recommendation(): BelongsTo { return $this->belongsTo(RentalRecommendation::class, 'recommendation_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
