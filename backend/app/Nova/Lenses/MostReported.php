<?php

namespace App\Nova\Lenses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\LensRequest;

/** Questions students reported most (all reports, open or closed). */
class MostReported extends QuestionLens
{
    public const KEY = 'most-reported';

    public $name = 'Most reported';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        return self::finish($request, self::base($query)->where('reports_count', '>', 0), fn (Builder $q) => $q->orderByDesc('reports_count'));
    }
}
