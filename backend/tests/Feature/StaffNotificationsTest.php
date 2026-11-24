<?php

namespace Tests\Feature;

use App\Contracts\StaffNotifier;
use App\Enums\NicknameReportReason;
use App\Enums\PremiumPlan;
use App\Enums\ReportReason;
use App\Enums\UserRole;
use App\Models\Question;
use App\Models\User;
use App\Services\NicknameModeration;
use App\Services\Ops\BackupFailed;
use App\Services\Ops\BackupService;
use App\Services\PaymentRequests;
use App\Services\Quality\QualityGate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\PendingCommand;
use Tests\Support\RecordingNotifier;
use Tests\TestCase;

/**
 * Bell messages for staff: a new report (once per question or student, not for every student who
 * reports the same thing), a payment waiting for approval, a failed backup.
 */
class StaffNotificationsTest extends TestCase
{
    use RefreshDatabase;

    private RecordingNotifier $notifier;

    protected function setUp(): void
    {
        parent::setUp();
        $this->notifier = new RecordingNotifier;
        $this->app->instance(StaffNotifier::class, $this->notifier);
    }

    public function test_the_first_report_on_a_question_rings_content_staff_once(): void
    {
        $question = Question::factory()->create()->refresh();
        $gate = app(QualityGate::class);
        $gate->report(User::factory()->create(), $question, ReportReason::WrongAnswer, null);
        $gate->report(User::factory()->create(), $question, ReportReason::Typo, null);

        $this->assertSame([[
            'to' => 'manage-content',
            'message' => "New report on question #{$question->id}: wrong answer.",
            'type' => 'warning',
            'url' => "/resources/questions/{$question->id}",
        ]], $this->notifier->sent);
    }

    public function test_the_first_report_on_a_nickname_rings_moderators_once(): void
    {
        $target = User::factory()->create(['nickname' => 'Rude1']);
        $moderation = app(NicknameModeration::class);
        $moderation->report(User::factory()->create(), $target, NicknameReportReason::Rude, null);
        $moderation->report(User::factory()->create(), $target, NicknameReportReason::Rude, null);

        $this->assertCount(1, $this->notifier->sent);
        $this->assertSame('moderate', $this->notifier->sent[0]['to']);
        $this->assertSame('New nickname report: "Rude1" (rude).', $this->notifier->sent[0]['message']);
        $this->assertSame('/resources/nickname-reports', $this->notifier->sent[0]['url']);
    }

    public function test_a_payment_waiting_rings_the_other_payment_staff(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $student = User::factory()->create();
        $request = app(PaymentRequests::class)->create($student, PremiumPlan::Term, 'TX-N1', 900, null, $helper);

        $this->assertSame([[
            'to' => 'manage-premium except #'.$helper->id,
            'message' => "Payment waiting for approval: {$student->student_id}, Term, reference TX-N1.",
            'type' => 'info',
            'url' => "/resources/payment-requests/{$request->id}",
        ]], $this->notifier->sent);
    }

    public function test_a_failed_nightly_backup_rings_super_admins(): void
    {
        Http::fake();
        $this->app->instance(BackupService::class, new class extends BackupService
        {
            public function __construct() {}

            public function run(): string
            {
                throw new BackupFailed('disk full');
            }
        });
        $pending = $this->artisan('guul:backup');
        $this->assertInstanceOf(PendingCommand::class, $pending);
        $pending->assertFailed()->run();

        $this->assertSame([[
            'to' => 'manage-settings',
            'message' => 'Backup failed: disk full',
            'type' => 'error',
            'url' => null,
        ]], $this->notifier->sent);
    }
}
