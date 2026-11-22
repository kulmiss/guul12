<?php

namespace App\Nova\Metrics;

use App\Models\PremiumGrant;
use Illuminate\Support\Carbon;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class PremiumActive extends Value
{
    public function calculate(NovaRequest $request): ValueResult
    {
        $count = PremiumGrant::query()->activeAt(Carbon::now())->distinct()->count('user_id');

        return $this->result($count)->format('0,0');
    }

    public function name(): string
    {
        return 'Premium students now';
    }

    public function uriKey(): string
    {
        return 'premium-active';
    }
}
