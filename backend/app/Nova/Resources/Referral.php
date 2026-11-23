<?php

namespace App\Nova\Resources;

use App\Models\Referral as ReferralModel;
use App\Nova\Fields\Show;
use App\Nova\Filters\FilterSets;
use App\Nova\Resources\Concerns\ReadOnlyResource;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;
use Laravel\Nova\Query\Search\SearchableRelation;

/**
 * Invites between students. A referral counts when the new student really studied (the rules are
 * Nova settings); the inviter then gets Premium days automatically. Read-only history.
 *
 * @extends \App\Nova\Resources\Resource<ReferralModel>
 */
class Referral extends Resource
{
    use ReadOnlyResource;

    /** @var class-string<ReferralModel> */
    public static $model = ReferralModel::class;

    public static $title = 'id';

    public static string $ability = 'manage-premium';

    /** @var list<string> */
    public static $with = ['inviter', 'invitee', 'premiumGrant'];

    /** @return list<string|SearchableRelation> */
    public static function searchableColumns(): array
    {
        return [new SearchableRelation('inviter', 'student_id'), new SearchableRelation('invitee', 'student_id')];
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable()->hideFromIndex(),
            BelongsTo::make('Invited by', 'inviter', Student::class),
            BelongsTo::make('New student', 'invitee', Student::class),
            Badge::make('Status', fn (ReferralModel $r): string => $r->status)
                ->map([ReferralModel::PENDING => 'info', ReferralModel::VALID => 'success', ReferralModel::EXPIRED => 'warning'])
                ->labels([ReferralModel::PENDING => 'Waiting', ReferralModel::VALID => 'Counted', ReferralModel::EXPIRED => 'Expired'])
                ->help('Waiting = the new student has not studied enough yet · Counted = Premium days given · Expired = not in time.'),
            ...Show::when('Invited', 'created_at'),
            DateTime::make('Counted at', 'valid_at')->onlyOnDetail()->displayUsing(fn (mixed $v): string => Show::full($v)),
            BelongsTo::make('Premium grant', 'premiumGrant', PremiumGrant::class)->nullable()->hideFromIndex(),
        ];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::referrals();
    }
}
