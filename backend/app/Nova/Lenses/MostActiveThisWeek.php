<?php

namespace App\Nova\Lenses;

use App\Models\Attempt;
use App\Nova\Fields\Show;
use App\Support\LocalTime;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;

/** Most answers since Monday (the leaderboard week). */
class MostActiveThisWeek extends StudentLens
{
    public const SLUG = 'most-active-this-week';

    public $name = 'Most active this week';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        $since = LocalTime::startOfWeek();
        $answers = Attempt::query()->selectRaw('coalesce(sum(total), 0)')->whereColumn('user_id', 'users.id')->where('finished_at', '>=', $since);

        return self::finish($request, self::students($query)->select('users.*')->selectSub($answers, 'answers_this_week')->withCasts(['answers_this_week' => 'integer'])
            ->whereHas('attempts', fn (EloquentBuilder $a) => $a->where('finished_at', '>=', $since)),
            fn (Builder $q) => $q->orderByDesc('answers_this_week'));
    }

    /** @return list<Field> */
    public function fields(NovaRequest $request): array
    {
        return [...$this->profile(),
            Number::make('Answers this week', 'answers_this_week')->sortable(),
            ...Show::when('Last active', 'last_active_at'),
        ];
    }
}
