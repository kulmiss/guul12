<?php

namespace App\Services\Ops;

use App\Services\ExplainService;
use App\Services\Settings;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Server checks for the watchdog. Each problem is one short line a staff member can act on.
 */
class HealthChecker
{
    public function __construct(
        private readonly BackupService $backups,
        private readonly ExplainService $explain,
        private readonly Settings $settings,
    ) {}

    public function databaseOk(): bool
    {
        try {
            DB::select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    /**
     * @return list<string> problems; empty = all good
     */
    public function problems(): array
    {
        if (! $this->databaseOk()) {
            // Everything else needs the database.
            return ['The database does not answer.'];
        }
        $problems = [];

        $probe = storage_path('app/.watchdog-probe');
        if (@file_put_contents($probe, 'ok') === false) {
            $problems[] = 'The storage folder is not writable.';
        } else {
            @unlink($probe);
        }

        if ((bool) config('guul.backups.required')) {
            $last = $this->backups->lastBackupAt();
            $maxHours = (int) config('guul.backups.max_age_hours');
            if ($last === null) {
                $problems[] = 'There is no database backup yet.';
            } elseif ($last->lessThan(Carbon::now()->subHours($maxHours))) {
                $problems[] = 'The newest backup is older than '.$maxHours.' hours ('.$last->toDateTimeString().' UTC).';
            }
        }

        $total = @disk_total_space(storage_path());
        $free = @disk_free_space(storage_path());
        $minFree = (int) config('guul.watchdog.min_free_disk_percent');
        if ($total !== false && $free !== false && $total > 0 && $free / $total * 100 < $minFree) {
            $problems[] = 'Less than '.$minFree.'% disk space is free.';
        }

        $capUsd = $this->settings->int('ai_monthly_cap_usd');
        $warn = (int) config('guul.watchdog.ai_budget_warn_percent');
        if ($capUsd > 0 && $this->explain->spentThisMonth() >= $capUsd * 1_000_000 * $warn / 100) {
            $problems[] = 'More than '.$warn.'% of this month\'s AI budget is used.';
        }

        return $problems;
    }
}
