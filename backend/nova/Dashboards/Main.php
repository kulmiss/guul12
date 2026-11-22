<?php

namespace App\Nova\Dashboards;

use App\Nova\Metrics\NewStudents;
use App\Nova\Metrics\PremiumActive;
use Laravel\Nova\Dashboards\Main as Dashboard;

/**
 * Basic numbers for Phase 1. The full analysis dashboard is built in Phase 5.
 */
class Main extends Dashboard
{
    public function cards(): array
    {
        return [
            new NewStudents,
            new PremiumActive,
        ];
    }
}
