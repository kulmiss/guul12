<?php

namespace Tests\Nova;

use App\Contracts\StaffNotifier;
use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Jobs\ApplyQuestionChanges;
use App\Models\AuditLog;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\User;
use App\Services\Staff\QuestionChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Question actions in Nova: status moves through the quality gate, CONFIRM for the careful ones,
 * difficulty and chapter, copies, big selections in the background with a bell message, the teacher
 * check export and bulk import (Draft only). Content staff only.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class QuestionActionsNovaTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    private User $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->checker = User::factory()->staff(UserRole::ContentChecker)->create();
    }

    /**
     * @param  list<int>|string  $ids
     * @param  array<string, mixed>  $fields
     * @return TestResponse<Response>
     */
    private function runAction(string $action, array|string $ids, array $fields = [], ?User $as = null, string $query = ''): TestResponse
    {
        return $this->actingAs($as ?? $this->checker)
            ->postJson("/nova-api/questions/action?action={$action}{$query}", ['resources' => $ids, ...$fields]);
    }

    public function test_status_moves_follow_the_quality_gate(): void
    {
        $beta = Question::factory()->create(['status' => QuestionStatus::Beta]);
        $draft = Question::factory()->create(['status' => QuestionStatus::Draft]);

        $this->runAction('publish', [$beta->id, $draft->id])->assertOk()
            ->assertJsonFragment(['message' => '1 changed, 1 skipped (not allowed from its status).']);
        $this->assertSame(QuestionStatus::Published, $beta->refresh()->status);
        $this->assertSame(QuestionStatus::Draft, $draft->refresh()->status);

        // Back to Draft hides questions from students: it needs the word CONFIRM.
        $this->runAction('back-to-draft', [$beta->id])->assertUnprocessable();
        $this->runAction('back-to-draft', [$beta->id], ['confirm' => 'CONFIRM'])->assertOk();
        $this->assertSame(QuestionStatus::Draft, $beta->refresh()->status);

        $checked = Question::factory()->create(['status' => QuestionStatus::Checked]);
        $this->runAction('move-to-beta', [$checked->id])->assertOk();
        $this->assertSame(QuestionStatus::Beta, $checked->refresh()->status);

        // Mark fixed changes students' scores: CONFIRM too.
        $this->runAction('mark-fixed-re-score', [$checked->id])->assertUnprocessable();
        $this->runAction('mark-fixed-re-score', [$checked->id], ['confirm' => 'CONFIRM'])->assertOk();
        $this->assertSame(QuestionStatus::Fixed, $checked->refresh()->status);
    }

    public function test_difficulty_and_chapter(): void
    {
        $q = Question::factory()->create(['difficulty' => 2]);
        $target = Chapter::factory()->create(['title' => 'Cell parts']);

        $this->runAction('change-difficulty', [$q->id], ['difficulty' => '7'])->assertUnprocessable();
        $this->runAction('change-difficulty', [$q->id], ['difficulty' => '5'])->assertOk();
        $this->assertSame(5, $q->refresh()->difficulty);

        $this->runAction('move-to-chapter', [$q->id], ['chapter' => '999999'])->assertUnprocessable();
        $this->runAction('move-to-chapter', [$q->id], ['chapter' => (string) $target->id])->assertOk();
        $this->assertSame($target->id, $q->refresh()->chapter_id);
    }

    public function test_big_selections_run_in_the_background_and_ring_the_bell(): void
    {
        Queue::fake();
        $chapter = Chapter::factory()->create();
        Question::factory()->count(QuestionChanges::BULK + 1)->create(['status' => QuestionStatus::Beta, 'chapter_id' => $chapter->id]);

        $this->runAction('publish', 'all')->assertOk()
            ->assertJsonFragment(['message' => 'Started in the background for 101 questions. You will get a message when it is done.']);
        $this->assertSame(0, Question::query()->where('status', 'published')->count(), 'nothing changed yet');
        Queue::assertPushed(ApplyQuestionChanges::class, fn (ApplyQuestionChanges $job): bool => $job->operation === 'publish'
            && count($job->ids) === 101 && $job->actorId === $this->checker->id);

        // The job does the work and leaves a Nova bell message for the staff member.
        (new ApplyQuestionChanges('publish', array_values(array_map(fn (mixed $id): int => (int) $id, Question::query()->pluck('id')->all())), [], $this->checker->id))
            ->handle(app(QuestionChanges::class), app(StaffNotifier::class));
        $this->assertSame(101, Question::query()->where('status', 'published')->count());
        $note = DB::table('nova_notifications')->where('notifiable_id', $this->checker->id)->first();
        $this->assertNotNull($note);
        $this->assertStringContainsString('Publish finished: 101 changed, 0 skipped.', (string) $note->data);
    }

    public function test_filters_decide_what_all_means(): void
    {
        $chapter = Chapter::factory()->create();
        $hard = Question::factory()->create(['status' => QuestionStatus::Beta, 'difficulty' => 5, 'chapter_id' => $chapter->id]);
        $easy = Question::factory()->create(['status' => QuestionStatus::Beta, 'difficulty' => 1, 'chapter_id' => $chapter->id]);
        $filters = base64_encode((string) json_encode([['question-difficulty' => '5']]));

        $this->runAction('publish', 'all', [], null, "&filters={$filters}")->assertOk();
        $this->assertSame(QuestionStatus::Published, $hard->refresh()->status);
        $this->assertSame(QuestionStatus::Beta, $easy->refresh()->status);
    }

    public function test_duplicate_opens_the_copy(): void
    {
        $q = Question::factory()->withOptions()->create(['status' => QuestionStatus::Published]);
        $response = $this->runAction('duplicate', [$q->id])->assertOk();
        $copy = Question::query()->where('id', '!=', $q->id)->firstOrFail();
        $this->assertSame(QuestionStatus::Draft, $copy->status);
        $this->assertSame("/resources/questions/{$copy->id}", $response->json('visit.path'));
    }

    public function test_teacher_check_export_has_the_answers_and_is_logged(): void
    {
        $q = Question::factory()->withOptions()->create(['stem' => '=2+2 is?', 'explanation' => 'Because 2 + 2 = 4.']);
        $response = $this->actingAs($this->checker)->post('/nova-api/questions/action?action=export-for-teacher-check', ['resources' => (string) $q->id]);
        $response->assertOk();
        $rows = array_map(fn (string $l): array => str_getcsv($l, ',', '"', ''),
            array_values(array_filter(explode("\n", str_replace("\r", '', substr($response->streamedContent(), 3))))));
        $this->assertSame(['ID', 'Chapter', 'Type', 'Difficulty', 'Status', 'Question', 'Options', 'Answer', 'Explanation'], $rows[0]);
        $this->assertSame("'=2+2 is?", $rows[1][5]);
        $this->assertSame('A) 3 | B) 4 | C) 5 | D) 22', $rows[1][6]);
        $this->assertSame('B', $rows[1][7]);
        $this->assertSame('Because 2 + 2 = 4.', $rows[1][8]);
        $this->assertSame(1, AuditLog::query()->where('action', 'export.questions')->count());
    }

    public function test_bulk_import_from_a_file(): void
    {
        $chapter = Chapter::factory()->create();
        $yaml = "- type: true_false\n  difficulty: 1\n  stem: 'The Sun is a star.'\n  answer: true\n  explanation: 'The Sun is the star at the centre of our solar system.'\n";
        $file = UploadedFile::fake()->createWithContent('questions.yaml', $yaml);

        $this->actingAs($this->checker)->post('/nova-api/questions/action?action=bulk-import', [
            'resources' => '', 'chapter' => (string) $chapter->id, 'file' => $file,
        ])->assertOk()->assertJsonFragment(['message' => '1 question added as Draft. It needs the second solve before students see it.']);
        $this->assertSame(QuestionStatus::Draft, Question::query()->firstOrFail()->status);

        $bad = UploadedFile::fake()->createWithContent('bad.yaml', "- type: nonsense\n");
        $danger = $this->actingAs($this->checker)->post('/nova-api/questions/action?action=bulk-import', [
            'resources' => '', 'chapter' => (string) $chapter->id, 'file' => $bad,
        ])->assertOk()->json('danger');
        $this->assertStringStartsWith("Nothing was added. question 1: unknown type 'nonsense'", (string) $danger);
        $this->assertSame(1, Question::query()->count());
    }

    public function test_question_actions_are_for_content_staff_only(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $q = Question::factory()->create(['status' => QuestionStatus::Beta]);
        foreach (['publish', 'move-to-beta', 'back-to-draft', 'mark-fixed-re-score', 'change-difficulty', 'move-to-chapter', 'duplicate', 'export-for-teacher-check'] as $action) {
            $this->assertContains($this->runAction($action, [$q->id], ['confirm' => 'CONFIRM', 'difficulty' => '3'], $helper)->status(), [403, 404], $action);
        }
        $this->assertSame(QuestionStatus::Beta, $q->refresh()->status);
        $this->assertSame(1, Question::query()->count());
    }
}
