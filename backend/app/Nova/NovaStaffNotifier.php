<?php

namespace App\Nova;

use App\Contracts\StaffNotifier;
use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Support\Facades\Log;
use Laravel\Nova\Notifications\NovaNotification;

/**
 * Staff messages as Nova bell notifications (also written to the log).
 */
final class NovaStaffNotifier implements StaffNotifier
{
    private const ICONS = ['info' => 'information-circle', 'success' => 'check-circle', 'warning' => 'exclamation-circle', 'error' => 'x-circle'];

    public function notify(User $staff, string $message, string $type = 'info', ?string $url = null): void
    {
        $type = array_key_exists($type, self::ICONS) ? $type : 'info';
        $note = NovaNotification::make()->message($message)->type($type)->icon(self::ICONS[$type]);
        if ($url !== null) {
            $note->action('Open', $url);
        }
        $staff->notify($note);
        Log::info("[staff #{$staff->id}] {$message}");
    }

    public function notifyAll(string $ability, string $message, string $type = 'info', ?string $url = null): void
    {
        User::query()->where('role', '!=', UserRole::Student->value)->orderBy('id')->get()
            ->filter(fn (User $staff): bool => $staff->can($ability))
            ->each(fn (User $staff) => $this->notify($staff, $message, $type, $url));
    }
}
