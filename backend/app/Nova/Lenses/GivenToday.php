<?php

namespace App\Nova\Lenses;

use App\Support\LocalTime;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\LensRequest;

/** Premium given today (Addis Ababa day), to check the day's work. */
class GivenToday extends GrantLens
{
    public const SLUG = 'given-today';

    public $name = 'Given today';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        return self::finish($request, $query->with(['user', 'grantedBy'])->where('created_at', '>=', LocalTime::startOfToday()),
            fn (Builder $q) => $q->orderByDesc('created_at'));
    }
}
