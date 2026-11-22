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
use Illuminate\Support\Facades\Storage;
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

    public function test_summary_pdfs_are_published_under_a_hashed_name_and_kept_in_step(): void
    {
        Storage::fake('public');
        $this->fx->writeReview(self::FILE);
        $this->fx->writePdf(self::FILE, '%PDF-1.7 first');
        $report = $this->import();

        $first = 'summaries/g12-physics-2-1-'.substr(hash('sha256', '%PDF-1.7 first'), 0, 12).'.pdf';
        $chapter = Chapter::query()->where('number', 1)->firstOrFail();
        $this->assertSame($first, $chapter->summary_pdf_path);
        $this->assertSame(1, $report->get('summary_pdfs_published'));
        Storage::disk('public')->assertExists($first);
        $this->assertSame('%PDF-1.7 first', Storage::disk('public')->get($first));
        // The planned chapter (no file) has no PDF.
        $this->assertNull(Chapter::query()->where('number', 2)->firstOrFail()->summary_pdf_path);

        // Same PDF again: nothing happens, no sync noise.
        $changes = ContentChange::query()->count();
        $report = $this->import();
        $this->assertSame(0, $report->get('summary_pdfs_published'));
        $this->assertSame($changes, ContentChange::query()->count());

        // A rebuilt PDF gets a new URL; the old file is removed and the app is told.
        $chapterChanges = fn (): int => ContentChange::query()->where('entity', 'chapter')->where('entity_id', $chapter->id)->count();
        $before = $chapterChanges();
        $this->fx->writePdf(self::FILE, '%PDF-1.7 second');
        $this->import();
        $second = 'summaries/g12-physics-2-1-'.substr(hash('sha256', '%PDF-1.7 second'), 0, 12).'.pdf';
        $this->assertSame($second, $chapter->refresh()->summary_pdf_path);
        Storage::disk('public')->assertExists($second);
        Storage::disk('public')->assertMissing($first);
        $this->assertSame($before + 1, $chapterChanges());

        // PDF deleted from the content folder: the chapter loses it too.
        $this->fx->writePdf(self::FILE, null);
        $report = $this->import();
        $this->assertNull($chapter->refresh()->summary_pdf_path);
        $this->assertSame(1, $report->get('summary_pdfs_removed'));
        Storage::disk('public')->assertMissing($second);
    }

    public function test_a_lost_pdf_file_is_copied_again(): void
    {
        Storage::fake('public');
        $this->fx->writePdf(self::FILE, '%PDF-1.7 x');
        $this->import();
        $path = Chapter::query()->where('number', 1)->firstOrFail()->summary_pdf_path;
        $this->assertNotNull($path);
        Storage::disk('public')->delete($path);

        $this->import();

        Storage::disk('public')->assertExists($path);
    }

    public function test_the_summary_pdf_url_reaches_the_app(): void
    {
        Storage::fake('public');
        $this->fx->writeReview(self::FILE);
        $this->fx->writePdf(self::FILE, '%PDF-1.7 app');
        $this->import();
        $user = User::factory()->create();
        $chapter = Chapter::query()->where('number', 1)->firstOrFail();
        $other = Chapter::query()->where('number', 2)->firstOrFail();

        $json = $this->actingAs($user)->getJson("/api/v1/chapters/{$chapter->id}")->assertOk()->json();
        $this->assertTrue($json['chapter']['has_summary_pdf']);
        $this->assertSame(Storage::disk('public')->url((string) $chapter->summary_pdf_path), $json['chapter']['summary_pdf_url']);

        $catalog = $this->actingAs($user)->getJson('/api/v1/catalog')->assertOk()->json();
        $headers = array_column($catalog['chapters'], null, 'id');
        $this->assertSame($json['chapter']['summary_pdf_url'], $headers[$chapter->id]['summary_pdf_url']);
        $this->assertNull($headers[$other->id]['summary_pdf_url']);
        $this->assertFalse($headers[$other->id]['has_summary_pdf']);
    }
}
