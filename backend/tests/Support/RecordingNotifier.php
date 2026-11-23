<?php

namespace Tests\Support;

use App\Contracts\StaffNotifier;
use App\Models\User;

/**
 * Remembers staff messages instead of sending them.
 */
final class RecordingNotifier implements StaffNotifier
{
    /** @var list<array{to: string, message: string, type: string, url: string|null}> */
    public array $sent = [];

    public function notify(User $staff, string $message, string $type = 'info', ?string $url = null): void
    {
        $this->sent[] = ['to' => 'staff #'.$staff->id, 'message' => $message, 'type' => $type, 'url' => $url];
    }

    public function notifyAll(string $ability, string $message, string $type = 'info', ?string $url = null, ?User $except = null): void
    {
        $this->sent[] = ['to' => $ability.($except !== null ? ' except #'.$except->id : ''), 'message' => $message, 'type' => $type, 'url' => $url];
    }
}
