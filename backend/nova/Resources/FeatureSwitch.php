<?php

namespace App\Nova\Resources;

use App\Models\FeatureSwitch as FeatureSwitchModel;
use App\Services\FeatureSwitches;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * @extends resource<FeatureSwitchModel>
 */
class FeatureSwitch extends Resource
{
    public static $model = FeatureSwitchModel::class;

    public static $title = 'key';

    public static $search = ['key'];

    public static string $ability = 'manage-settings';

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

    public static function afterSave(NovaRequest $request, $model): void
    {
        app(FeatureSwitches::class)->flush();
    }

    public static function afterDelete(NovaRequest $request, $model): void
    {
        app(FeatureSwitches::class)->flush();
    }
}
