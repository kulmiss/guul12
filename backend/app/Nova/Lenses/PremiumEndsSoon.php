<?php

namespace App\Nova\Lenses;

use App\Models\PremiumGrant;
use App\Models\User;
use App\Nova\Fields\Show;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;

/** Premium ends in the next 7 days and nothing follows it: a good moment to remind them. */
class PremiumEndsSoon extends StudentLens
{
    public const SLUG = 'premium-ends-in-7-days';

    public $name = 'Premium ends in 7 days';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        $now = Carbon::now();
        $until = PremiumGrant::query()->selectRaw('max(ends_at)')->whereColumn('user_id', 'users.id')->whereNull('revoked_at');
        $endingSoon = PremiumGrant::query()->select('user_id')->whereNull('revoked_at')->groupBy('user_id')
            ->havingRaw('max(ends_at) > ? and max(ends_at) <= ?', [$now, $now->copy()->addDays(7)]);

        return self::finish($request, self::students($query)->select('users.*')
            ->selectSub($until, 'premium_until')->withCasts(['premium_until' => 'datetime'])
            ->whereIn('users.id', $endingSoon),
            fn (Builder $q) => $q->orderBy('premium_until'));
    }

    /** @return list<Field> */
    public function fields(NovaRequest $request): array
    {
        return [...$this->profile(),
            Text::make('Premium until', fn (User $u): string => Show::full($u->getAttribute('premium_until'))),
        ];
    }
}
