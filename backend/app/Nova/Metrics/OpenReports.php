<?php

namespace App\Nova\Metrics;

use App\Enums\ReportStatus;
use App\Models\QuestionReport;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

/** Question reports from students that nobody has checked yet. */
class OpenReports extends Value
{
    public function calculate(NovaRequest $request): ValueResult
    {
        $open = QuestionReport::query()->where('status', ReportStatus::Open->value)->count();

        return $this->result($open)->format('0,0')->allowZeroResult();
    }

    public function name(): string
    {
        return 'Open question reports';
    }

    public function uriKey(): string
    {
        return 'open-reports';
    }
}
