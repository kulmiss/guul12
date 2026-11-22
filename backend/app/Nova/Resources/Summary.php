<?php

namespace App\Nova\Resources;

use App\Enums\QuestionStatus;
use App\Models\Summary as SummaryModel;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Markdown;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * @extends \App\Nova\Resources\Resource<SummaryModel>
 */
class Summary extends Resource
{
    /** @var class-string<SummaryModel> */
    public static $model = SummaryModel::class;

    public static $title = 'id';

    public static string $ability = 'manage-content';

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make(),
            BelongsTo::make('Chapter', 'chapter', Chapter::class),
            Code::make('Key points', 'key_points')->json()->rules('required', 'json')->help('["Point one", "Point two"]'),
            Code::make('Formulas')->json()->rules('required', 'json')->help('[{"label":"Area of a circle","latex":"A = \\\\pi r^2"}]'),
            Markdown::make('Body')->nullable(),
            Select::make('Status')->options(StatusOptions::all())->rules('required')->displayUsingLabels()
                ->resolveUsing(fn ($value) => $value instanceof QuestionStatus ? $value->value : $value),
            Text::make('Source')->nullable()->hideFromIndex(),
        ];
    }
}
