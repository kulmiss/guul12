<?php

namespace App\Nova\Resources;

use App\Models\AuditLog as AuditLogModel;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Read-only log of staff actions.
 *
 * @extends resource<AuditLogModel>
 */
class AuditLog extends Resource
{
    public static $model = AuditLogModel::class;

    public static $title = 'action';

    public static $search = ['action'];

    public static string $ability = 'manage-settings';

    public static function authorizedToCreate($request): bool
    {
        return false;
    }

    public function authorizedToUpdate($request): bool
    {
        return false;
    }

    public function authorizedToDelete($request): bool
    {
        return false;
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            DateTime::make('When', 'created_at')->sortable(),
            BelongsTo::make('Who', 'actor', Staff::class)->nullable(),
            Text::make('Action')->sortable(),
            Text::make('Subject', 'subject_type'),
            Number::make('Subject ID', 'subject_id'),
            Code::make('Data')->json()->onlyOnDetail(),
            Text::make('IP')->onlyOnDetail(),
        ];
    }
}
