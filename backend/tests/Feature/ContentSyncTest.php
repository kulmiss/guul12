<?php

namespace Tests\Feature;

use App\Enums\PremiumPlan;
use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\Chapter;
use App\Models\ContentChange;
use App\Models\Flashcard;
use App\Models\Question;
use App\Models\QuestionOption;
use App\Models\Summary;
use App\Models\Unit;
use App\Models\User;
use App\Services\PremiumService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ContentSyncTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = User::factory()->create();
        Sanctum::actingAs($this->student);
    }

    public function test_catalog_lists_only_active_items(): void
    {
        $chapter = Chapter::factory()->create(['title' => 'Electrochemistry']);
        Chapter::factory()->create(['title' => 'Hidden', 'is_active' => false]);

        $response = $this->getJson('/api/v1/catalog')->assertOk();

        $titles = collect((array) $response->json('chapters'))->pluck('title')->all();
        $this->assertContains('Electrochemistry', $titles);
        $this->assertNotContains('Hidden', $titles);
        $this->assertContains($chapter->unit_id, collect((array) $response->json('units'))->pluck('id')->all());
        $this->assertGreaterThan(0, $response->json('cursor'));
    }

    public function test_chapter_payload_contains_only_student_visible_content(): void
    {
        $chapter = Chapter::factory()->create();
        Summary::query()->create(['chapter_id' => $chapter->id, 'key_points' => ['Point'], 'formulas' => [['label' => 'Area', 'latex' => 'A = \\pi r^2']], 'status' => QuestionStatus::Published]);
        Flashcard::query()->create(['chapter_id' => $chapter->id, 'term' => 'Ion', 'definition' => 'A charged particle.', 'status' => QuestionStatus::Published]);
        Flashcard::query()->create(['chapter_id' => $chapter->id, 'term' => 'Draft term', 'definition' => 'x', 'status' => QuestionStatus::Draft]);

        $published = Question::factory()->withOptions()->create(['chapter_id' => $chapter->id]);
        $beta = Question::factory()->withOptions()->status(QuestionStatus::Beta)->create(['chapter_id' => $chapter->id]);
        Question::factory()->status(QuestionStatus::Draft)->create(['chapter_id' => $chapter->id]);
        Question::factory()->status(QuestionStatus::Checked)->create(['chapter_id' => $chapter->id]);

        $response = $this->getJson("/api/v1/chapters/{$chapter->id}")->assertOk();

        $ids = collect((array) $response->json('questions'))->pluck('id')->all();
        $this->assertEqualsCanonicalizing([$published->id, $beta->id], $ids);
        $response->assertJsonPath('summary.formulas.0.latex', 'A = \\pi r^2')
            ->assertJsonCount(1, 'flashcards')
            ->assertJsonPath('questions.0.options.1.key', 'B')
            ->assertJsonPath('questions.0.is_beta', false)
            ->assertJsonPath('questions.1.is_beta', true);
    }

    public function test_inactive_chapter_is_not_found(): void
    {
        $chapter = Chapter::factory()->create(['is_active' => false]);

        $this->getJson("/api/v1/chapters/{$chapter->id}")->assertNotFound();
    }

    public function test_changes_report_only_what_changed_since_the_cursor(): void
    {
        $chapterA = Chapter::factory()->create();
        $chapterB = Chapter::factory()->create();
        $cursor = ContentChange::latestCursor();

        Question::factory()->create(['chapter_id' => $chapterB->id]);

        $response = $this->getJson("/api/v1/sync/changes?since={$cursor}")->assertOk();

        $response->assertJsonPath('chapter_ids', [$chapterB->id])
            ->assertJsonPath('catalog_changed', false)
            ->assertJsonPath('has_more', false);
        $this->assertNotContains($chapterA->id, (array) $response->json('chapter_ids'));

        $next = (int) $response->json('cursor');
        $this->getJson("/api/v1/sync/changes?since={$next}")
            ->assertJsonPath('chapter_ids', [])
            ->assertJsonPath('cursor', $next);
    }

    public function test_catalog_change_is_flagged(): void
    {
        $unit = Unit::factory()->create();
        $cursor = ContentChange::latestCursor();

        $unit->update(['title' => 'Renamed unit']);

        $this->getJson("/api/v1/sync/changes?since={$cursor}")->assertJsonPath('catalog_changed', true);
    }

    public function test_deleting_content_is_synced(): void
    {
        $question = Question::factory()->create();
        $cursor = ContentChange::latestCursor();

        $question->delete();

        $this->getJson("/api/v1/sync/changes?since={$cursor}")->assertJsonPath('chapter_ids', [$question->chapter_id]);
        $this->assertDatabaseHas('content_changes', ['entity' => 'question', 'entity_id' => $question->id, 'action' => 'delete']);
    }

    public function test_changes_are_paged(): void
    {
        $chapter = Chapter::factory()->create();
        $cursor = ContentChange::latestCursor();
        for ($i = 0; $i < 501; $i++) {
            ContentChange::record('question', $i + 1, $chapter->id, ContentChange::ACTION_UPSERT);
        }

        $first = $this->getJson("/api/v1/sync/changes?since={$cursor}")->assertJsonPath('has_more', true);
        $this->getJson('/api/v1/sync/changes?since='.$first->json('cursor'))->assertJsonPath('has_more', false);
    }

    public function test_editing_a_published_question_bumps_its_version(): void
    {
        $question = Question::factory()->withOptions()->create();
        $version = $question->refresh()->version;

        $question->update(['explanation' => 'Better explanation.']);
        $this->assertSame($version + 1, $question->refresh()->version);

        QuestionOption::query()->where('question_id', $question->id)->where('key', 'D')->update(['body' => 'x']);
        // Mass query updates skip model events; edits through models bump the version.
        $option = QuestionOption::query()->where('question_id', $question->id)->where('key', 'C')->firstOrFail();
        $option->update(['body' => '6']);
        $this->assertSame($version + 2, $question->refresh()->version);

        // Changing only stats does not bump the version.
        $question->update(['difficulty' => 4]);
        $this->assertSame($version + 2, Question::query()->findOrFail($question->id)->version);
    }

    public function test_draft_question_options_do_not_bump_version(): void
    {
        $question = Question::factory()->status(QuestionStatus::Draft)->withOptions()->create();

        $this->assertSame(1, $question->refresh()->version);
    }

    public function test_offline_pack_requires_premium(): void
    {
        $chapter = Chapter::factory()->create();
        $unit = $chapter->unit()->firstOrFail();

        $this->getJson("/api/v1/sync/pack?subject_id={$unit->subject_id}&grade=12")
            ->assertForbidden()->assertJsonPath('code', 'premium_required');

        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        app(PremiumService::class)->grant($this->student, PremiumPlan::Term, $helper, 'TX-1');

        $this->getJson("/api/v1/sync/pack?subject_id={$unit->subject_id}&grade=12")
            ->assertOk()->assertJsonCount(1, 'chapters')
            ->assertJsonPath('chapters.0.chapter.id', $chapter->id);
    }
}
