<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResaleFieldInspection extends Model
{
    protected $guarded = ['id'];
    protected function casts(): array { return ['inspected_at' => 'datetime']; }
    public function recommendation(): BelongsTo { return $this->belongsTo(ResaleRecommendation::class, 'recommendation_id'); }
    public function recorder(): BelongsTo { return $this->belongsTo(User::class, 'recorded_by'); }
}
