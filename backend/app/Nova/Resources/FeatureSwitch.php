<?php

namespace App\Nova\Resources;

use App\Models\FeatureSwitch as FeatureSwitchModel;
use App\Services\FeatureSwitches;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * @extends \App\Nova\Resources\Resource<FeatureSwitchModel>
 */
class FeatureSwitch extends Resource
{
    /** @var class-string<FeatureSwitchModel> */
    public static $model = FeatureSwitchModel::class;

    public static $title = 'key';

    /** @var array<int, string> */
    public static $search = ['key'];

    public static string $ability = 'manage-settings';

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        $keys = array_keys(FeatureSwitches::defaults());

        return [
            ID::make(),
            Select::make('Feature', 'key')->options(array_combine($keys, $keys))->rules('required')
                ->creationRules('unique:feature_switches,key')
                ->updateRules('unique:feature_switches,key,{{resourceId}}'),
            Boolean::make('On', 'enabled'),
            Text::make('Description')->nullable(),
        ];
    }

    /** @param  Model  $model */
    public static function afterSave(NovaRequest $request, $model): void
    {
        app(FeatureSwitches::class)->flush();
    }

    /** @param  Model  $model */
    public static function afterDelete(NovaRequest $request, $model): void
    {
        app(FeatureSwitches::class)->flush();
    }
}
