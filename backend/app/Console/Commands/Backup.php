<?php

namespace App\Console\Commands;

use App\Services\Ops\BackupFailed;
use App\Services\Ops\BackupService;
use App\Services\Ops\StaffAlert;
use Illuminate\Console\Command;

class Backup extends Command
{
    protected $signature = 'guul:backup';

    protected $description = 'Make a gzipped database backup in storage/app/backups and keep only the newest ones';

    public function handle(StaffAlert $alert): int
    {
        try {
            $path = BackupService::fromConfig()->run();
        } catch (BackupFailed $e) {
            $alert->send('Backup failed: '.$e->getMessage());
            $this->error($e->getMessage());

            return self::FAILURE;
        }
        $this->info('Backup written: '.basename($path).' ('.number_format((int) filesize($path)).' bytes)');

        return self::SUCCESS;
    }
}
