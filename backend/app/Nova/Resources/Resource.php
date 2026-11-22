<?php

namespace App\Nova\Resources;

use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
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

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query;
    }
}
