<?php

namespace App\Nova\Resources;

use App\Models\Passage as PassageModel;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * @extends resource<PassageModel>
 */
class Passage extends Resource
{
    public static $model = PassageModel::class;

    public static $title = 'title';

    public static $search = ['title'];

    public static string $ability = 'manage-content';

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),
            Text::make('Title')->rules('required', 'max:191'),
            Textarea::make('Body')->rules('required')->alwaysShow(),
            Text::make('Source')->nullable(),
            HasMany::make('Questions', 'questions', Question::class),
        ];
    }
}
