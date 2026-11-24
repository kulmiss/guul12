<?php

namespace App\Nova\Metrics;

use App\Services\Stats\StatsCache;
use App\Services\Stats\StatsRange;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

/**
 * A number card read from the dashboard cache (StatsCache). Ranged cards show today / 7 / 30 / 90 days /
 * this term / all time and the ↑/↓ % against the period before.
 */
class CachedValue extends Value
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $stat,
        private readonly bool $ranged = false,
        string $help = '',
        string $defaultRange = '30',
    ) {
        parent::__construct();
        if ($help !== '') {
            $this->help($help);
        }
        if ($ranged) {
            $this->defaultRange($defaultRange);
        }
    }

    public function calculate(NovaRequest $request): ValueResult
    {
        $cache = app(StatsCache::class);
        if (! $this->ranged) {
            $data = $cache->get($this->stat);

            return $this->result(is_numeric($data) ? (int) $data : 0)->format('0,0')->allowZeroResult();
        }

        $data = $cache->get($this->stat, (string) $request->input('range', $this->selectedRangeKey ?? '30'));
        $value = is_array($data) && is_numeric($data['value'] ?? null) ? (int) $data['value'] : 0;
        $result = $this->result($value)->format('0,0')->allowZeroResult();
        if (is_array($data) && is_numeric($data['previous'] ?? null)) {
            $result->previous((int) $data['previous']);
        }

        return $result;
    }

    /** @return array<int|string, string> */
    public function ranges(): array
    {
        return $this->ranged ? StatsRange::options() : [];
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
