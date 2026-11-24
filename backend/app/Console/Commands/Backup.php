<?php

namespace App\Console\Commands;

use App\Contracts\StaffNotifier;
use App\Services\Ops\BackupFailed;
use App\Services\Ops\BackupService;
use App\Services\Ops\StaffAlert;
use Illuminate\Console\Command;

class Backup extends Command
{
    protected $signature = 'guul:backup';

    protected $description = 'Make a gzipped database backup in storage/app/backups and keep only the newest ones';

    public function handle(StaffAlert $alert, StaffNotifier $notifier, BackupService $backups): int
    {
        try {
            $path = $backups->run();
        } catch (BackupFailed $e) {
            $alert->send('Backup failed: '.$e->getMessage());
            $notifier->notifyAll('manage-settings', 'Backup failed: '.$e->getMessage(), 'error');
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Backup written: '.basename($path).' ('.number_format((int) filesize($path)).' bytes)');

        return self::SUCCESS;
    }
}
