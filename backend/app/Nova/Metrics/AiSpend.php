<?php

namespace App\Nova\Metrics;

use App\Services\ExplainService;
use App\Services\Settings;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

/** "Explain more" spending this month (Addis Ababa calendar) against the monthly cap in settings. */
class AiSpend extends Value
{
    public function calculate(NovaRequest $request): ValueResult
    {
        $dollars = round(app(ExplainService::class)->spentThisMonth() / 1_000_000, 2);
        $cap = app(Settings::class)->int('ai_monthly_cap_usd');

        return $this->result($dollars)
            ->prefix('$')
            ->suffix('of $'.$cap.' this month')
            ->withoutSuffixInflection()
            ->format('0,0.00')
            ->allowZeroResult();
    }

    public function name(): string
    {
        return 'AI spend ("Explain more")';
    }

    public function uriKey(): string
    {
        return 'ai-spend';
    }
}
