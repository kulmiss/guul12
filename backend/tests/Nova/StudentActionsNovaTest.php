<?php

namespace Tests\Nova;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\PremiumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Student page actions: Extend / End / Undo Premium (the last two need the word CONFIRM) and the CSV
 * export (filters respected, no private fields, safe cells, logged). Payment staff only.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class StudentActionsNovaTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    private User $helper;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow('2026-11-23 07:00:00');
        $this->helper = User::factory()->staff(UserRole::PaymentsHelper)->create(['name' => 'Hawa']);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    /**
     * @param  array<string, mixed>  $fields
     * @return TestResponse<Response>
     */
    private function runAction(User $as, string $action, int|string $ids, array $fields = []): TestResponse
    {
        return $this->actingAs($as)->postJson("/nova-api/students/action?action={$action}", ['resources' => (string) $ids, ...$fields]);
    }

    /** @return list<string> */
    private function actionKeys(User $as, int $studentId): array
    {
        $actions = $this->actingAs($as)->getJson("/nova-api/students/actions?resourceId={$studentId}&display=detail")->assertOk()->json('actions');
        $this->assertIsArray($actions);

        return array_column($actions, 'uriKey');
    }

    public function test_extend_end_and_undo_premium(): void
    {
        $student = User::factory()->create();
        $premium = app(PremiumService::class);

        $this->runAction($this->helper, 'extend-premium', $student->id, ['days' => 0, 'reason' => 'x'])->assertUnprocessable();
        $this->runAction($this->helper, 'extend-premium', $student->id, ['days' => 14, 'reason' => ''])->assertUnprocessable();
        $this->runAction($this->helper, 'extend-premium', $student->id, ['days' => 14, 'reason' => 'Prize'])->assertOk();
        $this->assertTrue($premium->activeUntil($student)?->equalTo(Carbon::now()->addDays(14)));
        // The grants list shows the hand-given days with their own label.
        $grants = $this->actingAs($this->helper)->getJson('/nova-api/premium-grants')->assertOk()->json('resources.0.fields');
        $this->assertIsArray($grants);
        $plan = array_values(array_filter($grants, fn (mixed $f): bool => is_array($f) && $f['name'] === 'Plan'));
        $this->assertSame('Extra days', $plan[0]['label'] ?? null);

        // End and Undo change what the student has, so they need the word CONFIRM.
        $this->runAction($this->helper, 'end-premium', $student->id, ['reason' => 'Refund'])->assertUnprocessable();
        $this->runAction($this->helper, 'end-premium', $student->id, ['reason' => 'Refund', 'confirm' => 'confirm'])->assertUnprocessable();
        $this->runAction($this->helper, 'end-premium', $student->id, ['reason' => 'Refund', 'confirm' => 'CONFIRM'])->assertOk();
        $this->assertFalse($premium->isPremium($student));

        $premium->grant($student, PremiumPlan::Term, $this->helper, 'TX-9');
        $this->runAction($this->helper, 'undo-last-premium', $student->id, ['reason' => 'Wrong student'])->assertUnprocessable();
        $this->runAction($this->helper, 'undo-last-premium', $student->id, ['reason' => 'Wrong student', 'confirm' => 'CONFIRM'])->assertOk();
        $this->assertFalse($premium->isPremium($student));
        $this->assertSame(['premium.extended', 'premium.ended', 'premium.granted', 'premium.revoked'],
            AuditLog::query()->where('action', 'like', 'premium.%')->orderBy('id')->pluck('action')->all());
    }

    public function test_premium_actions_are_for_payment_staff_only(): void
    {
        $student = User::factory()->create();
        $admin = User::factory()->staff(UserRole::SuperAdmin)->create();
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();

        foreach ([$this->helper, $admin] as $staff) {
            $keys = $this->actionKeys($staff, $student->id);
            foreach (['give-premium', 'extend-premium', 'end-premium', 'undo-last-premium', 'export-csv'] as $key) {
                $this->assertContains($key, $keys);
            }
        }
        foreach (['extend-premium', 'end-premium', 'undo-last-premium', 'export-csv'] as $key) {
            $this->assertContains($this->runAction($checker, $key, $student->id, ['days' => 5, 'reason' => 'x', 'confirm' => 'CONFIRM'])->status(), [403, 404]);
        }
        $this->assertFalse(app(PremiumService::class)->isPremium($student));
    }

    public function test_csv_export_keeps_filters_leaves_out_private_fields_and_is_logged(): void
    {
        $somali = User::factory()->create(['grade' => 12, 'region' => 'somali', 'email' => 'private@example.com', 'nickname' => 'Ayaan', 'city' => 'Jigjiga']);
        app(PremiumService::class)->grant($somali, PremiumPlan::Term, $this->helper, 'TX-10');
        $tricky = User::factory()->create(['grade' => 12, 'region' => '=HYPERLINK("http://x")']);
        $other = User::factory()->create(['grade' => 9, 'region' => 'afar']);

        $filters = base64_encode((string) json_encode([['student-grade' => '12']]));
        $response = $this->actingAs($this->helper)->post("/nova-api/students/action?action=export-csv&filters={$filters}", ['resources' => 'all']);
        $response->assertOk();
        $csv = $response->streamedContent();

        $this->assertStringStartsWith("\u{FEFF}", $csv, 'a byte-order mark, so Excel reads Amharic and Somali letters');
        $rows = array_map(fn (string $line): array => str_getcsv($line, ',', '"', ''),
            array_values(array_filter(explode("\n", str_replace("\r", '', substr($csv, 3))))));
        $this->assertSame(['Student ID', 'Grade', 'Stream', 'Region', 'Plan', 'Premium until', 'Joined', 'Last active'], $rows[0]);
        $this->assertCount(3, $rows, 'header + the two grade 12 students');
        $byId = array_column($rows, null, 0);
        $this->assertSame([$somali->student_id, '12', 'Natural', 'Somali', 'Premium'], array_slice($byId[$somali->student_id], 0, 5));
        $this->assertSame("'=HYPERLINK(\"http://x\")", $byId[$tricky->student_id][3], 'a cell that starts like a formula is made safe');
        $this->assertStringNotContainsString($other->student_id, $csv);
        foreach (['private@example.com', 'Ayaan', 'Jigjiga'] as $private) {
            $this->assertStringNotContainsString($private, $csv);
        }

        $log = AuditLog::query()->where('action', 'export.students')->firstOrFail();
        $this->assertSame($this->helper->id, $log->actor_id);
        $this->assertSame(2, $log->data['rows'] ?? null);
    }
}
