<?php

namespace App\Console\Commands;

use App\Services\WorkflowNotificationService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Log;

class ProcessWorkflowNotifications extends Command
{
    protected $signature = 'workflow:process-notifications';

    protected $description = 'Process due request, pause, and customer follow-up notifications.';

    public function handle(WorkflowNotificationService $notifications): int
    {
        $stats = $notifications->processDueEvents(100);
        $context = [
            'processed' => $stats['processed'],
            'pending' => $stats['pending'],
            'oldest_due_minutes' => $stats['oldest_due_minutes'],
        ];

        if ($stats['pending'] > 0) {
            Log::info('Workflow notification batch processed.', $context);
        }

        if ($stats['oldest_due_minutes'] > 5) {
            Log::warning('Workflow notification backlog exceeded five minutes.', $context);
        }

        $this->info("Processed {$stats['processed']} workflow events; {$stats['pending']} pending.");

        return self::SUCCESS;
    }
}
