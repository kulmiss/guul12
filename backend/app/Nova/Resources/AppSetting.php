<?php

namespace App\Nova\Resources;

use App\Models\AppSetting as AppSettingModel;
use App\Nova\Actions\StaffTool;
use App\Services\Settings;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * App settings: prices (landing page only), free limits, exam date, AI cap, update prompts, maintenance.
 *
 * @extends \App\Nova\Resources\Resource<AppSettingModel>
 */
class AppSetting extends Resource
{
    /** @var class-string<AppSettingModel> */
    public static $model = AppSettingModel::class;

    public static $title = 'key';

    /** @var array<int, string> */
    public static $search = ['key'];

    public static string $ability = 'manage-settings';

    /** @return array<int, Field|Panel> */
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

    /** @param  Model  $model */
    public static function afterSave(NovaRequest $request, $model): void
    {
        app(Settings::class)->flush();
    }

    /** @param  Model  $model */
    public static function afterDelete(NovaRequest $request, $model): void
    {
        app(Settings::class)->flush();
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [new StaffTool('refresh'), new StaffTool('telegram'), new StaffTool('email'), new StaffTool('backup')];
    }
}
