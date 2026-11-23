<?php

namespace App\Nova\Metrics;

use App\Models\AttemptAnswer;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

/** Answers that reached the server (practice, exams, challenges, battles) in the chosen range. */
class AnswersGiven extends Value
{
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this->count($request, AttemptAnswer::query())->format('0,0');
    }

    /** @return array<int|string, string> */
    public function ranges(): array
    {
        return [1 => 'Today', 7 => '7 days', 30 => '30 days'];
    }

    public function name(): string
    {
        return 'Answers given';
    }

    public function uriKey(): string
    {
        return 'answers-given';
    }
}
