<?php

namespace App\Contracts;

use App\Models\User;

/**
 * Short messages for staff (a bulk change finished, a new report, a failed backup). In Nova they are
 * the bell notifications; without Nova they go to the log.
 */
interface StaffNotifier
{
    /** @param  string  $type  info, success, warning or error */
    public function notify(User $staff, string $message, string $type = 'info', ?string $url = null): void;

    /** Every staff member with this ability (see the gates in AppServiceProvider), except $except. */
    public function notifyAll(string $ability, string $message, string $type = 'info', ?string $url = null, ?User $except = null): void;
}
