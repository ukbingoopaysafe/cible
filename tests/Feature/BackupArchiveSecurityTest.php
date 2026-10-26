<?php

namespace Tests\Feature;

use App\Backup\Cleanup\KeepBackupsForConfiguredDays;
use Carbon\Carbon;
use Illuminate\Support\Facades\Storage;
use Spatie\Backup\BackupDestination\Backup;
use Spatie\Backup\BackupDestination\BackupCollection;
use Spatie\Backup\Events\BackupZipWasCreated;
use Spatie\Backup\Listeners\EncryptBackupArchive;
use Tests\TestCase;
use ZipArchive;

class BackupArchiveSecurityTest extends TestCase
{
    public function test_backup_zip_listener_encrypts_archive_entries_with_aes256(): void
    {
        if (! defined(ZipArchive::class.'::EM_AES_256')) {
            $this->markTestSkipped('This PHP ZipArchive build does not support AES-256 encryption.');
        }

        $path = tempnam(sys_get_temp_dir(), 'cible-backup-');
        $zip = new ZipArchive;
        $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
        $zip->addFromString('database.sql', 'sensitive-test-payload');
        $zip->close();

        config([
            'backup.backup.password' => 'test-only-strong-password',
            'backup.backup.encryption' => 'default',
        ]);

        try {
            app(EncryptBackupArchive::class)->handle(new BackupZipWasCreated($path));

            $encrypted = new ZipArchive;
            $this->assertTrue($encrypted->open($path));
            $encrypted->setPassword('test-only-strong-password');
            $this->assertSame('sensitive-test-payload', $encrypted->getFromName('database.sql'));
            $encrypted->setPassword('wrong-password');
            $this->assertFalse($encrypted->getFromName('database.sql'));
            $encrypted->close();
        } finally {
            @unlink($path);
        }
    }

    public function test_configured_retention_removes_backups_older_than_thirty_days_only(): void
    {
        Carbon::setTestNow('2026-10-25 12:00:00');
        Storage::fake('backup-test');
        $disk = Storage::disk('backup-test');

        // Use standard backup filenames so their embedded timestamps are deterministic.
        $old = new Backup($disk, '2026-09-24-12-00-00.zip');
        $recent = new Backup($disk, '2026-09-26-12-00-00.zip');
        $disk->put($old->path(), 'old');
        $disk->put($recent->path(), 'recent');

        config(['backup.cleanup.keep_days' => 30]);
        app(KeepBackupsForConfiguredDays::class)->deleteOldBackups(new BackupCollection([$old, $recent]));

        $this->assertFalse($disk->exists($old->path()));
        $this->assertTrue($disk->exists($recent->path()));
        Carbon::setTestNow();
    }
}
