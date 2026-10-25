<?php

namespace App\Models;

use App\Enums\ContactParty;
use App\Enums\RequestOutcome;
use App\Enums\RequestStatus;
use App\Enums\RequestUnitType;
use App\Enums\ResaleRequestStage;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResaleRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contact_party' => ContactParty::class,
            'status' => RequestStatus::class,
            'stage' => ResaleRequestStage::class,
            'outcome' => RequestOutcome::class,
            'unit_type' => RequestUnitType::class,
            'next_follow_up_at' => 'datetime',
            'paused_at' => 'datetime',
            'closed_at' => 'datetime',
            'area_min' => 'decimal:2',
            'area_max' => 'decimal:2',
            'budget_declared' => 'decimal:2',
            'budget_targeted' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(ResaleCustomer::class)->withTrashed();
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(ResaleBroker::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(ResaleArea::class, 'area_id')->withTrashed();
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(ResaleLeadSource::class, 'lead_source_id');
    }

    public function closeReason(): BelongsTo
    {
        return $this->belongsTo(ResaleRequestCloseReason::class, 'outcome_reason_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function reassignments(): HasMany
    {
        return $this->hasMany(ResaleRequestReassignment::class, 'request_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(ResaleActivity::class, 'resale_request_id');
    }

    public function deal(): \Illuminate\Database\Eloquent\Relations\HasOne
    {
        return $this->hasOne(ResaleDeal::class, 'request_id');
    }

    public function recommendations(): HasMany
    {
        return $this->hasMany(ResaleRecommendation::class, 'request_id');
    }

    public function viewings(): HasMany
    {
        return $this->hasMany(ResaleViewing::class, 'request_id');
    }
}
