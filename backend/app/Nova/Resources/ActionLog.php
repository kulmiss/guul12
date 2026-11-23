<?php

namespace App\Nova\Resources;

use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionEvent;
use Laravel\Nova\Actions\ActionResource;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Nova's own record of actions run (who ran what on which row, the fields typed in). It can hold
 * Premium and payment details, so only super admins may read it; everyone else gets 403.
 * Registered as config('nova.actions.resource') in NovaServiceProvider. Nova itself never lets
 * anyone create, edit or delete these rows.
 *
 * @extends ActionResource<ActionEvent>
 */
class ActionLog extends ActionResource
{
    public static function authorizedToViewAny(Request $request): bool
    {
        return $request->user()?->can('manage-settings') ?? false;
    }

    public function authorizedToView(Request $request): bool
    {
        return static::authorizedToViewAny($request);
    }

    /* Nova's controllers call these policy-based checks; see App\Nova\Resources\Resource. */

    public function authorizeToViewAny(Request $request): void
    {
        throw_unless(static::authorizedToViewAny($request), AuthorizationException::class);
    }

    public function authorizeToView(Request $request): void
    {
        throw_unless($this->authorizedToView($request), AuthorizationException::class);
    }

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
}
