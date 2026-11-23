<?php

namespace App\Nova\Metrics;

use App\Services\Ops\HealthChecker;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\MetricTableRow;
use Laravel\Nova\Metrics\Table;

/** The same checks as the watchdog (database, storage, backups, disk, AI budget), live. */
class SystemHealth extends Table
{
    /** @return list<MetricTableRow> */
    public function calculate(NovaRequest $request): array
    {
        $problems = app(HealthChecker::class)->problems();
        if ($problems === []) {
            return [
                MetricTableRow::make()
                    ->icon('check-circle')
                    ->iconClass('text-green-500')
                    ->title('All good')
                    ->subtitle('Database, storage, backups, disk space and AI budget are fine.'),
            ];
        }

        return array_map(
            fn (string $problem): MetricTableRow => MetricTableRow::make()
                ->icon('exclamation-circle')
                ->iconClass('text-red-500')
                ->title($problem)
                ->subtitle('See docs/operations.md'),
            $problems,
        );
    }

    public function name(): string
    {
        return 'System health';
    }

    public function uriKey(): string
    {
        return 'system-health';
    }
}
