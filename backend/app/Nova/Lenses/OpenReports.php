<?php

namespace App\Nova\Lenses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\LensRequest;

/** Reports nobody has answered yet, oldest first. */
class OpenReports extends ReportLens
{
    public const KEY = 'open-reports';

    public $name = 'Open';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        return self::finish($request, self::open($query), fn (Builder $q) => $q->orderBy('created_at'));
    }
}
