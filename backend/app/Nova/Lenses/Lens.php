<?php

namespace App\Nova\Lenses;

use Closure;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Lenses\Lens as NovaLens;

/**
 * Our lenses: a saved view of a list with its own name and address. The list's filters still work
 * on a lens; a column the staff member sorts by wins over the lens's own order.
 * Who may open a lens is decided by its resource (Nova checks the resource's role first).
 */
abstract class Lens extends NovaLens
{
    /** The address part, e.g. "new-today". */
    public const KEY = '';

    public function uriKey(): string
    {
        return static::KEY;
    }

    /** @param  Closure(Builder): mixed  $defaultOrder */
    protected static function finish(LensRequest $request, Builder $query, Closure $defaultOrder): Builder
    {
        return $request->withOrdering($request->withFilters($query), function (Builder $q) use ($defaultOrder): Builder {
            $defaultOrder($q);

            return $q->orderBy($q->getModel()->getQualifiedKeyName());
        });
    }
}
