<?php

namespace App\Nova\Resources;

use App\Enums\UserRole;
use App\Models\User;
use App\Nova\Actions\GrantPremium;
use App\Services\PremiumService;
use App\Services\StudentIdGenerator;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Students. Helpers find a student by the student ID the student sends with the payment proof.
 *
 * @extends \App\Nova\Resources\Resource<User>
 */
class Student extends Resource
{
    /** @var class-string<User> */
    public static $model = User::class;

    public static $title = 'student_id';

    /** @var array<int, string> */
    public static $search = ['student_id', 'nickname'];

    public static string $ability = 'view-students';

    public static function label(): string
    {
        return 'Students';
    }

    public static function uriKey(): string
    {
        return 'students';
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->where('role', UserRole::Student->value);
    }

    /**
     * "st 7k3q9" finds "ST-7K3Q9".
     */
    protected static function applySearch(Builder $query, string $search): Builder
    {
        $normalised = StudentIdGenerator::normalise($search);

        if (StudentIdGenerator::isValid($normalised)) {
            return $query->where('student_id', $normalised);
        }

        return parent::applySearch($query, $search);
    }

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
        // Account deletion requests are handled by the super admin only.
        return $request->user()?->can('manage-settings') ?? false;
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Student ID', 'student_id')->sortable()->copyable(),
            Badge::make('Plan', fn (User $user): string => app(PremiumService::class)->isPremium($user) ? 'Premium' : 'Free')
                ->map(['Free' => 'info', 'Premium' => 'success'])->withIcons()->exceptOnForms(),
            Text::make('Premium until', fn (User $user): string => app(PremiumService::class)->activeUntil($user)?->format('Y-m-d') ?? '—')
                ->exceptOnForms(),
            Boolean::make('Signed in', fn (User $user): bool => $user->google_sub !== null),
            Number::make('Grade')->sortable(),
            Text::make('Stream'),
            Boolean::make('Retaker', 'is_retaker')->hideFromIndex(),
            Text::make('Region')->hideFromIndex(),
            Text::make('City')->hideFromIndex(),
            Text::make('Nickname')->hideFromIndex(),
            DateTime::make('Joined', 'created_at')->sortable()->exceptOnForms(),
            DateTime::make('Last active', 'last_active_at')->sortable()->exceptOnForms(),
            HasMany::make('Premium grants', 'premiumGrants', PremiumGrant::class),
        ];
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [
            GrantPremium::make()
                ->canSee(fn ($request): bool => $request->user()?->can('manage-premium') ?? false)
                ->canRun(fn ($request): bool => $request->user()?->can('manage-premium') ?? false),
        ];
    }
}
