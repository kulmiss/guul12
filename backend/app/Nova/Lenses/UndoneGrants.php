<?php

namespace App\Nova\Lenses;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Http\Requests\LensRequest;

/** Premium that staff took back ("Undo Premium"), newest first. */
class UndoneGrants extends GrantLens
{
    public const KEY = 'undone-grants';

    public $name = 'Undone changes';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        return self::finish($request, $query->with(['user', 'grantedBy'])->whereNotNull('revoked_at'),
            fn (Builder $q) => $q->orderByDesc('revoked_at'));
    }
}
