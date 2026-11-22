<?php

namespace App\Nova\Resources;

use App\Enums\ReportReason;
use App\Enums\ReportStatus;
use App\Models\QuestionReport as QuestionReportModel;
use App\Nova\Actions\ResolveReports;
use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * "Report a problem" from students. Content checkers accept (the question is wrong: fix the
 * content file, re-solve, import) or reject (the question is right).
 *
 * @extends \App\Nova\Resources\Resource<QuestionReportModel>
 */
class QuestionReport extends Resource
{
    /** @var class-string<QuestionReportModel> */
    public static $model = QuestionReportModel::class;

    public static $title = 'id';

    /** @var array<int, string> */
    public static $search = ['note'];

    public static string $ability = 'manage-content';

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            DateTime::make('When', 'created_at')->sortable(),
            BelongsTo::make('Question', 'question', Question::class),
            Number::make('Version', 'question_version'),
            Text::make('Reason', fn (QuestionReportModel $r) => match ($r->reason) {
                ReportReason::WrongAnswer => 'The answer is wrong',
                ReportReason::Unclear => 'Unclear',
                ReportReason::Typo => 'Typo',
                ReportReason::Other => 'Other',
            }),
            Text::make('Note')->onlyOnDetail(),
            Badge::make('Status', fn (QuestionReportModel $r) => $r->status->value)->map([
                ReportStatus::Open->value => 'warning',
                ReportStatus::Accepted->value => 'success',
                ReportStatus::Rejected->value => 'info',
            ]),
            DateTime::make('Resolved', 'resolved_at')->onlyOnDetail(),
        ];
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [new ResolveReports(true), new ResolveReports(false)];
    }
}
