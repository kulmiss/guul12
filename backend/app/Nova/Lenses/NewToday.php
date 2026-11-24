<?php

namespace App\Nova\Lenses;

use App\Nova\Fields\Show;
use App\Support\LocalTime;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;

/** Joined today (Addis Ababa day). */
class NewToday extends StudentLens
{
    public const SLUG = 'new-today';

    public $name = 'New today';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        return self::finish($request, self::students($query)->where('created_at', '>=', LocalTime::startOfToday()),
            fn (Builder $q) => $q->orderByDesc('created_at'));
    }

    /** @return list<Field> */
    public function fields(NovaRequest $request): array
    {
        return [...$this->profile(), Text::make('Region')->displayUsing(fn (?string $v): string => $v !== null ? ucfirst(str_replace('_', ' ', $v)) : '—'),
            ...Show::when('Joined', 'created_at')];
    }
}
