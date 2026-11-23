<?php

namespace Tests\Feature;

use App\Models\AiExplanation;
use App\Models\Question;
use App\Models\User;
use App\Services\Ops\BackupService;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Testing\PendingCommand;
use Tests\TestCase;

/**
 * DatabaseMigrations (not RefreshDatabase): a backup cannot be made inside the test transaction.
 */
class OpsTest extends TestCase
{
    use DatabaseMigrations;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/guul12-backups-'.bin2hex(random_bytes(4));
        config(['guul.backups.path' => $this->dir, 'guul.backups.keep' => 3]);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->dir.'/*') ?: [] as $f) {
            @unlink($f);
        }
        @rmdir($this->dir);
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function cmd(string $command): PendingCommand
    {
        $pending = $this->artisan($command);
        $this->assertInstanceOf(PendingCommand::class, $pending);

        return $pending;
    }

    public function test_backup_of_the_current_database_can_be_read_back(): void
    {
        User::factory()->create(['student_id' => 'ST-BKUP1']);

        $this->cmd('guul:backup')->assertSuccessful()->expectsOutputToContain('Backup written: guul12-');
        $files = BackupService::fromConfig()->files();
        $this->assertCount(1, $files);
        $this->assertSame('600', substr(sprintf('%o', fileperms($files[0])), -3), 'only the server user can read it');

        $raw = (string) gzdecode((string) file_get_contents($files[0]));
        if (DB::connection()->getDriverName() === 'sqlite') {
            // Open the copy as a database and find the student.
            $copy = $this->dir.'/check.sqlite';
            file_put_contents($copy, $raw);
            $pdo = new \PDO('sqlite:'.$copy);
            $statement = $pdo->query("select student_id from users where student_id = 'ST-BKUP1'");
            $this->assertNotFalse($statement);
            $this->assertSame('ST-BKUP1', $statement->fetchColumn());
        } else {
            $this->assertStringContainsString('CREATE TABLE `users`', $raw);
            $this->assertStringContainsString('ST-BKUP1', $raw);
        }
    }

    public function test_only_the_newest_backups_are_kept(): void
    {
        mkdir($this->dir, 0700, true);
        foreach (['20260101-000000', '20260102-000000', '20260103-000000', '20260104-000000'] as $t) {
            file_put_contents($this->dir."/guul12-{$t}.sqlite.gz", 'old');
        }
        $this->cmd('guul:backup')->assertSuccessful();
        $names = array_map('basename', BackupService::fromConfig()->files());
        $this->assertCount(3, $names);
        $this->assertStringStartsWith('guul12-2026', $names[0]);
        $this->assertSame(['guul12-20260104-000000.sqlite.gz', 'guul12-20260103-000000.sqlite.gz'], array_slice($names, 1));
    }

    public function test_a_failed_backup_alerts_staff(): void
    {
        // The backup folder path is a file, so it cannot be created.
        file_put_contents($this->dir, 'not a folder');
        $log = Log::spy();
        $this->cmd('guul:backup')->assertFailed();
        $log->shouldHaveReceived('warning')->withArgs(fn (string $m): bool => str_contains($m, 'Backup failed'))->once();
        unlink($this->dir);
    }

    public function test_watchdog_alerts_only_when_something_changes(): void
    {
        config([
            'guul.backups.required' => true,
            'guul.watchdog.min_free_disk_percent' => 0,
            'guul.telegram.bot_token' => '123456:secret-token',
            'guul.telegram.ops_chat_id' => '-1001',
        ]);
        Http::fake(['api.telegram.org/*' => Http::response(['ok' => true])]);
        Cache::flush();

        $this->cmd('guul:watchdog')->assertFailed()->expectsOutputToContain('There is no database backup yet.');
        $this->cmd('guul:watchdog')->assertFailed();
        Http::assertSentCount(1);
        Http::assertSent(fn (HttpRequest $r): bool => $r['chat_id'] === '-1001'
            && str_contains((string) $r['text'], 'There is no database backup yet.'));

        $this->cmd('guul:backup')->assertSuccessful();
        $this->cmd('guul:watchdog')->assertSuccessful()->expectsOutputToContain('All good.');
        Http::assertSentCount(2);
        Http::assertSent(fn (HttpRequest $r): bool => $r['text'] === 'Guul 12: All good again.');

        // An old backup is a problem again.
        Carbon::setTestNow(Carbon::now()->addHours(27));
        $this->cmd('guul:watchdog')->assertFailed()->expectsOutputToContain('older than 26 hours');
    }

    public function test_watchdog_warns_about_the_ai_budget_and_works_without_telegram(): void
    {
        config(['guul.backups.required' => false, 'guul.watchdog.min_free_disk_percent' => 0, 'guul.telegram.bot_token' => null]);
        Http::fake();
        Cache::flush();
        AiExplanation::query()->create([
            'question_id' => Question::factory()->create()->id,
            'question_version' => 1,
            'body' => 'x',
            'model' => 'm',
            'input_tokens' => 1,
            'output_tokens' => 1,
            'cost_micro_usd' => 17_000_000, // $17 of the $20 cap
        ]);
        $this->cmd('guul:watchdog')->assertFailed()->expectsOutputToContain('AI budget');
        Http::assertNothingSent();
    }

    public function test_watchdog_warns_about_low_disk_space(): void
    {
        // No disk is ever 101% free, so this always reports low space.
        config(['guul.backups.required' => false, 'guul.watchdog.min_free_disk_percent' => 101, 'guul.telegram.bot_token' => null]);
        Cache::flush();
        $this->cmd('guul:watchdog')->assertFailed()->expectsOutputToContain('Less than 101% disk space is free.');
    }

    public function test_health_endpoint_says_ok_without_details(): void
    {
        $this->getJson('/health')->assertOk()->assertExactJson(['status' => 'ok']);
        $this->get('/up')->assertOk();
    }

    public function test_jobs_are_scheduled(): void
    {
        $this->cmd('schedule:list')
            ->expectsOutputToContain('guul:backup')
            ->expectsOutputToContain('guul:watchdog')
            ->assertSuccessful();
    }
}
