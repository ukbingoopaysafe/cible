<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResaleRequestReassignment extends Model
{
    protected $fillable = [
        'request_id', 'requested_by', 'from_user_id', 'to_user_id', 'reason',
        'status', 'decided_by', 'decided_at',
    ];

    protected function casts(): array
    {
        return ['decided_at' => 'datetime'];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ResaleRequest::class);
    }

    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function target(): BelongsTo
    {
        return $this->belongsTo(User::class, 'to_user_id');
    }

    public function decider(): BelongsTo
    {
        return $this->belongsTo(User::class, 'decided_by');
    }
}
