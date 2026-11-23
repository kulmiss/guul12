<?php

namespace App\Services\Ops;

use App\Mail\StaffTestEmail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\FeatureSwitches;
use App\Services\Leaderboard;
use App\Services\Settings;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;

/**
 * Small tools for the super admin in Nova (Settings): refresh cached data, test the alert channels.
 */
final class StaffTools
{
    public function __construct(
        private readonly Settings $settings,
        private readonly FeatureSwitches $switches,
        private readonly StaffAlert $alert,
    ) {}

    /**
     * The app and the landing page read settings and switches from a 5-minute cache; this makes a
     * change show at once. Returns what was cleared.
     *
     * @return list<string>
     */
    public function refreshCaches(User $actor): array
    {
        $this->settings->flush();
        $this->switches->flush();
        Cache::forever(Leaderboard::VERSION_KEY, (int) Cache::get(Leaderboard::VERSION_KEY, 0) + 1);
        $done = ['settings', 'feature switches', 'leaderboard'];
        AuditLog::write($actor, 'ops.cache_refreshed', null, ['cleared' => $done]);

        return $done;
    }

    public function sendTestTelegram(User $actor): ToolResult
    {
        $sent = $this->alert->send('Test message from Nova (sent by '.($actor->name ?? 'staff').').');
        AuditLog::write($actor, 'ops.test_telegram', null, ['sent' => $sent]);

        return match (true) {
            ! $this->alert->isConfigured() => new ToolResult(false, 'Telegram is not set up (TELEGRAM_BOT_TOKEN and TELEGRAM_OPS_CHAT_ID in .env). The test went to the log only.'),
            $sent => new ToolResult(true, 'Test message sent to the staff Telegram group.'),
            default => new ToolResult(false, 'Telegram did not accept the message. Check the bot token and the chat ID (see the log).'),
        };
    }

    public function sendTestEmail(User $actor): ToolResult
    {
        if ($actor->email === null || $actor->email === '') {
            return new ToolResult(false, 'Your staff account has no email address.');
        }
        Mail::to((string) $actor->email)->send(new StaffTestEmail);
        AuditLog::write($actor, 'ops.test_email', null, ['mailer' => (string) config('mail.default')]);

        return config('mail.default') === 'log'
            ? new ToolResult(false, 'Email is set to the log (MAIL_MAILER=log): the test was written to the log, not sent. Set real mail settings in .env to send.')
            : new ToolResult(true, "Test email sent to {$actor->email}.");
    }
}
