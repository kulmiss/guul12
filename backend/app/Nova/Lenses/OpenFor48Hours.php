<?php

namespace App\Nova\Lenses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Laravel\Nova\Http\Requests\LensRequest;

/** Open reports waiting more than 48 hours: answer these first. */
class OpenFor48Hours extends ReportLens
{
    public const KEY = 'open-48-hours';

    public $name = 'Older than 48 h';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        return self::finish($request, self::open($query)->where('created_at', '<', Carbon::now()->subHours(48)),
            fn (Builder $q) => $q->orderBy('created_at'));
    }
}
