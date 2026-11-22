<?php

namespace App\Nova\Resources;

use App\Models\AppSetting as AppSettingModel;
use App\Services\Settings;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * App settings: prices (landing page only), free limits, exam date, AI cap, update prompts, maintenance.
 *
 * @extends resource<AppSettingModel>
 */
class AppSetting extends Resource
{
    public static $model = AppSettingModel::class;

    public static $title = 'key';

    public static $search = ['key'];

    public static string $ability = 'manage-settings';

    public function fields(NovaRequest $request): array
    {
        $keys = array_keys(Settings::defaults());

        return [
            ID::make(),
            Select::make('Key')->options(array_combine($keys, $keys))->rules('required')
                ->creationRules('unique:app_settings,key')
                ->updateRules('unique:app_settings,key,{{resourceId}}'),
            Code::make('Value')->json()->rules('required', 'json')
                ->help('JSON value, e.g. 250, "2027-06-29", null, true'),
        ];
    }

    public static function afterSave(NovaRequest $request, $model): void
    {
        app(Settings::class)->flush();
    }

    public static function afterDelete(NovaRequest $request, $model): void
    {
        app(Settings::class)->flush();
    }
}
