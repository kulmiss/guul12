<?php

namespace App\Nova\Lenses;

use App\Services\Stats\DashboardStats;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\LensRequest;

/** Highest share of right answers first, among questions with enough answers to tell (the same rule as the dashboard). */
class EasiestQuestions extends QuestionLens
{
    public const SLUG = 'easiest-questions';

    public $name = 'Easiest';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        return self::finish($request, self::base($query)->where('times_answered', '>=', DashboardStats::MIN_ANSWERS_QUESTION),
            fn (Builder $q) => $q->orderByRaw('times_correct * 1.0 / times_answered desc'));
    }
}
