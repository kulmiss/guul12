<?php

namespace App\Nova\Actions;

use App\Models\NicknameReport;
use App\Models\QuestionReport;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/**
 * Opens what a report is about: the question, or the student behind a nickname (only for staff who
 * may see students).
 */
class OpenReportedItem extends Action
{
    public $withoutConfirmation = true;

    public $sole = true;

    public $showInline = true;

    public function __construct(private readonly bool $student = false)
    {
        $this->name = $student ? 'Open student' : 'Open question';
        if ($student) {
            $allowed = fn (Request $request): bool => $request->user() instanceof User && $request->user()->can('view-students');
            $this->canSee($allowed)->canRun(fn (Request $request): bool => $allowed($request));
        }
    }

    /** @param  Collection<int, Model>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $report = $models->first();

        return match (true) {
            $report instanceof QuestionReport => ActionResponse::visit('/resources/questions/'.$report->question_id),
            $report instanceof NicknameReport => ActionResponse::visit('/resources/students/'.$report->reported_user_id),
            default => ActionResponse::danger('Nothing to open.'),
        };
    }
}
