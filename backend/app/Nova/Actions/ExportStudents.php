<?php

namespace App\Nova\Actions;

use App\Models\AuditLog;
use App\Models\User;
use App\Nova\Fields\Show;
use App\Services\PremiumService;
use App\Support\CsvCell;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;
use Laravel\Nova\Actions\ExportAsCsv;

/**
 * The students in the list (selected rows, or all that match the filters) as a CSV. Only what staff
 * need for reports: no name, email, nickname, city or gender. Every export is logged.
 */
class ExportStudents extends ExportAsCsv
{
    public function __construct()
    {
        parent::__construct('Export CSV');

        $this->withQuery(function (Builder $query): Builder {
            $actor = request()->user();
            AuditLog::write($actor instanceof User ? $actor : null, 'export.students', null, ['rows' => (clone $query)->count()]);

            return $query->with('currentPremiumGrants');
        })->withFormat(function (Model $student): array {
            if (! $student instanceof User) {
                return [];
            }
            $until = PremiumService::untilFromGrants($student->currentPremiumGrants, Carbon::now());

            return CsvCell::row([
                'Student ID' => $student->student_id,
                'Grade' => $student->grade,
                'Stream' => $student->stream !== null ? ucfirst($student->stream) : '',
                'Region' => $student->region !== null ? ucfirst(str_replace('_', ' ', $student->region)) : '',
                'Plan' => $until !== null ? 'Premium' : 'Free',
                'Premium until' => $until?->setTimezone(Show::TIMEZONE)->format('Y-m-d') ?? '',
                'Joined' => $student->created_at->setTimezone(Show::TIMEZONE)->format('Y-m-d'),
                'Last active' => $student->last_active_at?->setTimezone(Show::TIMEZONE)->format('Y-m-d') ?? '',
            ]);
        });
    }

    public function uriKey(): string
    {
        return 'export-csv';
    }
}
