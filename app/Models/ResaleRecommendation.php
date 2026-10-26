<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResaleRecommendation extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array { return ['unit_area' => 'decimal:2', 'asking_price' => 'decimal:2']; }
    public function request(): BelongsTo { return $this->belongsTo(ResaleRequest::class); }
    public function inspections(): HasMany { return $this->hasMany(ResaleFieldInspection::class, 'recommendation_id'); }
    public function viewings(): HasMany { return $this->hasMany(ResaleViewing::class, 'recommendation_id'); }
    public function creator(): BelongsTo { return $this->belongsTo(User::class, 'created_by'); }
}
