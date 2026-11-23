<?php

namespace App\Nova\Lenses;

use App\Nova\Fields\Show;
use App\Nova\Filters\FilterSets;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;

/** Not seen for 14 days or more (or never, when they joined over 14 days ago). */
class InactiveStudents extends StudentLens
{
    public const SLUG = 'inactive-14-days';

    public $name = 'Inactive 14+ days';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        return self::finish($request, FilterSets::inactiveFor14Days(self::students($query)),
            fn (Builder $q) => $q->orderByDesc('last_active_at'));
    }

    /** @return list<Field> */
    public function fields(NovaRequest $request): array
    {
        return [...$this->profile(), ...Show::when('Last active', 'last_active_at'), ...Show::when('Joined', 'created_at')];
    }
}
