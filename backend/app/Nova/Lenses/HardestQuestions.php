<?php

namespace App\Nova\Lenses;

use App\Services\Stats\DashboardStats;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\LensRequest;

/** Lowest share of right answers first, among questions with enough answers to tell (the same rule as the dashboard). */
class HardestQuestions extends QuestionLens
{
    public const KEY = 'hardest-questions';

    public $name = 'Hardest';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        return self::finish($request, self::base($query)->where('times_answered', '>=', DashboardStats::MIN_ANSWERS_QUESTION),
            fn (Builder $q) => $q->orderByRaw('times_correct * 1.0 / times_answered asc'));
    }
}
