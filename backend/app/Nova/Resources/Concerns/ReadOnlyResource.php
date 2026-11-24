<?php

namespace App\Nova\Resources\Concerns;

use Illuminate\Http\Request;

/**
 * A resource staff can look at but not create, edit, delete or attach to. Changes happen elsewhere
 * (content files, the app, or a logged action), so the history stays true.
 */
trait ReadOnlyResource
{
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

    public function authorizedToAttachAny(Request $request, mixed $model): bool
    {
        return false;
    }

    public function authorizedToAttach(Request $request, mixed $model): bool
    {
        return false;
    }

    public function authorizedToDetach(Request $request, mixed $model, mixed $relationship): bool
    {
        return false;
    }
}
