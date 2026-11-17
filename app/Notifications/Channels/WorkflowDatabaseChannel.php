<?php

namespace App\Notifications\Channels;

use App\Notifications\WorkflowEventNotification;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class WorkflowDatabaseChannel
{
    public function send(Model $notifiable, WorkflowEventNotification $notification): void
    {
        $now = now();

        DB::table('notifications')->insertOrIgnore([
            'id' => (string) Str::uuid(),
            'type' => $notification::class,
            'notifiable_type' => $notifiable->getMorphClass(),
            'notifiable_id' => $notifiable->getKey(),
            'data' => json_encode($notification->toDatabasePayload(), JSON_THROW_ON_ERROR | JSON_UNESCAPED_UNICODE),
            'read_at' => null,
            'created_at' => $now,
            'updated_at' => $now,
            'workflow_event_key' => $notification->eventKey,
        ]);
    }
}
