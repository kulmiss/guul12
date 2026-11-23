<?php

namespace App\Nova\Resources;

use App\Models\PremiumGrant as PremiumGrantModel;
use App\Nova\Actions\RevokePremium;
use App\Nova\Fields\Show;
use App\Nova\Filters\FilterSets;
use App\Nova\Lenses;
use App\Nova\Resources\Concerns\ReadOnlyResource;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Date;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;
use Laravel\Nova\Query\Search\SearchableRelation;

/**
 * Read-only history of Premium grants. Grants are made with the "Give Premium" action on a student.
 *
 * @extends \App\Nova\Resources\Resource<PremiumGrantModel>
 */
class PremiumGrant extends Resource
{
    use ReadOnlyResource;

    /** @var class-string<PremiumGrantModel> */
    public static $model = PremiumGrantModel::class;

    public static $title = 'id';

    public static string $ability = 'manage-premium';

    /** @var list<string> */
    public static $with = ['user', 'grantedBy'];

    /** @return list<string|SearchableRelation> */
    public static function searchableColumns(): array
    {
        return ['payment_ref', new SearchableRelation('user', 'student_id')];
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->hideFromIndex(),
            BelongsTo::make('Student', 'user', Student::class),
            Badge::make('Plan', fn (PremiumGrantModel $grant): string => $grant->plan->value)
                ->map(['term' => 'success', 'year' => 'success', 'referral' => 'info', 'extra' => 'info'])
                ->labels(['term' => 'Term', 'year' => 'Year', 'referral' => 'Referral reward', 'extra' => 'Extra days']),
            Badge::make('State', fn (PremiumGrantModel $g): string => $g->revoked_at !== null ? 'undone' : ($g->ends_at->isPast() ? 'expired' : ($g->starts_at->isFuture() ? 'next' : 'active')))
                ->map(['active' => 'success', 'next' => 'info', 'expired' => 'warning', 'undone' => 'danger'])
                ->labels(['active' => 'Active', 'next' => 'Starts later', 'expired' => 'Expired', 'undone' => 'Undone']),
            Date::make('Starts', 'starts_at')->sortable()->displayUsing(fn ($v): string => $v?->setTimezone(Show::TIMEZONE)->format('j M Y') ?? '—'),
            Date::make('Ends', 'ends_at')->sortable()->displayUsing(fn ($v): string => $v?->setTimezone(Show::TIMEZONE)->format('j M Y') ?? '—'),
            Text::make('Payment reference', 'payment_ref')->copyable(),
            Text::make('Given by', fn (PremiumGrantModel $g): string => $g->grantedBy->name ?? '— (automatic)'),
            ...Show::when('Given', 'created_at'),
            DateTime::make('Undone at', 'revoked_at')->onlyOnDetail()->displayUsing(fn ($v): string => Show::full($v)),
            Text::make('Note')->hideFromIndex(),
        ];
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [RevokePremium::make()];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::premiumGrants();
    }

    /** @return array<int, Lens> */
    public function lenses(NovaRequest $request): array
    {
        return [new Lenses\GivenToday, new Lenses\UndoneGrants];
    }
}
