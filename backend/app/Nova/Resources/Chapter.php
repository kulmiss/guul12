<?php

namespace App\Nova\Resources;

use App\Enums\Access;
use App\Models\Chapter as ChapterModel;
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
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * @extends \App\Nova\Resources\Resource<ChapterModel>
 */
class Chapter extends Resource
{
    /** @var class-string<ChapterModel> */
    public static $model = ChapterModel::class;

    public static $title = 'title';

    /** @var array<int, string> */
    public static $search = ['title'];

    public static string $ability = 'manage-content';

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
            Number::make('Sort')->rules('required', 'integer', 'min:0')->hideFromIndex(),
            Boolean::make('Active', 'is_active'),
            HasOne::make('Summary', 'summary', Summary::class),
            HasMany::make('Solved exercises', 'solvedExercises', SolvedExercise::class),
            HasMany::make('Flashcards', 'flashcards', Flashcard::class),
            HasMany::make('Questions', 'questions', Question::class),
        ];
    }
}
