<?php

namespace App\Nova\Metrics;

use App\Services\Stats\StatsCache;
use Closure;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\MetricTableRow;
use Laravel\Nova\Metrics\Table;

/** A short list (top 10, latest 5) read from the dashboard cache. */
class CachedTable extends Table
{
    /**
     * @param  Closure(array<string, mixed>): MetricTableRow  $row  turns one cached item into a row
     */
    public function __construct(
        private readonly string $key,
        private readonly string $label,
        private readonly string $stat,
        private readonly Closure $row,
        private readonly string $empty = 'Nothing here yet.',
        string $help = '',
    ) {
        parent::__construct();
        if ($help !== '') {
            $this->help($help);
        }
    }

    /** @return list<MetricTableRow> */
    public function calculate(NovaRequest $request): array
    {
        $data = app(StatsCache::class)->get($this->stat);
        $items = is_array($data) ? array_values(array_filter($data, 'is_array')) : [];
        if ($items === []) {
            return [MetricTableRow::make()->icon('information-circle')->iconClass('text-gray-400')->title($this->empty)];
        }

        return array_map(fn (array $item): MetricTableRow => ($this->row)($item), $items);
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
