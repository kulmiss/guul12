<?php

namespace App\Nova\Metrics;

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

/** Students who opened the app (and reached the server) in the chosen range. */
class ActiveStudents extends Value
{
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this->count(
            $request,
            User::query()->where('role', UserRole::Student->value),
            dateColumn: 'last_active_at',
        )->format('0,0');
    }

    /** @return array<int|string, string> */
    public function ranges(): array
    {
        return [1 => 'Today', 7 => '7 days', 30 => '30 days'];
    }

    public function name(): string
    {
        return 'Active students';
    }

    public function uriKey(): string
    {
        return 'active-students';
    }
}
