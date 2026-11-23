<?php

namespace App\Nova\Lenses;

use App\Models\Chapter;
use App\Nova\Filters\FilterSets;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;

/** Chapters without a lesson video yet. */
class NoVideoYet extends Lens
{
    public const SLUG = 'no-video-yet';

    public $name = 'No video yet';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        return self::finish($request, $query->with('unit.subject')->whereNull('video_youtube_id'),
            fn (Builder $q) => $q->orderBy('unit_id')->orderBy('number'));
    }

    /** @return list<Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->hideFromIndex(),
            Text::make('Chapter', fn (Chapter $c): string => $c->title),
            Text::make('Subject', fn (Chapter $c): string => $c->unit->subject->name.' · Grade '.$c->unit->grade),
        ];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::chapters();
    }
}
