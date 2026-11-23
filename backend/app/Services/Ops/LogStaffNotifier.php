<?php

namespace App\Services\Ops;

use App\Contracts\StaffNotifier;
use App\Models\User;
use Illuminate\Support\Facades\Log;

/**
 * Staff messages when Nova is not installed: written to the log only.
 */
final class LogStaffNotifier implements StaffNotifier
{
    public function notify(User $staff, string $message, string $type = 'info', ?string $url = null): void
    {
        Log::info("[staff #{$staff->id}] {$message}");
    }

    public function notifyAll(string $ability, string $message, string $type = 'info', ?string $url = null, ?User $except = null): void
    {
        Log::info("[staff: {$ability}] {$message}");
    }
}
