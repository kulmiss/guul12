<?php

namespace App\Nova\Resources;

use App\Models\Unit as UnitModel;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * @extends \App\Nova\Resources\Resource<UnitModel>
 */
class Unit extends Resource
{
    /** @var class-string<UnitModel> */
    public static $model = UnitModel::class;

    public static $title = 'title';

    /** @var array<int, string> */
    public static $search = ['title'];

    public static string $ability = 'manage-content';

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Subject', 'subject', Subject::class)->sortable(),
            Select::make('Grade')->options(array_combine(UnitModel::GRADES, array_map('strval', UnitModel::GRADES)))->rules('required')->sortable(),
            Select::make('Stream')->options([
                'natural' => 'Natural',
                'social' => 'Social',
            ])->nullable()->displayUsingLabels()->help('Only for subjects that split by stream (Maths 11–12). Empty = both.'),
            Number::make('Number')->rules('required', 'integer', 'min:1')->sortable(),
            Text::make('Title')->rules('required', 'max:191'),
            Number::make('Sort')->rules('required', 'integer', 'min:0')->hideFromIndex(),
            Boolean::make('Active', 'is_active'),
            HasMany::make('Chapters', 'chapters', Chapter::class),
        ];
    }
}
