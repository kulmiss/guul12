<?php

namespace App\Jobs;

use App\Contracts\StaffNotifier;
use App\Models\User;
use App\Services\Ops\BackupFailed;
use App\Services\Ops\BackupService;
use App\Services\Ops\StaffAlert;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * "Run backup now" from Nova: the same backup as the nightly one, in the background. The staff member
 * who asked gets the result; a failure also goes to the staff alert channel, like the nightly one.
 */
class RunBackup implements ShouldQueue
{
    use Queueable;

    public int $timeout = 1800;

    public function __construct(public readonly int $actorId) {}

    public function handle(StaffNotifier $notifier, StaffAlert $alert, BackupService $backups): void
    {
        $actor = User::query()->find($this->actorId);
        if ($actor === null || ! $actor->can('manage-settings')) {
            return;
        }
        try {
            $path = $backups->run();
        } catch (BackupFailed $e) {
            $alert->send('Backup failed: '.$e->getMessage());
            $notifier->notify($actor, 'Backup failed: '.$e->getMessage(), 'error');

            return;
        }
        $notifier->notify($actor, 'Backup written: '.basename($path), 'success');
    }
}
