<?php

namespace App\Nova\Lenses;

use App\Models\Question;
use App\Nova\Fields\Show;
use App\Nova\Filters\FilterSets;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Shared columns of the question lenses.
 */
abstract class QuestionLens extends Lens
{
    protected static function base(Builder $query): Builder
    {
        return $query->with('chapter');
    }

    /** @return list<Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Question', fn (Question $q): string => self::short($q->stem)),
            Text::make('Chapter', fn (Question $q): string => $q->chapter->title),
            Text::make('Status', fn (Question $q): string => ucfirst($q->status->value)),
            Number::make('Answered', 'times_answered')->sortable(),
            Text::make('Right', fn (Question $q): string => Show::percent($q->times_correct, $q->times_answered)),
            Number::make('Reports', 'reports_count')->sortable(),
        ];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::questions();
    }

    private static function short(string $text): string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($plain) > 70 ? mb_substr($plain, 0, 69).'…' : $plain;
    }
}
