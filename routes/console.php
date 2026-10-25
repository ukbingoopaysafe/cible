<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

$backupIsConfigured = static fn (): bool => app()->environment('production')
    && strlen((string) config('backup.backup.password')) >= 32
    && class_exists(\ZipArchive::class)
    && defined(\ZipArchive::class.'::EM_AES_256')
    && (bool) config('filesystems.disks.backup_offsite.bucket')
    && (bool) config('filesystems.disks.backup_offsite.key')
    && (bool) config('filesystems.disks.backup_offsite.secret');

Schedule::command('backup:run')
    ->dailyAt('04:15')
    ->timezone(config('app.timezone', 'Africa/Cairo'))
    ->withoutOverlapping()
    ->onOneServer()
    ->when($backupIsConfigured)
    ->appendOutputTo(storage_path('logs/backups.log'))
    ->onFailure(static fn () => Log::critical('The scheduled off-site database backup failed.'));

Schedule::command('backup:clean')
    ->dailyAt('04:35')
    ->timezone(config('app.timezone', 'Africa/Cairo'))
    ->withoutOverlapping()
    ->onOneServer()
    ->when($backupIsConfigured)
    ->appendOutputTo(storage_path('logs/backups.log'))
    ->onFailure(static fn () => Log::critical('The scheduled off-site backup cleanup failed.'));

Schedule::command('backup:monitor')
    ->dailyAt('04:50')
    ->timezone(config('app.timezone', 'Africa/Cairo'))
    ->withoutOverlapping()
    ->onOneServer()
    ->when($backupIsConfigured)
    ->appendOutputTo(storage_path('logs/backups.log'))
    ->onFailure(static fn () => Log::critical('The scheduled off-site backup health check failed.'));
