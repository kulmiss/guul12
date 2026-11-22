<?php

namespace App\Nova\Resources;

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

    public static function authorizedToViewAny($request): bool
    {
        return $request->user()?->can(static::$ability) ?? false;
    }

    public function authorizedToView($request): bool
    {
        return static::authorizedToViewAny($request);
    }

    public static function authorizedToCreate($request): bool
    {
        return static::authorizedToViewAny($request);
    }

    public function authorizedToUpdate($request): bool
    {
        return static::authorizedToViewAny($request);
    }

    public function authorizedToDelete($request): bool
    {
        return static::authorizedToViewAny($request);
    }

    public function authorizedToReplicate($request): bool
    {
        return false;
    }

    public static function indexQuery(NovaRequest $request, $query)
    {
        return $query;
    }
}
