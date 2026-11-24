<?php

namespace App\Console\Commands;

use App\Services\Ops\HealthChecker;
use App\Services\Ops\StaffAlert;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class Watchdog extends Command
{
    protected $signature = 'guul:watchdog';

    protected $description = 'Check the server and alert staff when something changes (problem found or fixed)';

    private const STATE_KEY = 'watchdog.problems';

    public function handle(HealthChecker $health, StaffAlert $alert): int
    {
        $problems = $health->problems();
        /** @var list<string> $before */
        $before = Cache::get(self::STATE_KEY, []);

        // Only changes are sent, so staff are not woken up every 10 minutes for the same thing.
        if ($problems !== $before) {
            $alert->send($problems === [] ? 'All good again.' : implode(' ', $problems));
            Cache::forever(self::STATE_KEY, $problems);
        }
        foreach ($problems as $p) {
            $this->warn($p);
        }
        if ($problems === []) {
            $this->info('All good.');
        }

        return $problems === [] ? self::SUCCESS : self::FAILURE;
    }
}
