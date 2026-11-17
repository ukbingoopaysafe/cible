<?php

namespace App\Notifications;

use App\Notifications\Channels\WorkflowDatabaseChannel;
use Filament\Actions\Action;
use Filament\Notifications\Notification as FilamentNotification;
use Illuminate\Notifications\Notification;

class WorkflowEventNotification extends Notification
{
    /** @param array<string, mixed> $metadata */
    public function __construct(
        public readonly string $eventKey,
        public readonly string $title,
        public readonly string $body,
        public readonly string $sourceType,
        public readonly int|string $sourceId,
        public readonly string $url,
        public readonly array $metadata = [],
    ) {}

    /** @return list<class-string> */
    public function via($notifiable): array
    {
        return [WorkflowDatabaseChannel::class];
    }

    /** @return array<string, mixed> */
    public function toDatabasePayload(): array
    {
        $data = FilamentNotification::make()
            ->title($this->title)
            ->body($this->body)
            ->icon('heroicon-o-bell')
            ->iconColor('primary')
            ->actions([
                Action::make('open')
                    ->label('فتح السجل')
                    ->url($this->url)
                    ->markAsRead(),
            ])
            ->getDatabaseMessage();
        $data['workflow'] = [
            ...$this->metadata,
            'event_key' => $this->eventKey,
            'source_type' => $this->sourceType,
            'source_id' => $this->sourceId,
            'url' => $this->url,
        ];

        return $data;
    }
}
