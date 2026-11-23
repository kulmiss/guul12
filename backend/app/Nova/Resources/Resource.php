<?php

namespace App\Nova\Resources;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionCollection;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Resource as NovaResource;

/**
 * @template TModel of \Illuminate\Database\Eloquent\Model
 *
 * @extends NovaResource<TModel>
 */
abstract class Resource extends NovaResource
{
    /**
     * Ability (see AppServiceProvider gates) needed to see and edit this resource.
     */
    public static string $ability = 'manage-settings';

    /** "super_admin" → "Super admin": enum values read as plain words in search hits. */
    protected static function words(\BackedEnum|string|null $value): string
    {
        $value = $value instanceof \BackedEnum ? (string) $value->value : (string) $value;

        return ucfirst(str_replace('_', ' ', $value));
    }

    public static function authorizedToViewAny(Request $request): bool
    {
        return $request->user()?->can(static::$ability) ?? false;
    }

    public function authorizedToView(Request $request): bool
    {
        return static::authorizedToViewAny($request);
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return static::authorizedToViewAny($request);
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return static::authorizedToViewAny($request);
    }

    public function authorizedToDelete(Request $request): bool
    {
        return static::authorizedToViewAny($request);
    }

    public function authorizedToReplicate(Request $request): bool
    {
        return false;
    }

    /*
     * Nova's own "authorizeTo..." checks (used when a detail page loads, a form is saved or a row is
     * deleted) only look at policies, and we use abilities instead. Without these, a role could open
     * or save a resource outside its job by calling the address directly.
     */

    public function authorizeToViewAny(Request $request): void
    {
        throw_unless(static::authorizedToViewAny($request), AuthorizationException::class);
    }

    public function authorizeToView(Request $request): void
    {
        throw_unless($this->authorizedToView($request), AuthorizationException::class);
    }

    public function authorizeToUpdate(Request $request): void
    {
        throw_unless($this->authorizedToUpdate($request), AuthorizationException::class);
    }

    public function authorizeToDelete(Request $request): void
    {
        throw_unless($this->authorizedToDelete($request), AuthorizationException::class);
    }

    public function authorizeToReplicate(Request $request): void
    {
        throw_unless($this->authorizedToReplicate($request), AuthorizationException::class);
    }

    /**
     * A role that may not open this resource gets none of its actions, even when calling an
     * action's address directly (Nova then answers "not found").
     */
    public function resolveActions(NovaRequest $request): ActionCollection
    {
        return static::authorizedToViewAny($request) ? parent::resolveActions($request) : ActionCollection::make();
    }

    /*
     * Nova serves the filter and card lists of a resource without checking who asks (its lens list is
     * checked). Filters name staff members and subjects, so a role outside the job is refused here too.
     */

    public function availableFilters(NovaRequest $request): Collection
    {
        $this->authorizeToViewAny($request);

        return parent::availableFilters($request);
    }

    public function availableCards(NovaRequest $request): Collection
    {
        $this->authorizeToViewAny($request);

        return parent::availableCards($request);
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query;
    }
}
