<?php

namespace App\Backup\Cleanup;

use Carbon\Carbon;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\Tasks\Cleanup\CleanupStrategy;

class KeepBackupsForConfiguredDays extends CleanupStrategy
{
    public function deleteOldBackups(BackupCollection $backups): void
    {
        $cutoff = Carbon::now()->subDays((int) $this->config->get('backup.cleanup.keep_days', 30));

        $backups
            ->filter(fn ($backup): bool => $backup->date()->isBefore($cutoff))
            ->each(fn ($backup) => $backup->delete());
    }
}
