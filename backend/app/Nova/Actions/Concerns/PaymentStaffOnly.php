<?php

namespace App\Nova\Actions\Concerns;

use App\Models\User;
use Illuminate\Http\Request;

/**
 * Payment work is for super admins and payments helpers (the "manage-premium" ability).
 * Hidden from everyone else and refused if called directly.
 */
trait PaymentStaffOnly
{
    protected function onlyPaymentStaff(): static
    {
        $allowed = fn (Request $request): bool => $request->user() instanceof User && $request->user()->can('manage-premium');

        return $this->canSee($allowed)->canRun(fn (Request $request): bool => $allowed($request));
    }
}
