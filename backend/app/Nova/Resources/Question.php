<?php

namespace App\Nova\Resources;

use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Models\Question as QuestionModel;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Questions of all types. LaTeX goes between $…$ (inline) or $$…$$ (block).
 *
 * @extends \App\Nova\Resources\Resource<QuestionModel>
 */
class Question extends Resource
{
    /** @var class-string<QuestionModel> */
    public static $model = QuestionModel::class;

    public static $title = 'id';

    /** @var array<int, string> */
    public static $search = ['id', 'stem'];

    public static string $ability = 'manage-content';

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        $types = [];
        foreach (QuestionType::cases() as $type) {
            $types[$type->value] = ucwords(str_replace('_', ' ', $type->value));
        }

        $statuses = [];
        foreach (QuestionStatus::cases() as $status) {
            $statuses[$status->value] = ucfirst($status->value);
        }

        return [
            ID::make()->sortable(),
            BelongsTo::make('Chapter', 'chapter', Chapter::class)->sortable(),
            BelongsTo::make('Passage', 'passage', Passage::class)->nullable()->hideFromIndex(),
            Select::make('Type')->options($types)->rules('required')->displayUsingLabels()
                ->resolveUsing(fn ($value) => $value instanceof QuestionType ? $value->value : $value),
            Textarea::make('Question', 'stem')->rules('required')->alwaysShow(),
            Text::make('Image / diagram path', 'media_path')->nullable()->hideFromIndex(),
            Number::make('Difficulty')->rules('required', 'integer', 'between:1,5')->sortable(),
            Select::make('Status')->options($statuses)->rules('required')->displayUsingLabels()->sortable()
                ->resolveUsing(fn ($value) => $value instanceof QuestionStatus ? $value->value : $value),
            Code::make('Answer key', 'answer_key')->json()->rules('required', 'json')
                ->help('e.g. {"correct":"B"} · {"correct":["A","C"]} · {"value":2.5,"tolerance":0.01,"unit":"s"} · {"accepted":["ion"]} · {"pairs":{"L1":"R2"}} · {"order":["C","A","B"]}'),
            Textarea::make('Explanation')->rules('required')->alwaysShow(),
            Text::make('Source')->nullable()->hideFromIndex(),
            Number::make('Version')->exceptOnForms(),
            Number::make('Answered', 'times_answered')->exceptOnForms()->sortable(),
            Number::make('Correct', 'times_correct')->exceptOnForms()->hideFromIndex(),
            Number::make('Reports', 'reports_count')->exceptOnForms()->sortable(),
            HasMany::make('Options', 'options', QuestionOption::class),
        ];
    }
}
