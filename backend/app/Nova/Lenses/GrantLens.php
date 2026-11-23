<?php

namespace App\Nova\Lenses;

use App\Models\PremiumGrant;
use App\Nova\Fields\Show;
use App\Nova\Filters\FilterSets;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Shared columns of the Premium grant lenses.
 */
abstract class GrantLens extends Lens
{
    /** @return list<Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->hideFromIndex(),
            Text::make('Student', fn (PremiumGrant $g): string => $g->user->student_id),
            Text::make('Plan', fn (PremiumGrant $g): string => ucfirst($g->plan->value)),
            Text::make('Payment reference', 'payment_ref'),
            Text::make('Given by', fn (PremiumGrant $g): string => $g->grantedBy->name ?? '— (automatic)'),
            ...Show::when('Given', 'created_at'),
            Text::make('Undone', fn (PremiumGrant $g): string => $g->revoked_at !== null ? Show::ago($g->revoked_at) : '—'),
        ];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::premiumGrants();
    }
}
