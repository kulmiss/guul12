<?php

namespace App\Nova\Resources;

use App\Enums\UserRole;
use App\Models\User;
use App\Nova\Actions\GrantPremium;
use App\Services\PremiumService;
use App\Services\StudentIdGenerator;
use Illuminate\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Students. Helpers find a student by the student ID the student sends with the payment proof.
 *
 * @extends resource<User>
 */
class Student extends Resource
{
    public static $model = User::class;

    public static $title = 'student_id';

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

    public static function indexQuery(NovaRequest $request, $query)
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
        // Account deletion requests are handled by the super admin only.
        return $request->user()?->can('manage-settings') ?? false;
    }

    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('Student ID', 'student_id')->sortable()->copyable(),
            Badge::make('Premium', function (): string {
                /** @var User $user */
                $user = $this->resource;
                $until = app(PremiumService::class)->activeUntil($user);

                return $until === null ? 'Free' : 'Premium until '.$until->format('Y-m-d');
            })->map(['Free' => 'info'])->withIcons()->exceptOnForms(),
            Boolean::make('Signed in', fn (): bool => $this->resource->google_sub !== null),
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

    public function actions(NovaRequest $request): array
    {
        return [
            GrantPremium::make()
                ->canSee(fn ($request): bool => $request->user()?->can('manage-premium') ?? false)
                ->canRun(fn ($request): bool => $request->user()?->can('manage-premium') ?? false),
        ];
    }
}
