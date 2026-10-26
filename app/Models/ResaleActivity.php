<?php

namespace App\Models;

use App\Enums\ContactActivityChannel;
use App\Enums\ContactParty;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResaleActivity extends Model
{
    protected $guarded = ['id'];

    protected function casts(): array
    {
        return [
            'channel' => ContactActivityChannel::class,
            'contact_party' => ContactParty::class,
            'occurred_at' => 'datetime',
        ];
    }

    public function request(): BelongsTo
    {
        return $this->belongsTo(ResaleRequest::class, 'resale_request_id');
    }

    public function actor(): BelongsTo
    {
        return $this->belongsTo(User::class, 'actor_user_id');
    }
}
