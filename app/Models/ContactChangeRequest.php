<?php

namespace App\Models;

use App\Enums\ContactChangeRequestStatus;
use App\Enums\Department;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ContactChangeRequest extends Model
{
    protected $fillable = [
        'subject_type',
        'subject_id',
        'department',
        'requested_by',
        'before_values',
        'after_values',
        'reviewed_similar_names',
        'status',
        'decided_by',
        'reason',
        'decided_at',
    ];

    protected function casts(): array
    {
        return [
            'department' => Department::class,
            'before_values' => 'array',
            'after_values' => 'array',
            'reviewed_similar_names' => 'boolean',
            'status' => ContactChangeRequestStatus::class,
            'decided_at' => 'datetime',
        ];
    }

    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
