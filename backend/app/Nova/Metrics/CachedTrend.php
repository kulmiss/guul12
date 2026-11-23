<?php

namespace App\Nova\Metrics;

use App\Services\Stats\StatsCache;
use App\Services\Stats\StatsRange;
use Illuminate\Support\Carbon;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Trend;
use Laravel\Nova\Metrics\TrendResult;

/** A line chart (per day or per week) read from the dashboard cache, with the total of the range. */
class CachedTrend extends Trend
{
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $stat,
        string $help = '',
        private readonly string $suffix = '',
    ) {
        parent::__construct();
        if ($help !== '') {
            $this->help($help);
        }
        $this->defaultRange('30');
    }

    public function calculate(NovaRequest $request): TrendResult
    {
        $data = app(StatsCache::class)->get($this->stat, (string) $request->input('range', '30'));
        $points = [];
        $sum = 0;
        foreach (is_array($data) ? $data : [] as $date => $count) {
            $day = Carbon::createFromFormat('Y-m-d', (string) $date, StatsRange::TIMEZONE);
            $points[$day !== null ? $day->format('M j') : (string) $date] = (int) $count;
            $sum += (int) $count;
        }

        $result = (new TrendResult($sum))->trend($points)->format('0,0');

        return $this->suffix !== '' ? $result->suffix($this->suffix)->withoutSuffixInflection() : $result;
    }

    /** @return array<int|string, string> */
    public function ranges(): array
    {
        return array_diff_key(StatsRange::options(), ['1' => true]);
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
