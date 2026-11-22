<?php

namespace App\Nova\Resources;

use App\Models\PremiumGrant as PremiumGrantModel;
use App\Nova\Actions\RevokePremium;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Read-only history of Premium grants. Grants are made with the "Give Premium" action on a student.
 *
 * @extends resource<PremiumGrantModel>
 */
class PremiumGrant extends Resource
{
    public static $model = PremiumGrantModel::class;

    public static $title = 'id';

    public static $search = ['payment_ref'];

    public static string $ability = 'manage-premium';

    public static function authorizedToCreate($request): bool
    {
        return false;
    }

    public function authorizedToUpdate($request): bool
    {
        return false;
    }

    public function authorizedToDelete($request): bool
    {
        return false;
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Student', 'user', Student::class),
            Text::make('Plan', fn (): string => $this->resource->plan->value),
            DateTime::make('Starts', 'starts_at')->sortable(),
            DateTime::make('Ends', 'ends_at')->sortable(),
            Text::make('Payment reference', 'payment_ref')->copyable(),
            BelongsTo::make('Given by', 'grantedBy', Staff::class)->nullable(),
            DateTime::make('Undone at', 'revoked_at'),
            Text::make('Note')->hideFromIndex(),
        ];
    }

    public function actions(NovaRequest $request): array
    {
        return [RevokePremium::make()];
    }
}
