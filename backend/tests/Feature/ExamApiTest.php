<?php

namespace Tests\Feature;

use App\Enums\Access;
use App\Enums\PremiumPlan;
use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\Chapter;
use App\Models\Mock;
use App\Models\Passage;
use App\Models\Question;
use App\Models\User;
use App\Services\Leaderboard;
use App\Services\PremiumService;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExamApiTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Chapter $chapterA;

    private Chapter $chapterB;

    /** @var list<Question> */
    private array $questions;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = User::factory()->create();
        Sanctum::actingAs($this->student);
        $this->chapterA = Chapter::factory()->create();
        $this->chapterB = Chapter::factory()->create(['unit_id' => $this->chapterA->unit_id, 'number' => 2]);
        $this->questions = [
            Question::factory()->withOptions()->create(['chapter_id' => $this->chapterA->id]),
            Question::factory()->withOptions()->create(['chapter_id' => $this->chapterA->id]),
            Question::factory()->withOptions()->create(['chapter_id' => $this->chapterB->id]),
        ];
    }

    /**
     * @param  list<Question>  $questions
     */
    private function makeMock(int $number, Access $access, array $questions, bool $active = true): Mock
    {
        $mock = Mock::query()->create([
            'uid' => "g12-mock-physics-{$number}",
            'subject_id' => $this->chapterA->unit->subject_id,
            'grade' => 12,
            'number' => $number,
            'title' => "Mock {$number}",
            'minutes' => 75,
            'access' => $access,
            'is_active' => $active,
        ]);
        foreach ($questions as $i => $q) {
            $mock->questions()->attach($q->id, ['sort' => $i + 1]);
        }

        return $mock;
    }

    private function makePremium(User $user): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        app(PremiumService::class)->grant($user, PremiumPlan::Term, $helper, 'TX-'.Str::random(6));
    }

    /**
     * @param  array<int, string|null>  $responses  question index => response
     * @return array<string, mixed>
     */
    private function exam(string $mode, array $responses, ?Mock $mock = null, ?string $uuid = null): array
    {
        $answers = [];
        foreach ($responses as $i => $response) {
            $answers[] = ['question_id' => $this->questions[$i]->id, 'version' => 1, 'response' => $response, 'time_ms' => 20000];
        }

        return array_filter([
            'client_uuid' => $uuid ?? (string) Str::uuid(),
            'mode' => $mode,
            'mock_id' => $mock?->id,
            'started_at' => now()->subHour()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'answers' => $answers,
        ], fn ($v) => $v !== null);
    }

    public function test_the_mock_list_shows_free_and_locked_mocks_with_a_content_hash(): void
    {
        $free = $this->makeMock(1, Access::Free, $this->questions);
        $premium = $this->makeMock(2, Access::Premium, [$this->questions[2]]);
        $this->makeMock(3, Access::Free, $this->questions, active: false);
        $hidden = Question::factory()->withOptions()->create(['chapter_id' => $this->chapterA->id, 'status' => QuestionStatus::Draft]);
        $free->questions()->attach($hidden->id, ['sort' => 9]);

        $json = $this->getJson('/api/v1/mocks')->assertOk()->json('mocks');

        $this->assertSame([$free->id, $premium->id], array_column($json, 'id'));
        $this->assertSame([3, false, 'free', 75], [$json[0]['question_count'], $json[0]['locked'], $json[0]['access'], $json[0]['minutes']]);
        $this->assertSame([1, true, 'premium'], [$json[1]['question_count'], $json[1]['locked'], $json[1]['access']]);

        // A changed order changes the hash; the same content keeps it.
        $hash = $json[0]['content_hash'];
        $this->assertSame($hash, $this->getJson('/api/v1/mocks')->json('mocks.0.content_hash'));
        $free->questions()->updateExistingPivot($this->questions[0]->id, ['sort' => 5]);
        $this->assertNotSame($hash, $this->getJson('/api/v1/mocks')->json('mocks.0.content_hash'));

        $this->makePremium($this->student);
        $this->assertFalse($this->getJson('/api/v1/mocks')->json('mocks.1.locked'));
    }

    public function test_a_mock_downloads_in_order_and_premium_mocks_are_refused_to_free_students(): void
    {
        $passage = Passage::query()->create(['uid' => 'g12-physics-1-1-ps01', 'title' => 'Read', 'body' => 'Text']);
        $this->questions[1]->forceFill(['passage_id' => $passage->id])->save();
        $free = $this->makeMock(1, Access::Free, [$this->questions[2], $this->questions[0], $this->questions[1]]);
        $premium = $this->makeMock(2, Access::Premium, [$this->questions[0]]);
        $gone = $this->makeMock(3, Access::Free, [$this->questions[0]], active: false);

        $json = $this->getJson("/api/v1/mocks/{$free->id}")->assertOk()->json();
        $this->assertSame([$this->questions[2]->id, $this->questions[0]->id, $this->questions[1]->id], array_column($json['questions'], 'id'));
        $this->assertSame(['B'], array_unique(array_column(array_column($json['questions'], 'answer_key'), 'correct')));
        $this->assertSame([['id' => $passage->id, 'title' => 'Read', 'body' => 'Text']], $json['passages']);
        $this->assertSame('Mock 1', $json['mock']['title']);

        $this->getJson("/api/v1/mocks/{$premium->id}")->assertForbidden()->assertJsonPath('code', 'premium_required');
        $this->getJson("/api/v1/mocks/{$gone->id}")->assertNotFound();

        $this->makePremium($this->student);
        $this->getJson("/api/v1/mocks/{$premium->id}")->assertOk()->assertJsonCount(1, 'questions');
    }

    public function test_an_exam_result_has_right_answers_per_chapter(): void
    {
        $mock = $this->makeMock(1, Access::Free, $this->questions);

        $json = $this->postJson('/api/v1/attempts', $this->exam('exam_fixed', ['B', 'A', 'B'], $mock))->assertCreated()->json('attempt');

        $this->assertSame(['exam_fixed', $mock->id, 3, 2], [$json['mode'], $json['mock_id'], $json['total'], $json['correct']]);
        $this->assertSame([
            ['chapter_id' => $this->chapterA->id, 'total' => 2, 'correct' => 1],
            ['chapter_id' => $this->chapterB->id, 'total' => 1, 'correct' => 1],
        ], $json['exam']['chapters']);
        $this->assertNull($json['exam']['better_than_percent']);
        $this->assertSame(0, $json['exam']['students']);
        $this->assertSame($mock->id, Attempt::query()->firstOrFail()->mock_id);

        // Random exams have chapters but no comparison; practice has no exam block.
        $random = $this->postJson('/api/v1/attempts', $this->exam('exam_random', ['B']))->assertCreated()->json('attempt.exam');
        $this->assertSame([null, null], [$random['better_than_percent'], $random['students']]);
        $this->assertNull($this->postJson('/api/v1/attempts', $this->exam('quick10', ['B']))->assertCreated()->json('attempt.exam'));
    }

    public function test_the_mock_id_is_needed_for_a_fixed_mock_and_only_there(): void
    {
        $mock = $this->makeMock(1, Access::Free, $this->questions);

        $this->postJson('/api/v1/attempts', $this->exam('exam_fixed', ['B']))->assertUnprocessable()->assertJsonValidationErrors('mock_id');
        $this->postJson('/api/v1/attempts', $this->exam('exam_random', ['B'], $mock))->assertUnprocessable()->assertJsonValidationErrors('mock_id');
        $this->postJson('/api/v1/attempts', [...$this->exam('exam_fixed', ['B']), 'mock_id' => 999999])->assertUnprocessable()->assertJsonValidationErrors('mock_id');
        $this->postJson('/api/v1/attempts', $this->exam('exam_weird', ['B']))->assertUnprocessable()->assertJsonValidationErrors('mode');
    }

    public function test_better_than_percent_uses_each_other_students_first_try(): void
    {
        app(Settings::class)->set('percentile_min_students', 4);
        $mock = $this->makeMock(1, Access::Free, $this->questions);
        $other = $this->makeMock(2, Access::Free, $this->questions);

        // Four other students: first tries score 0/3, 1/3, 2/3, 3/3. Later tries and other mocks do not count.
        foreach ([[null, null, null], ['B', null, null], ['B', 'B', null], ['B', 'B', 'B']] as $responses) {
            Sanctum::actingAs(User::factory()->create());
            $this->postJson('/api/v1/attempts', $this->exam('exam_fixed', $responses, $mock))->assertCreated();
            $this->postJson('/api/v1/attempts', $this->exam('exam_fixed', ['B', 'B', 'B'], $mock))->assertCreated();
            $this->postJson('/api/v1/attempts', $this->exam('exam_fixed', ['A', 'A', 'A'], $other))->assertCreated();
        }

        Sanctum::actingAs($this->student);
        // My own earlier try is not a rival either.
        $this->postJson('/api/v1/attempts', $this->exam('exam_fixed', [null, null, null], $mock))->assertCreated();
        $uuid = (string) Str::uuid();
        $json = $this->postJson('/api/v1/attempts', $this->exam('exam_fixed', ['B', 'B', null], $mock, $uuid))->assertCreated()->json('attempt.exam');

        // 2/3: better than 0/3 and 1/3 (a tie with 2/3 is not "better"): 2 of 4 = 50%.
        $this->assertSame([50, 4], [$json['better_than_percent'], $json['students']]);

        // Fewer students than the minimum: no comparison yet.
        app(Settings::class)->set('percentile_min_students', 5);
        $later = $this->getJson("/api/v1/attempts/{$uuid}")->assertOk()->json('attempt.exam');
        $this->assertSame([null, 4], [$later['better_than_percent'], $later['students']]);
    }

    public function test_a_fixed_mock_counts_only_its_own_questions_and_missing_ones_are_wrong(): void
    {
        $mock = $this->makeMock(1, Access::Free, [$this->questions[0], $this->questions[1]]);

        // A fake phone sends one right answer from the mock and one right answer from elsewhere.
        $json = $this->postJson('/api/v1/attempts', $this->exam('exam_fixed', [0 => 'B', 2 => 'B'], $mock))->assertCreated()->json('attempt');

        $this->assertSame([2, 1], [$json['total'], $json['correct']], 'the mock has 2 questions; the unanswered one is wrong');
        $this->assertSame([['chapter_id' => $this->chapterA->id, 'total' => 1, 'correct' => 1]], $json['exam']['chapters']);
    }

    public function test_a_half_sent_mock_cannot_beat_students_who_sat_the_whole_mock(): void
    {
        app(Settings::class)->set('percentile_min_students', 2);
        $mock = $this->makeMock(1, Access::Free, $this->questions);
        foreach ([['B', 'B', null], ['B', null, null]] as $responses) {
            Sanctum::actingAs(User::factory()->create());
            $this->postJson('/api/v1/attempts', $this->exam('exam_fixed', $responses, $mock))->assertCreated();
        }

        // Only one (right) answer sent: 1 of 3, not 1 of 1.
        Sanctum::actingAs($this->student);
        $json = $this->postJson('/api/v1/attempts', $this->exam('exam_fixed', [0 => 'B'], $mock))->assertCreated()->json('attempt');
        $this->assertSame([3, 1], [$json['total'], $json['correct']]);
        $this->assertSame(0, $json['exam']['better_than_percent'], 'ties with 1/3 and loses to 2/3');
    }

    public function test_a_fixed_mock_far_over_its_time_gets_no_exam_bonus_and_is_no_rival(): void
    {
        app(Settings::class)->set('percentile_min_students', 1);
        $more = Question::factory()->count(7)->withOptions()->create(['chapter_id' => $this->chapterA->id])->all();
        $this->questions = [...$this->questions, ...$more];
        $mock = $this->makeMock(1, Access::Free, $this->questions); // 10 questions, 75 minutes
        $allRight = array_fill(0, 10, 'B');

        // A fake phone claims 3 hours for a 75-minute mock, all right.
        $cheater = User::factory()->create();
        Sanctum::actingAs($cheater);
        $this->postJson('/api/v1/attempts', [...$this->exam('exam_fixed', $allRight, $mock),
            'started_at' => now()->subHours(3)->toIso8601String()])->assertCreated();
        $this->assertSame(10 * Leaderboard::XP_CORRECT, app(Leaderboard::class)->xpOf($cheater, now()), 'answers count, the 50 exam points do not');

        // In time (75 minutes + 5 minutes for a slow phone is fine): the exam points count.
        $honest = User::factory()->create();
        Sanctum::actingAs($honest);
        $json = $this->postJson('/api/v1/attempts', [...$this->exam('exam_fixed', $allRight, $mock),
            'started_at' => now()->subMinutes(79)->toIso8601String()])->assertCreated()->json('attempt.exam');
        $this->assertSame(10 * Leaderboard::XP_CORRECT + Leaderboard::XP_EXAM, app(Leaderboard::class)->xpOf($honest, now()));
        // The over-time mock is not a rival: no one to compare with yet.
        $this->assertSame([null, 0], [$json['better_than_percent'], $json['students']]);
    }

    public function test_the_exam_bonus_needs_at_least_ten_answers(): void
    {
        $more = Question::factory()->count(7)->withOptions()->create(['chapter_id' => $this->chapterA->id])->all();
        $this->questions = [...$this->questions, ...$more];

        // A one-question "exam" sent again and again used to give 50 points each time.
        for ($i = 0; $i < 3; $i++) {
            $this->postJson('/api/v1/attempts', $this->exam('exam_mistakes', [0 => 'B']))->assertCreated();
        }
        $this->assertSame(3 * Leaderboard::XP_CORRECT, app(Leaderboard::class)->xpOf($this->student, now()));

        $this->postJson('/api/v1/attempts', $this->exam('exam_random', array_fill(0, 10, 'B')))->assertCreated();
        $this->assertSame(13 * Leaderboard::XP_CORRECT + Leaderboard::XP_EXAM, app(Leaderboard::class)->xpOf($this->student, now()));
    }

    public function test_an_attempt_can_be_read_again_only_by_its_owner(): void
    {
        $uuid = (string) Str::uuid();
        $this->postJson('/api/v1/attempts', $this->exam('quick10', ['B'], uuid: $uuid))->assertCreated();

        $this->getJson("/api/v1/attempts/{$uuid}")->assertOk()->assertJsonPath('attempt.correct', 1);
        $this->getJson('/api/v1/attempts/'.Str::uuid())->assertNotFound();
        $this->getJson('/api/v1/attempts/not-a-uuid')->assertNotFound();

        Sanctum::actingAs(User::factory()->create());
        $this->getJson("/api/v1/attempts/{$uuid}")->assertNotFound();
    }

    public function test_history_returns_only_my_answers_page_by_page(): void
    {
        $this->postJson('/api/v1/attempts', $this->exam('quick10', ['B', 'A']))->assertCreated();
        $this->postJson('/api/v1/attempts', $this->exam('quick10', [2 => 'B']))->assertCreated();
        Sanctum::actingAs(User::factory()->create());
        $this->postJson('/api/v1/attempts', $this->exam('quick10', ['B']))->assertCreated();
        Sanctum::actingAs($this->student);

        $json = $this->getJson('/api/v1/history')->assertOk()->json();

        $this->assertFalse($json['has_more']);
        $this->assertSame(
            [[$this->questions[0]->id, $this->chapterA->id, true], [$this->questions[1]->id, $this->chapterA->id, false], [$this->questions[2]->id, $this->chapterB->id, true]],
            array_map(fn (array $a) => [$a['question_id'], $a['chapter_id'], $a['correct']], $json['answers']),
        );
        $this->assertMatchesRegularExpression('/^\d{4}-\d\d-\d\dT\d\d:\d\d:\d\dZ$/', $json['answers'][0]['answered_at']);

        $after = $json['answers'][1]['id'];
        $next = $this->getJson("/api/v1/history?after={$after}")->assertOk()->json();
        $this->assertSame([$this->questions[2]->id], array_column($next['answers'], 'question_id'));
        $this->assertSame($next['answers'][0]['id'], $next['next_after']);

        $end = $this->getJson("/api/v1/history?after={$next['next_after']}")->assertOk()->json();
        $this->assertSame([[], $next['next_after'], false], [$end['answers'], $end['next_after'], $end['has_more']]);
        $this->getJson('/api/v1/history?after=-1')->assertUnprocessable();
    }

    public function test_free_random_exams_per_day_is_sent_to_the_app(): void
    {
        $this->getJson('/api/v1/config')->assertOk()
            ->assertJsonPath('settings.free_random_exams_per_day', 1)
            ->assertJsonPath('settings.rewarded_random_exams_per_day', 2)
            ->assertJsonMissingPath('settings.percentile_min_students')
            ->assertJsonMissingPath('settings.referral_premium_days')
            ->assertJsonMissingPath('settings.free_fixed_mocks');
    }
}
