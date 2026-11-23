<?php

namespace App\Nova\Resources;

use App\Enums\QuestionStatus;
use App\Models\Flashcard as FlashcardModel;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * @extends \App\Nova\Resources\Resource<FlashcardModel>
 */
class Flashcard extends Resource
{
    /** @var class-string<FlashcardModel> */
    public static $model = FlashcardModel::class;

    public static $title = 'term';

    /** @var array<int, string> */
    public static $search = ['term'];

    public static string $ability = 'manage-content';

    /** @var list<string> */
    public static $with = ['chapter'];

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),
            BelongsTo::make('Chapter', 'chapter', Chapter::class),
            Number::make('Sort')->rules('required', 'integer', 'min:0'),
            Text::make('Term')->rules('required', 'max:191'),
            Textarea::make('Definition')->rules('required')->alwaysShow(),
            Select::make('Status')->options(StatusOptions::all())->rules('required')->displayUsingLabels()
                ->resolveUsing(fn ($value) => $value instanceof QuestionStatus ? $value->value : $value),
        ];
    }
}
