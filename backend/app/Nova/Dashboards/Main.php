<?php

namespace App\Nova\Dashboards;

use App\Models\User;
use App\Nova\Metrics\ActiveStudents;
use App\Nova\Metrics\AiSpend;
use App\Nova\Metrics\AnswersGiven;
use App\Nova\Metrics\NewStudents;
use App\Nova\Metrics\OpenReports;
use App\Nova\Metrics\PremiumActive;
use App\Nova\Metrics\SystemHealth;
use Illuminate\Http\Request;
use Laravel\Nova\Card;
use Laravel\Nova\Dashboards\Main as Dashboard;

/**
 * The staff home page. Everyone sees the activity numbers; each role also sees the cards for its job.
 * A card a role cannot see also cannot be fetched by its address.
 */
class Main extends Dashboard
{
    /** @return array<int, Card> */
    public function cards(): array
    {
        return [
            new NewStudents,
            new ActiveStudents,
            new AnswersGiven,
            (new PremiumActive)->canSee(self::allowedTo('manage-premium')),
            (new OpenReports)->canSee(self::allowedTo('manage-content')),
            (new AiSpend)->canSee(self::allowedTo('manage-settings')),
            (new SystemHealth)->canSee(self::allowedTo('manage-settings')),
        ];
    }

    /** @return \Closure(Request): bool */
    private static function allowedTo(string $ability): \Closure
    {
        return fn (Request $request): bool => $request->user() instanceof User && $request->user()->can($ability);
    }
}
