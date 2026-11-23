<?php

namespace App\Nova\Actions\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Nickname moderation is for super admins and content checkers (the "moderate" ability).
 * Hidden from everyone else and refused if called directly.
 */
trait ModeratorsOnly
{
    protected function onlyModerators(): static
    {
        $allowed = fn (Request $request): bool => $request->user() instanceof User && $request->user()->can('moderate');

        return $this->canSee($allowed)->canRun(fn (Request $request): bool => $allowed($request));
    }
}
