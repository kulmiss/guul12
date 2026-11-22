<?php

namespace App\Nova\Resources;

use App\Models\PremiumGrant as PremiumGrantModel;
use App\Nova\Actions\RevokePremium;
use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Read-only history of Premium grants. Grants are made with the "Give Premium" action on a student.
 *
 * @extends \App\Nova\Resources\Resource<PremiumGrantModel>
 */
class PremiumGrant extends Resource
{
    /** @var class-string<PremiumGrantModel> */
    public static $model = PremiumGrantModel::class;

    public static $title = 'id';

    /** @var array<int, string> */
    public static $search = ['payment_ref'];

    public static string $ability = 'manage-premium';

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        return false;
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            BelongsTo::make('Student', 'user', Student::class),
            Text::make('Plan', fn (PremiumGrantModel $grant): string => $grant->plan->value),
            DateTime::make('Starts', 'starts_at')->sortable(),
            DateTime::make('Ends', 'ends_at')->sortable(),
            Text::make('Payment reference', 'payment_ref')->copyable(),
            BelongsTo::make('Given by', 'grantedBy', Staff::class)->nullable(),
            DateTime::make('Undone at', 'revoked_at'),
            Text::make('Note')->hideFromIndex(),
        ];
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [RevokePremium::make()];
    }
}
