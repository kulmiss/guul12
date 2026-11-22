<?php

namespace App\Nova\Actions;

use App\Models\QuestionReport;
use App\Models\User;
use App\Services\Quality\QualityGate;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

class ResolveReports extends Action
{
    public function __construct(private readonly bool $accept)
    {
        $this->name = $accept ? 'Accept: the question is wrong' : 'Reject: the question is right';
        $this->confirmText = $accept
            ? 'Mark the open reports as accepted? Then fix the question in the content files, re-solve it and import.'
            : 'Mark the open reports as rejected? The question goes back to Published (or Beta).';
    }

    /**
     * @param  Collection<int, QuestionReport>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $gate = app(QualityGate::class);
        /** @var User $actor */
        $actor = request()->user();
        $done = 0;

        foreach ($models->unique('question_id') as $report) {
            $done += $gate->resolve($report->question()->firstOrFail(), $this->accept, $actor);
        }

        return Action::message("{$done} report(s) resolved.");
    }
}
