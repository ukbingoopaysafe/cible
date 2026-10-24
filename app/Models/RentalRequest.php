<?php

namespace App\Models;

use App\Enums\ContactParty;
use App\Enums\FinishingLevel;
use App\Enums\FloorPreference;
use App\Enums\RentalRequestStage;
use App\Enums\RentalTerm;
use App\Enums\RequestUnitType;
use App\Enums\RequestOutcome;
use App\Enums\RequestStatus;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class RentalRequest extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'contact_party' => ContactParty::class,
            'status' => RequestStatus::class,
            'stage' => RentalRequestStage::class,
            'outcome' => RequestOutcome::class,
            'rental_term' => RentalTerm::class,
            'rental_term_count' => 'integer',
            'unit_type' => RequestUnitType::class,
            'finishing' => FinishingLevel::class,
            'floor_preference' => FloorPreference::class,
            'household' => 'boolean',
            'elevator_required' => 'boolean',
            'next_follow_up_at' => 'datetime',
            'paused_at' => 'datetime',
            'closed_at' => 'datetime',
            'budget_declared' => 'decimal:2',
            'budget_targeted' => 'decimal:2',
        ];
    }

    public function customer(): BelongsTo
    {
        return $this->belongsTo(RentalCustomer::class);
    }

    public function broker(): BelongsTo
    {
        return $this->belongsTo(RentalBroker::class);
    }

    public function area(): BelongsTo
    {
        return $this->belongsTo(RentalArea::class, 'area_id');
    }

    public function leadSource(): BelongsTo
    {
        return $this->belongsTo(RentalLeadSource::class, 'lead_source_id');
    }

    public function closeReason(): BelongsTo
    {
        return $this->belongsTo(RentalRequestCloseReason::class, 'outcome_reason_id');
    }

    public function owner(): BelongsTo
    {
        return $this->belongsTo(User::class, 'owner_user_id');
    }

    public function reassignments(): HasMany
    {
        return $this->hasMany(RentalRequestReassignment::class, 'request_id');
    }

    public function activities(): HasMany
    {
        return $this->hasMany(RentalActivity::class, 'rental_request_id');
    }
}
