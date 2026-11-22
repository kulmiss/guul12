<?php

namespace Tests\Feature;

use App\Content\ContentFolder;
use App\Content\ContentImporter;
use App\Content\ImportReport;
use App\Enums\QuestionStatus;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Chapter;
use App\Models\ContentChange;
use App\Models\Flashcard;
use App\Models\Question;
use App\Models\QuestionReview;
use App\Models\SolvedExercise;
use App\Models\Unit;
use App\Models\User;
use Database\Seeders\CurriculumSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\ContentFixture;
use Tests\TestCase;

class ContentImportTest extends TestCase
{
    use RefreshDatabase;

    private ContentFixture $fx;

    private const FILE = 'physics/u2-c1.yaml';

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed(CurriculumSeeder::class);
        $this->fx = new ContentFixture;
    }

    protected function tearDown(): void
    {
        $this->fx->delete();
        parent::tearDown();
    }

    private function import(): ImportReport
    {
        $bundle = ContentFolder::validator()->load($this->fx->write());

        return $this->app->make(ContentImporter::class)->import($bundle['curricula'], $bundle['reviews']);
    }

    private function q(string $suffix): Question
    {
        return Question::query()->where('uid', 'g12-physics-2-1-'.$suffix)->firstOrFail();
    }

    public function test_without_a_second_solve_everything_stays_hidden(): void
    {
        $report = $this->import();

        $this->assertSame(1, Unit::query()->count());
        $this->assertSame(2, Chapter::query()->count());
        $this->assertSame(8, Question::query()->count());
        $this->assertSame(8, $report->get('status_draft'));
        $this->assertCount(8, $report->problems);
        $this->assertSame(0, Question::query()->visibleToStudents()->count());
        // Exercises, flashcards and the summary do not need a second solve.
        $this->assertSame(1, SolvedExercise::query()->count());
        $this->assertSame(1, Flashcard::query()->count());
        $chapter = Chapter::query()->where('number', 1)->firstOrFail();
        $this->assertSame('https://elearn.moe.gov.et/storage/G12-Physics.pdf', $chapter->textbook_url);
    }

    public function test_matching_second_solve_makes_questions_beta(): void
    {
        $this->fx->writeReview(self::FILE, skip: ['q08']);
        $report = $this->import();

        $this->assertSame(7, $report->get('status_beta'));
        $this->assertSame(QuestionStatus::Beta, $this->q('q01')->status);
        $this->assertSame(QuestionStatus::Draft, $this->q('q08')->status);
        $this->assertSame(['g12-physics-2-1-q08: no second solve for the current text (stays hidden)'], $report->problems);
        $this->assertSame(7, QuestionReview::query()->where('matches_key', true)->count());
        // Options are stored in order with sides.
        $this->assertSame(['L1', 'L2', 'R1', 'R2'], $this->q('q06')->options->pluck('key')->all());
        $this->assertSame(['TRUE', 'FALSE'], $this->q('q02')->options->pluck('key')->all());
        $this->assertSame(['value' => 3, 'tolerance' => 0, 'unit' => 's'], $this->q('q04')->answer_key);
    }

    public function test_a_disagreeing_solve_blocks_the_question(): void
    {
        $this->fx->writeReview(self::FILE, overrides: ['q01' => 'C', 'q04' => ['value' => 4]]);
        $report = $this->import();

        $this->assertSame(QuestionStatus::Draft, $this->q('q01')->status);
        $this->assertSame(QuestionStatus::Draft, $this->q('q04')->status);
        $this->assertContains('g12-physics-2-1-q01: second solve disagrees with the key (stays hidden)', $report->problems);
        $this->assertFalse(QuestionReview::query()->where('question_id', $this->q('q01')->id)->firstOrFail()->matches_key);
    }

    public function test_importing_again_changes_nothing(): void
    {
        $this->fx->writeReview(self::FILE);
        $this->import();
        $versions = Question::query()->pluck('version', 'uid')->all();
        $changes = ContentChange::query()->count();

        $report = $this->import();

        $this->assertSame(8, $report->get('questions_unchanged'));
        $this->assertSame(0, $report->get('questions_updated'));
        $this->assertSame($versions, Question::query()->pluck('version', 'uid')->all());
        $this->assertSame($changes, ContentChange::query()->count(), 'no sync noise');
    }

    public function test_changed_text_needs_a_new_second_solve(): void
    {
        $this->fx->writeReview(self::FILE);
        $this->import();
        $before = $this->q('q01');

        $this->fx->chapters[self::FILE]['questions'][0]['stem'] = 'Which quantity stays the same for a projectile with no air resistance?';
        $report = $this->import();

        $after = $this->q('q01');
        $this->assertSame($before->version + 1, $after->version);
        $this->assertSame(QuestionStatus::Draft, $after->status, 'old review was for the old text');

        $this->fx->writeReview(self::FILE);
        $this->import();
        $this->assertSame(QuestionStatus::Beta, $this->q('q01')->status);
        $this->assertSame($before->version + 1, $this->q('q01')->version, 'adding a review does not bump the version');
        $this->assertSame(1, $report->get('questions_updated'));
    }

    public function test_option_only_change_bumps_the_version_once(): void
    {
        $this->fx->writeReview(self::FILE);
        $this->import();
        $before = $this->q('q01')->version;

        $this->fx->chapters[self::FILE]['questions'][0]['options']['D'] = 'Height above the ground';
        $this->fx->writeReview(self::FILE);
        $this->import();

        $this->assertSame($before + 1, $this->q('q01')->version);
        $this->assertSame('Height above the ground', $this->q('q01')->options->last()?->body);
    }

    public function test_a_fixed_key_rescores_old_answers_quietly(): void
    {
        $this->fx->writeReview(self::FILE);
        $this->import();
        $q = $this->q('q01');
        $q->status = QuestionStatus::Published;
        $q->save();

        // Two students answered: one chose B (right under the old key), one chose A.
        $attempts = [];
        foreach (['B', 'A'] as $choice) {
            $user = User::factory()->create();
            $attempt = Attempt::query()->create([
                'user_id' => $user->id, 'client_uuid' => (string) Str::uuid(), 'mode' => 'quick10', 'chapter_id' => $q->chapter_id,
                'started_at' => now()->subMinute(), 'finished_at' => now(), 'total' => 1, 'correct' => $choice === 'B' ? 1 : 0,
            ]);
            AttemptAnswer::query()->create([
                'attempt_id' => $attempt->id, 'question_id' => $q->id, 'question_version' => $q->version,
                'response' => $choice, 'is_correct' => $choice === 'B', 'time_ms' => 5000,
            ]);
            $attempts[$choice] = $attempt;
        }

        // The key was wrong: the real answer is A. The reviewer agrees with the new key.
        $this->fx->chapters[self::FILE]['questions'][0]['answer'] = 'A';
        $this->fx->writeReview(self::FILE);
        $report = $this->import();

        $q->refresh();
        $this->assertSame(QuestionStatus::Fixed, $q->status);
        $this->assertSame(2, $report->answersRescored);
        $this->assertSame(0, $attempts['B']->fresh()?->correct);
        $this->assertSame(1, $attempts['A']->fresh()?->correct);
        $this->assertSame(1, $q->times_correct);
        $this->assertDatabaseHas('audit_logs', ['action' => 'question.rescored', 'subject_id' => $q->id]);
    }

    public function test_removed_items_are_retired_or_deleted_and_chapters_hidden(): void
    {
        $this->fx->writeReview(self::FILE);
        $this->import();

        array_pop($this->fx->chapters[self::FILE]['questions']);
        $this->fx->chapters[self::FILE]['flashcards'] = [];
        $this->fx->chapters[self::FILE]['solved_exercises'] = [];
        array_pop($this->fx->curriculum['subjects'][0]['units'][0]['chapters']);
        $report = $this->import();

        $this->assertSame(1, $report->get('questions_retired'));
        $this->assertSame(QuestionStatus::Retired, $this->q('q08')->status);
        $this->assertSame(0, Flashcard::query()->count());
        $this->assertSame(0, SolvedExercise::query()->count());
        $this->assertSame(1, $report->get('chapters_hidden'));
        $this->assertFalse(Chapter::query()->where('number', 2)->firstOrFail()->is_active);
    }

    public function test_import_command_refuses_invalid_content(): void
    {
        $this->fx->chapters[self::FILE]['questions'][0]['answer'] = 'Z';
        $this->command('content:import', ['path' => $this->fx->write()])
            ->expectsOutputToContain('Nothing was imported.')
            ->assertFailed();
        $this->assertSame(0, Question::query()->count());

        $this->fx->chapters[self::FILE]['questions'][0]['answer'] = 'B';
        $this->command('content:import', ['path' => $this->fx->write()])
            ->expectsOutputToContain('Import finished.')
            ->assertSuccessful();
        $this->assertSame(8, Question::query()->count());
    }

    public function test_imported_questions_reach_the_app_payload(): void
    {
        $this->fx->writeReview(self::FILE);
        $this->import();
        $user = User::factory()->create();
        $chapter = Chapter::query()->where('number', 1)->firstOrFail();

        $json = $this->actingAs($user)->getJson("/api/v1/chapters/{$chapter->id}")->assertOk()->json();

        $this->assertCount(8, $json['questions']);
        $this->assertTrue($json['questions'][0]['is_beta']);
        $this->assertSame('Launch day', $json['passages'][0]['title']);
        $this->assertSame(['A projectile has constant horizontal velocity.', 'Vertical motion has acceleration $g$.'], $json['summary']['key_points']);
    }
}
