<?php

namespace App\Console\Commands;

use App\Services\Stats\StatsCache;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;

class RefreshStats extends Command
{
    protected $signature = 'guul:stats';

    protected $description = 'Work out every Nova dashboard number again and keep it, so the dashboard opens fast';

    public function handle(StatsCache $cache): int
    {
        $started = Carbon::now();
        $count = $cache->refreshAll();
        $ms = (int) $started->diffInMilliseconds(Carbon::now(), true);
        $this->info("Dashboard numbers refreshed: {$count} in {$ms} ms.");

        return self::SUCCESS;
    }
}
