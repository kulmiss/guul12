<?php

namespace App\Nova\Resources;

use App\Models\QuestionOption as QuestionOptionModel;
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
 * @extends \App\Nova\Resources\Resource<QuestionOptionModel>
 */
class QuestionOption extends Resource
{
    /** @var class-string<QuestionOptionModel> */
    public static $model = QuestionOptionModel::class;

    public static $title = 'key';

    public static $displayInNavigation = false;

    public static string $ability = 'manage-content';

    /** @var list<string> */
    public static $with = ['question'];

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),
            BelongsTo::make('Question', 'question', Question::class),
            Text::make('Key')->rules('required', 'max:8', 'regex:/^[A-Za-z0-9]+$/')
                ->help('A–F for choices, L1/R1 for matching, any key for ordering'),
            Select::make('Side')->options([
                QuestionOptionModel::SIDE_LEFT => 'Left (matching)',
                QuestionOptionModel::SIDE_RIGHT => 'Right (matching)',
            ])->nullable()->displayUsingLabels(),
            Textarea::make('Text', 'body')->rules('required')->alwaysShow(),
            Text::make('Image path', 'media_path')->nullable()->hideFromIndex(),
            Number::make('Sort')->rules('required', 'integer', 'min:0'),
        ];
    }
}
