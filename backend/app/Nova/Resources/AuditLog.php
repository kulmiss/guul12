<?php

namespace App\Nova\Resources;

use App\Models\AuditLog as AuditLogModel;
use App\Nova\Fields\Show;
use App\Nova\Filters\FilterSets;
use App\Nova\Resources\Concerns\ReadOnlyResource;
use App\Support\AuditLogDiff;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Read-only log of staff actions.
 *
 * @extends \App\Nova\Resources\Resource<AuditLogModel>
 */
class AuditLog extends Resource
{
    use ReadOnlyResource;

    /** @var class-string<AuditLogModel> */
    public static $model = AuditLogModel::class;

    /** Kept out of the search box at the top: too noisy to be useful there. */
    public static $globallySearchable = false;

    public static $title = 'action';

    /** @var array<int, string> */
    public static $search = ['action', 'subject_type'];

    /** @var list<string> */
    public static $with = ['actor'];

    public static function label(): string
    {
        return 'Activity log';
    }

    public static string $ability = 'manage-settings';

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->hideFromIndex(),
            ...Show::when('When', 'created_at'),
            Text::make('Who', fn (AuditLogModel $log): string => $log->actor->name ?? ($log->actor_id === null ? 'System' : 'Deleted staff member')),
            Text::make('Action')->sortable()->help('What happened, for example premium.granted or nickname.reset.'),
            Text::make('About', fn (AuditLogModel $log): string => $log->subject_type !== null ? $log->subject_type.' #'.$log->subject_id : '—'),
            Text::make('Change', fn (AuditLogModel $log): string => AuditLogDiff::summary($log->data))->onlyOnIndex(),
            Text::make('Changes', fn (AuditLogModel $log): string => AuditLogDiff::html($log->data))->asHtml()->onlyOnDetail(),
            Code::make('Raw details', 'data')->json()->onlyOnDetail(),
            Text::make('IP')->onlyOnDetail(),
        ];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::auditLogs();
    }
}
