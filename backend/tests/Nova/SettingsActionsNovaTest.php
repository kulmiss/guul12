<?php

namespace Tests\Nova;

use App\Enums\UserRole;
use App\Jobs\RunBackup;
use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Settings tools in Nova (App settings page): refresh cached data, test Telegram and email, run a
 * backup now. Super admins only.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class SettingsActionsNovaTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    /** @return TestResponse<Response> */
    private function tool(string $action, User $as): TestResponse
    {
        return $this->actingAs($as)->postJson("/nova-api/app-settings/action?action={$action}", ['resources' => '']);
    }

    public function test_super_admin_runs_the_tools(): void
    {
        $admin = User::factory()->staff(UserRole::SuperAdmin)->create(['email' => 'boss@kulmiss.test']);
        Queue::fake();
        Mail::fake();
        Http::fake();
        Config::set('guul.telegram.bot_token', '');

        $this->tool('refresh-app-and-landing-data', $admin)->assertOk()
            ->assertJsonFragment(['message' => 'Refreshed: settings, feature switches, leaderboard. The app and the landing page show changes now.']);
        $this->tool('send-test-telegram', $admin)->assertOk()
            ->assertJsonFragment(['danger' => 'Telegram is not set up (TELEGRAM_BOT_TOKEN and TELEGRAM_OPS_CHAT_ID in .env). The test went to the log only.']);
        Config::set('mail.default', 'smtp');
        $this->tool('send-test-email', $admin)->assertOk()->assertJsonFragment(['message' => 'Test email sent to boss@kulmiss.test.']);
        $this->tool('run-backup-now', $admin)->assertOk()
            ->assertJsonFragment(['message' => 'Backup started. You will get a message when it is done (usually within 2 minutes).']);
        Queue::assertPushed(RunBackup::class, fn (RunBackup $job): bool => $job->actorId === $admin->id);
        $this->assertSame(1, AuditLog::query()->where('action', 'ops.backup_started')->count());
    }

    public function test_only_super_admins_see_and_run_them(): void
    {
        Queue::fake();
        foreach ([UserRole::PaymentsHelper, UserRole::ContentChecker] as $role) {
            $staff = User::factory()->staff($role)->create();
            foreach (['refresh-app-and-landing-data', 'send-test-telegram', 'send-test-email', 'run-backup-now'] as $action) {
                $this->assertContains($this->tool($action, $staff)->status(), [403, 404], "{$role->value} {$action}");
            }
        }
        Queue::assertNothingPushed();

        $admin = User::factory()->staff(UserRole::SuperAdmin)->create();
        $actions = $this->actingAs($admin)->getJson('/nova-api/app-settings/actions')->assertOk()->json('actions');
        $this->assertIsArray($actions);
        $this->assertSame(['refresh-app-and-landing-data', 'send-test-telegram', 'send-test-email', 'run-backup-now'],
            array_values(array_filter(array_column($actions, 'uriKey'), fn (string $k): bool => in_array($k, ['refresh-app-and-landing-data', 'send-test-telegram', 'send-test-email', 'run-backup-now'], true))));
    }
}
