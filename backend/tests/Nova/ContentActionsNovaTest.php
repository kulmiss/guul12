<?php

namespace Tests\Nova;

use App\Enums\Access;
use App\Enums\UserRole;
use App\Models\Chapter;
use App\Models\Mock;
use App\Models\NicknameReport;
use App\Models\Question;
use App\Models\QuestionReport;
use App\Models\Subject;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Symfony\Component\HttpFoundation\Response;
use Tests\TestCase;

/**
 * Report, chapter and mock actions in Nova: open the reported item, chapter video Free/Premium and
 * re-send, mock unpublish (CONFIRM) / publish / check / preview as student. Roles respected.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class ContentActionsNovaTest extends TestCase
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
     * @param  array<string, mixed>  $fields
     * @return TestResponse<Response>
     */
    private function runAction(string $uri, string $action, int $id, array $fields = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->checker)->postJson("/nova-api/{$uri}/action?action={$action}", ['resources' => [$id], ...$fields]);
    }

    private function makeMock(): Mock
    {
        return Mock::query()->create(['uid' => 'g12-m-1', 'subject_id' => Subject::factory()->create()->id, 'grade' => 12,
            'number' => 1, 'title' => 'Mock 1', 'minutes' => 60, 'access' => Access::Free]);
    }

    public function test_reports_open_the_reported_item(): void
    {
        $question = Question::factory()->create();
        $student = User::factory()->create(['nickname' => 'Rude1']);
        $report = QuestionReport::query()->create(['question_id' => $question->id, 'user_id' => $student->id, 'question_version' => 1, 'reason' => 'typo']);
        $this->assertSame("/resources/questions/{$question->id}",
            $this->runAction('question-reports', 'open-question', $report->id)->assertOk()->json('visit.path'));

        // The nickname's owner is a student: only staff who may see students get "Open student".
        $nick = NicknameReport::query()->create(['reporter_id' => User::factory()->create()->id, 'reported_user_id' => $student->id, 'nickname' => 'Rude1', 'reason' => 'rude']);
        $admin = User::factory()->staff(UserRole::SuperAdmin)->create();
        $this->assertSame("/resources/students/{$student->id}",
            $this->runAction('nickname-reports', 'open-student', $nick->id, [], $admin)->assertOk()->json('visit.path'));
        $this->assertContains($this->runAction('nickname-reports', 'open-student', $nick->id)->status(), [403, 404]);
    }

    public function test_chapter_video_and_rebuild(): void
    {
        $chapter = Chapter::factory()->create(['video_youtube_id' => 'abc123', 'video_access' => 'free']);

        $this->runAction('chapters', 'video-premium', $chapter->id)->assertOk()->assertJsonFragment(['message' => 'Video is Premium now for 1 chapter.']);
        $this->assertSame('premium', $chapter->refresh()->video_access->value);
        $this->runAction('chapters', 'video-free', $chapter->id)->assertOk();
        $this->assertSame('free', $chapter->refresh()->video_access->value);

        $this->runAction('chapters', 'rebuild-offline-pack', $chapter->id)->assertOk()
            ->assertJsonFragment(['message' => 'Phones download 1 chapter again at their next sync.']);
    }

    public function test_mock_unpublish_publish_check_and_preview(): void
    {
        $mock = $this->makeMock();

        $this->runAction('mocks', 'unpublish', $mock->id)->assertUnprocessable();
        $this->runAction('mocks', 'unpublish', $mock->id, ['confirm' => 'CONFIRM'])->assertOk();
        $this->assertNotNull($mock->refresh()->unpublished_at);
        $this->runAction('mocks', 'publish', $mock->id)->assertOk();
        $this->assertNull($mock->refresh()->unpublished_at);

        $this->runAction('mocks', 'check-mock', $mock->id)->assertOk()->assertJsonFragment(['message' => 'All 0 questions are shown to students.']);
        $preview = $this->runAction('mocks', 'preview-as-student', $mock->id)->assertOk()->json('redirect');
        $this->assertSame(['url' => route('staff.preview.mock', $mock), 'openInNewTab' => true], $preview);

        $fields = $this->actingAs($this->checker)->getJson("/nova-api/mocks/{$mock->id}")->json('resource.fields');
        $this->assertIsArray($fields);
        $shown = array_values(array_filter($fields, fn (mixed $f): bool => is_array($f) && $f['name'] === 'Shown to students'));
        $this->assertSame('Shown', $shown[0]['label'] ?? null);
    }

    public function test_these_actions_are_for_content_staff_only(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $chapter = Chapter::factory()->create(['video_youtube_id' => 'abc123']);
        $mock = $this->makeMock();
        foreach ([['chapters', 'video-premium', $chapter->id], ['chapters', 'rebuild-offline-pack', $chapter->id],
            ['mocks', 'unpublish', $mock->id], ['mocks', 'preview-as-student', $mock->id]] as [$uri, $action, $id]) {
            $this->assertContains($this->runAction($uri, $action, $id, ['confirm' => 'CONFIRM'], $helper)->status(), [403, 404], $action);
        }
        $this->assertNull($mock->refresh()->unpublished_at);
        $this->assertSame('free', $chapter->refresh()->video_access->value);
    }
}
