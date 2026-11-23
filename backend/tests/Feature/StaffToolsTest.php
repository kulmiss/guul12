<?php

namespace Tests\Feature;

use App\Contracts\StaffNotifier;
use App\Enums\UserRole;
use App\Jobs\RunBackup;
use App\Mail\StaffTestEmail;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\FeatureSwitches;
use App\Services\Leaderboard;
use App\Services\Ops\BackupFailed;
use App\Services\Ops\BackupService;
use App\Services\Ops\StaffAlert;
use App\Services\Ops\StaffTools;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Settings tools for the super admin: refresh cached data, test Telegram and email, backup now.
 */
class StaffToolsTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->staff(UserRole::SuperAdmin)->create(['email' => 'admin@kulmiss.test']);
    }

    public function test_refresh_clears_settings_switches_and_leaderboard(): void
    {
        app(Settings::class)->get('exam_date');
        app(FeatureSwitches::class)->all();
        $this->assertTrue(Cache::has('app_settings.all'));
        $this->assertTrue(Cache::has('feature_switches.all'));
        $version = (int) Cache::get(Leaderboard::VERSION_KEY, 0);

        $done = app(StaffTools::class)->refreshCaches($this->admin);

        $this->assertSame(['settings', 'feature switches', 'leaderboard'], $done);
        $this->assertFalse(Cache::has('app_settings.all'));
        $this->assertFalse(Cache::has('feature_switches.all'));
        $this->assertSame($version + 1, (int) Cache::get(Leaderboard::VERSION_KEY));
        $this->assertSame(['cleared' => $done], AuditLog::query()->where('action', 'ops.cache_refreshed')->firstOrFail()->data);
    }

    public function test_telegram_test_says_when_it_is_not_set_up(): void
    {
        Http::fake();
        Config::set('guul.telegram.bot_token', '');
        $this->assertFalse(app(StaffAlert::class)->isConfigured());
        $this->assertSame('Telegram is not set up (TELEGRAM_BOT_TOKEN and TELEGRAM_OPS_CHAT_ID in .env). The test went to the log only.',
            app(StaffTools::class)->sendTestTelegram($this->admin)->message);
        Http::assertNothingSent();

        Config::set('guul.telegram.bot_token', '123456789:'.str_repeat('a', 35));
        Config::set('guul.telegram.ops_chat_id', '-100123');
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        $sent = app(StaffTools::class)->sendTestTelegram($this->admin);
        $this->assertTrue($sent->ok);
        $this->assertSame('Test message sent to the staff Telegram group.', $sent->message);
        Http::assertSent(fn ($request): bool => str_contains((string) $request['text'], 'Test message from Nova'));
    }

    public function test_test_email_goes_to_the_staff_member_and_explains_log_mode(): void
    {
        Mail::fake();
        Config::set('mail.default', 'log');
        $this->assertSame('Email is set to the log (MAIL_MAILER=log): the test was written to the log, not sent. Set real mail settings in .env to send.',
            app(StaffTools::class)->sendTestEmail($this->admin)->message);

        Config::set('mail.default', 'smtp');
        $sent = app(StaffTools::class)->sendTestEmail($this->admin);
        $this->assertTrue($sent->ok);
        $this->assertSame('Test email sent to admin@kulmiss.test.', $sent->message);
        Mail::assertSent(StaffTestEmail::class, 2);
        Mail::assertSent(StaffTestEmail::class, fn (StaffTestEmail $mail): bool => $mail->hasTo('admin@kulmiss.test'));

        $noEmail = User::factory()->staff(UserRole::SuperAdmin)->create(['email' => null]);
        $this->assertSame('Your staff account has no email address.', app(StaffTools::class)->sendTestEmail($noEmail)->message);
    }

    public function test_backup_job_reports_the_result_to_the_staff_member(): void
    {
        $notifier = new class implements StaffNotifier
        {
            /** @var list<string> */
            public array $notes = [];

            public function notify(User $staff, string $message, string $type = 'info', ?string $url = null): void
            {
                $this->notes[] = "{$staff->id} {$type}: {$message}";
            }

            public function notifyAll(string $ability, string $message, string $type = 'info', ?string $url = null): void
            {
                $this->notes[] = "all {$ability} {$type}: {$message}";
            }
        };

        $this->app->instance(BackupService::class, new class extends BackupService
        {
            public function __construct() {}

            public function run(): string
            {
                return '/srv/backups/guul12-20261123-070000.sql.gz';
            }
        });
        (new RunBackup($this->admin->id))->handle($notifier, app(StaffAlert::class), app(BackupService::class));
        $this->assertSame(["{$this->admin->id} success: Backup written: guul12-20261123-070000.sql.gz"], $notifier->notes);

        $notifier->notes = [];
        Http::fake();
        $this->app->instance(BackupService::class, new class extends BackupService
        {
            public function __construct() {}

            public function run(): string
            {
                throw new BackupFailed('mysqldump was not found.');
            }
        });
        (new RunBackup($this->admin->id))->handle($notifier, app(StaffAlert::class), app(BackupService::class));
        $this->assertSame(["{$this->admin->id} error: Backup failed: mysqldump was not found."], $notifier->notes);
    }
}
