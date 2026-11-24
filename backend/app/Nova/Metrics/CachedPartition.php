<?php

namespace App\Nova\Metrics;

use App\Services\Stats\StatsCache;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Partition;
use Laravel\Nova\Metrics\PartitionResult;

/** A pie chart of "now" counts read from the dashboard cache. */
class CachedPartition extends Partition
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $stat,
        string $help = '',
    ) {
        parent::__construct();
        if ($help !== '') {
            $this->help($help);
        }
    }

    public function calculate(NovaRequest $request): PartitionResult
    {
        $data = app(StatsCache::class)->get($this->stat);
        $counts = [];
        foreach (is_array($data) ? $data : [] as $label => $count) {
            $counts[(string) $label] = (int) $count;
        }

        return $this->result($counts);
    }

    public function name(): string
    {
        return $this->label;
    }

    public function uriKey(): string
    {
        return $this->key;
    }
}
