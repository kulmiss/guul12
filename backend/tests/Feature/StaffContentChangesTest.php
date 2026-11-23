<?php

namespace Tests\Feature;

use App\Enums\Access;
use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Chapter;
use App\Models\ContentChange;
use App\Models\Mock;
use App\Models\Question;
use App\Models\Subject;
use App\Models\User;
use App\Services\Staff\ContentChanges;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * Staff changes to mocks and chapters: unpublish/publish a mock (the content import does not undo
 * it), check a mock, the student preview page, chapter video access and re-sending a chapter.
 */
class StaffContentChangesTest extends TestCase
{
    use RefreshDatabase;

    private ContentChanges $changes;

    private User $checker;

    protected function setUp(): void
    {
        parent::setUp();
        $this->changes = app(ContentChanges::class);
        $this->checker = User::factory()->staff(UserRole::ContentChecker)->create();
    }

    private function makeMock(int $visible = 2, int $hidden = 0): Mock
    {
        $mock = Mock::query()->create(['uid' => 'g12-bio-mock-1', 'subject_id' => Subject::factory()->create()->id, 'grade' => 12,
            'number' => 1, 'title' => 'Biology mock 1', 'minutes' => 60, 'access' => Access::Free]);
        $sort = 0;
        foreach ([...array_fill(0, $visible, QuestionStatus::Published), ...array_fill(0, $hidden, QuestionStatus::Draft)] as $status) {
            $q = Question::factory()->withOptions()->create(['status' => $status, 'stem' => 'Mock question '.(++$sort).' about $x^2$?']);
            DB::table('mock_questions')->insert(['mock_id' => $mock->id, 'question_id' => $q->id, 'sort' => $sort]);
        }

        return $mock;
    }

    public function test_unpublished_mocks_disappear_for_students_and_come_back(): void
    {
        $mock = $this->makeMock();
        $student = User::factory()->create();
        $token = $student->createToken('app')->plainTextToken;

        $this->assertTrue($this->changes->unpublishMock($mock, $this->checker));
        $this->assertFalse($this->changes->unpublishMock($mock, $this->checker), 'already hidden');
        $this->withToken($token)->getJson('/api/v1/mocks')->assertOk()->assertJsonCount(0, 'mocks');
        $this->withToken($token)->getJson("/api/v1/mocks/{$mock->id}")->assertNotFound();
        $log = AuditLog::query()->where('action', 'mock.published')->firstOrFail();
        $this->assertSame(['old' => ['shown' => true], 'new' => ['shown' => false]], $log->data);

        // The content import switches mocks on, but never undoes a staff "unpublish".
        $mock->forceFill(['is_active' => true])->save();
        $this->withToken($token)->getJson('/api/v1/mocks')->assertJsonCount(0, 'mocks');

        $this->assertTrue($this->changes->publishMock($mock, $this->checker));
        $this->withToken($token)->getJson('/api/v1/mocks')->assertJsonCount(1, 'mocks');
    }

    public function test_check_mock_lists_hidden_questions(): void
    {
        $this->assertSame('All 3 questions are shown to students.', $this->changes->checkMock($this->makeMock(3)));
        Mock::query()->delete();
        $text = $this->changes->checkMock($this->makeMock(2, 1));
        $draft = Question::query()->where('status', QuestionStatus::Draft->value)->value('id');
        $this->assertSame("2 of 3 questions are shown to students. Hidden: #{$draft} (draft). The app gets changes by itself.", $text);
    }

    public function test_student_preview_shows_questions_without_answers_for_content_staff_only(): void
    {
        $mock = $this->makeMock(2);
        $url = "/staff/preview/mocks/{$mock->id}";

        $this->get($url)->assertForbidden();
        $this->actingAs(User::factory()->create())->get($url)->assertForbidden();
        $this->actingAs(User::factory()->staff(UserRole::PaymentsHelper)->create())->get($url)->assertForbidden();

        $page = $this->actingAs($this->checker)->get($url)->assertOk();
        $page->assertSee('Biology mock 1')->assertSee('60 minutes')->assertSee('Mock question 1 about');
        $page->assertSee('<span class="guul-tex" data-display="0">x^2</span>', false);
        $page->assertSee('/katex/katex.min.js', false);
        $page->assertDontSee('Right answer')->assertDontSee('Explanation');
        $page->assertHeader('X-Robots-Tag', 'noindex');
    }

    public function test_chapter_video_access_and_resend(): void
    {
        $chapter = Chapter::factory()->create(['video_youtube_id' => 'abc123', 'video_access' => 'free']);
        $noVideo = Chapter::factory()->create();

        $before = ContentChange::latestCursor();
        $this->assertTrue($this->changes->setVideoAccess($chapter, Access::Premium, $this->checker));
        $this->assertFalse($this->changes->setVideoAccess($chapter, Access::Premium, $this->checker), 'no change');
        $this->assertFalse($this->changes->setVideoAccess($noVideo, Access::Premium, $this->checker), 'no video');
        $this->assertSame('premium', $chapter->refresh()->video_access->value);
        $this->assertSame(['old' => ['video_access' => 'free'], 'new' => ['video_access' => 'premium']],
            AuditLog::query()->where('action', 'chapter.video_access')->firstOrFail()->data);
        $this->assertTrue(ContentChange::query()->where('id', '>', $before)->where('chapter_id', $chapter->id)->exists());

        $cursor = ContentChange::latestCursor();
        $this->changes->resendChapter($chapter, $this->checker);
        $this->assertSame([$chapter->id], ContentChange::query()->where('id', '>', $cursor)->pluck('chapter_id')->all());
        $this->assertSame(1, AuditLog::query()->where('action', 'chapter.resent')->count());
    }
}
