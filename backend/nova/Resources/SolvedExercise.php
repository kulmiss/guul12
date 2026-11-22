<?php

namespace App\Nova\Resources;

use App\Enums\QuestionStatus;
use App\Models\SolvedExercise as SolvedExerciseModel;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * @extends resource<SolvedExerciseModel>
 */
class SolvedExercise extends Resource
{
    public static $model = SolvedExerciseModel::class;

    public static $title = 'id';

    public static string $ability = 'manage-content';

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),
            BelongsTo::make('Chapter', 'chapter', Chapter::class),
            Number::make('Sort')->rules('required', 'integer', 'min:0'),
            Textarea::make('Problem')->rules('required')->alwaysShow(),
            Code::make('Steps')->json()->rules('required', 'json')->help('["Step 1 …", "Step 2 …"]'),
            Textarea::make('Final answer', 'final_answer')->rules('required'),
            Select::make('Status')->options(StatusOptions::all())->rules('required')->displayUsingLabels()
                ->resolveUsing(fn ($value) => $value instanceof QuestionStatus ? $value->value : $value),
            Text::make('Source')->nullable()->hideFromIndex(),
        ];
    }
}
