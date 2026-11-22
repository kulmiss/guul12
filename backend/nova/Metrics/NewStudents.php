<?php

namespace App\Nova\Metrics;

use App\Enums\UserRole;
use App\Models\User;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Metrics\Value;
use Laravel\Nova\Metrics\ValueResult;

class NewStudents extends Value
{
    public function calculate(NovaRequest $request): ValueResult
    {
        return $this->count($request, User::query()->where('role', UserRole::Student->value));
    }

    /**
     * @return array<int|string, string>
     */
    public function ranges(): array
    {
        return [
            1 => 'Today',
            7 => '7 days',
            30 => '30 days',
            'MTD' => 'Month to date',
        ];
    }

    public function uriKey(): string
    {
        return 'new-students';
    }
}
