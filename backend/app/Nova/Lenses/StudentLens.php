<?php

namespace App\Nova\Lenses;

use App\Enums\UserRole;
use App\Nova\Filters\FilterSets;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Shared columns and rules of the student lenses.
 */
abstract class StudentLens extends Lens
{
    protected static function students(Builder $query): Builder
    {
        return $query->where('role', UserRole::Student->value);
    }

    /** @return list<Field> */
    protected function profile(): array
    {
        return [
            ID::make()->hideFromIndex(),
            Text::make('Student ID', 'student_id'),
            Text::make('Nickname'),
            Number::make('Grade'),
        ];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::students();
    }
}
