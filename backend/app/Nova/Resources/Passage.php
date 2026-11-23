<?php

namespace App\Nova\Resources;

use App\Models\Passage as PassageModel;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * @extends \App\Nova\Resources\Resource<PassageModel>
 */
class Passage extends Resource
{
    /** @var class-string<PassageModel> */
    public static $model = PassageModel::class;

    /** Kept out of the search box at the top: too noisy to be useful there. */
    public static $globallySearchable = false;

    public static $title = 'title';

    /** @var array<int, string> */
    public static $search = ['title'];

    public static string $ability = 'manage-content';

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->withCount('questions');
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),
            Text::make('Title')->rules('required', 'max:191'),
            Textarea::make('Body')->rules('required')->alwaysShow(),
            Text::make('Source')->nullable(),
            Number::make('Questions', 'questions_count')->resolveUsing(fn (mixed $count): int => (int) $count)->onlyOnIndex()->sortable(),
            HasMany::make('Questions', 'questions', Question::class),
        ];
    }
}
