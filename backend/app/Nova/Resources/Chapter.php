<?php

namespace App\Nova\Resources;

use App\Enums\Access;
use App\Models\Chapter as ChapterModel;
use App\Nova\Actions\RebuildOfflinePack;
use App\Nova\Actions\SetVideoAccess;
use App\Nova\Filters\FilterSets;
use App\Nova\Lenses;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\HasOne;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\URL;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;
use Laravel\Nova\Query\Search\SearchableRelation;

/**
 * @extends \App\Nova\Resources\Resource<ChapterModel>
 */
class Chapter extends Resource
{
    /** @var class-string<ChapterModel> */
    public static $model = ChapterModel::class;

    public static $title = 'title';

    public function subtitle(): ?string
    {
        $row = $this->resource;
        if ($row === null) {
            return null;
        }
        $unit = $row->unit;

        return 'Grade '.$unit->grade.' · '.$unit->subject->name.' · Unit '.$unit->number;
    }

    public static string $ability = 'manage-content';

    /** @var list<string> */
    public static $with = ['unit'];

    /** @return list<string|SearchableRelation> */
    public static function searchableColumns(): array
    {
        return ['title', new SearchableRelation('unit', 'title')];
    }

    /** Lists show how many questions each chapter has and how many of them students reported. */
    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->withCount([
            'questions',
            'questions as reported_count' => fn ($q) => $q->where('reports_count', '>', 0),
        ]);
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Unit', 'unit', Unit::class)->sortable(),
            Number::make('Number')->rules('required', 'integer', 'min:1')->sortable(),
            Text::make('Title')->rules('required', 'max:191'),
            URL::make('Official textbook PDF', 'textbook_url')->rules('nullable', 'url', 'max:512')->hideFromIndex(),
            Text::make('YouTube video ID', 'video_youtube_id')->rules('nullable', 'regex:/^[A-Za-z0-9_-]{6,32}$/')
                ->help('Unlisted video. Empty = video slot hidden.')->hideFromIndex(),
            Select::make('Video access', 'video_access')->options([
                Access::Free->value => 'Free',
                Access::Premium->value => 'Premium',
            ])->rules('required')->displayUsingLabels()
                ->resolveUsing(fn ($value) => $value instanceof Access ? $value->value : $value)->hideFromIndex(),
            Number::make('Sort')->rules('required', 'integer', 'min:0')->hideFromIndex()->help('Order inside the unit (smaller first).'),
            Badge::make('Video', fn (ChapterModel $c): string => $c->video_youtube_id === null ? 'none' : $c->video_access->value)
                ->map(['none' => 'info', Access::Free->value => 'success', Access::Premium->value => 'warning'])
                ->labels(['none' => 'No video', Access::Free->value => 'Free', Access::Premium->value => '🔒 Premium'])
                ->exceptOnForms(),
            Number::make('Questions', 'questions_count')->resolveUsing(fn (mixed $count): int => (int) $count)->onlyOnIndex()->sortable(),
            Number::make('Reported', 'reported_count')->resolveUsing(fn (mixed $count): int => (int) $count)->onlyOnIndex()->sortable()
                ->help('Questions in this chapter with at least one student report.'),
            Boolean::make('Active', 'is_active')->help('Off = hidden in the app.'),
            HasOne::make('Summary', 'summary', Summary::class),
            HasMany::make('Solved exercises', 'solvedExercises', SolvedExercise::class),
            HasMany::make('Flashcards', 'flashcards', Flashcard::class),
            HasMany::make('Questions', 'questions', Question::class),
        ];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::chapters();
    }

    /** @return array<int, Lens> */
    public function lenses(NovaRequest $request): array
    {
        return [new Lenses\IncompleteContent, new Lenses\NoVideoYet];
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [new SetVideoAccess(Access::Free), new SetVideoAccess(Access::Premium), new RebuildOfflinePack];
    }
}
