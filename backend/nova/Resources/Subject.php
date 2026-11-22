<?php

namespace App\Nova\Resources;

use App\Models\Subject as SubjectModel;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * @extends resource<SubjectModel>
 */
class Subject extends Resource
{
    public static $model = SubjectModel::class;

    public static $title = 'name';

    public static $search = ['name', 'code'];

    public static string $ability = 'manage-content';

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Name')->rules('required', 'max:96')->sortable(),
            Text::make('Code')->rules('required', 'max:48', 'regex:/^[a-z0-9_]+$/')
                ->creationRules('unique:subjects,code')
                ->updateRules('unique:subjects,code,{{resourceId}}'),
            Select::make('Stream')->options([
                SubjectModel::STREAM_NATURAL => 'Natural',
                SubjectModel::STREAM_SOCIAL => 'Social',
            ])->nullable()->displayUsingLabels()->help('Empty = both streams'),
            Boolean::make('Exam subject', 'is_exam_subject'),
            Text::make('Icon')->nullable()->hideFromIndex(),
            Number::make('Sort')->rules('required', 'integer', 'min:0'),
            Boolean::make('Active', 'is_active'),
            HasMany::make('Units', 'units', Unit::class),
        ];
    }
}
