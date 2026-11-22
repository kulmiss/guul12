<?php

namespace Tests\Feature;

use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Models\Chapter;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Subject;
use App\Models\Unit;
use App\Models\User;
use App\Services\DailyChallengeService;
use App\Services\FeatureSwitches;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class DailyChallengeTest extends TestCase
{
    use RefreshDatabase;

    private Chapter $maths;

    private Chapter $physics;

    private Chapter $history;

    protected function setUp(): void
    {
        parent::setUp();
        // 25 Nov 2026, 10:00 in Addis Ababa.
        Carbon::setTestNow(Carbon::parse('2026-11-25 10:00', DailyChallengeService::TIMEZONE));
        $this->maths = $this->chapter(null, 12, null);
        $this->physics = $this->chapter('natural', 12, 'natural');
        $this->history = $this->chapter('social', 12, 'social');
        foreach ([$this->maths, $this->physics, $this->history] as $c) {
            Question::factory()->count(8)->withOptions()->create(['chapter_id' => $c->id]);
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function chapter(?string $subjectStream, int $grade, ?string $unitStream, bool $exam = true): Chapter
    {
        $subject = Subject::factory()->create(['stream' => $subjectStream, 'is_exam_subject' => $exam]);
        $unit = Unit::factory()->create(['subject_id' => $subject->id, 'grade' => $grade, 'stream' => $unitStream]);

        return Chapter::factory()->create(['unit_id' => $unit->id]);
    }

    private function student(int $grade = 12, ?string $stream = 'natural'): User
    {
        $user = User::factory()->create(['grade' => $grade, 'stream' => $stream]);
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @return list<int>
     *
     * @phpstan-impure
     */
    private function challengeIds(): array
    {
        return array_column($this->getJson('/api/v1/challenge')->assertOk()->json('questions'), 'id');
    }

    /**
     * @param  list<int>  $ids
     * @return array<string, mixed>
     */
    private function payload(array $ids, string $date = '2026-11-25', int $right = 0): array
    {
        $answers = [];
        foreach ($ids as $i => $id) {
            $key = Question::query()->findOrFail($id)->answer_key['correct'];
            $answers[] = ['question_id' => $id, 'version' => 1, 'response' => $i < $right ? $key : 'Z', 'time_ms' => 6000];
        }

        return [
            'client_uuid' => (string) Str::uuid(),
            'mode' => 'daily_challenge',
            'challenge_date' => $date,
            'started_at' => now()->subMinutes(3)->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'answers' => $answers,
        ];
    }

    public function test_ten_questions_from_my_exam_subjects_taking_turns(): void
    {
        $this->student(12, 'natural');
        $ids = $this->challengeIds();
        $this->assertCount(DailyChallengeService::SIZE, $ids);
        $this->assertCount(10, array_unique($ids));
        $chapters = Question::query()->whereIn('id', $ids)->pluck('chapter_id')->countBy()->all();
        // Maths (both streams) and Physics (natural) take turns: 5 + 5. No History (social).
        $this->assertSame([$this->maths->id => 5, $this->physics->id => 5], array_replace([$this->maths->id => 0, $this->physics->id => 0], $chapters));
    }

    public function test_same_questions_for_the_same_grade_and_stream_and_kept_all_day(): void
    {
        $this->student(12, 'natural');
        $first = $this->challengeIds();
        $this->student(12, 'natural');
        $this->assertSame($first, $this->challengeIds());

        // New content during the day does not change today's set.
        Question::factory()->count(20)->withOptions()->create(['chapter_id' => $this->physics->id]);
        $this->assertSame($first, $this->challengeIds());

        $this->student(12, 'social');
        $social = $this->challengeIds();
        $this->assertNotSame($first, $social);
        $this->assertSame(0, Question::query()->whereIn('id', $social)->where('chapter_id', $this->physics->id)->count());

        // A new day, a new set.
        Carbon::setTestNow(Carbon::parse('2026-11-26 08:00', DailyChallengeService::TIMEZONE));
        $this->student(12, 'natural');
        $this->assertNotSame($first, $this->challengeIds());
    }

    public function test_only_visible_single_choice_questions_without_passages_from_exam_subjects(): void
    {
        $other = $this->chapter(null, 12, null, exam: false);
        Question::factory()->count(10)->withOptions()->create(['chapter_id' => $other->id]);
        $passage = Passage::query()->create(['title' => 'P', 'body' => 'Text']);
        Question::factory()->count(5)->withOptions()->create(['chapter_id' => $this->maths->id, 'passage_id' => $passage->id, 'type' => QuestionType::PassageChoice]);
        Question::factory()->count(5)->create(['chapter_id' => $this->maths->id, 'type' => QuestionType::TrueFalse, 'answer_key' => ['correct' => 'TRUE']]);
        Question::factory()->count(5)->withOptions()->status(QuestionStatus::Draft)->create(['chapter_id' => $this->maths->id]);

        $this->student(12, 'natural');
        $ids = $this->challengeIds();
        $bad = Question::query()->whereIn('id', $ids)->where(fn ($q) => $q
            ->where('chapter_id', $other->id)
            ->orWhereNotNull('passage_id')
            ->orWhere('type', '!=', QuestionType::SingleChoice->value)
            ->orWhere('status', QuestionStatus::Draft->value))->count();
        $this->assertSame(0, $bad);
    }

    public function test_a_grade_without_enough_questions_uses_grade_12(): void
    {
        $g10 = $this->chapter(null, 10, null);
        Question::factory()->count(3)->withOptions()->create(['chapter_id' => $g10->id]);
        $this->student(10, null);
        $ids = $this->challengeIds();
        // Grade 12 subjects shared by both streams only (here: the 8 Maths questions).
        $this->assertCount(8, $ids);
        $this->assertSame(8, Question::query()->whereIn('id', $ids)->where('chapter_id', $this->maths->id)->count());
    }

    public function test_switched_off_or_no_questions(): void
    {
        $this->student();
        app(FeatureSwitches::class)->set('daily_challenge', false);
        $this->getJson('/api/v1/challenge')->assertForbidden()->assertJsonPath('code', 'feature_disabled');
        app(FeatureSwitches::class)->set('daily_challenge', true);
        Question::query()->update(['status' => QuestionStatus::Draft->value]);
        $this->getJson('/api/v1/challenge')->assertNotFound()->assertJsonPath('code', 'no_questions');
    }

    public function test_playing_counts_once_a_day_and_only_challenge_questions_count(): void
    {
        $this->student(12, 'natural');
        $this->getJson('/api/v1/challenge')->assertJsonPath('result', null);
        $ids = $this->challengeIds();
        $stranger = Question::query()->where('chapter_id', $this->history->id)->firstOrFail()->id;

        $first = $this->postJson('/api/v1/attempts', $this->payload([...$ids, $stranger], right: 7))
            ->assertCreated()
            ->assertJsonPath('attempt.total', 10)
            ->assertJsonPath('attempt.correct', 7)
            ->assertJsonPath('attempt.challenge.date', '2026-11-25')
            ->assertJsonPath('attempt.challenge.better_than_percent', null);
        $this->assertNull($first->json('attempt.exam'));

        // A second try is saved as practice but the day's result stays the first one.
        $this->postJson('/api/v1/attempts', $this->payload($ids, right: 10))->assertCreated();
        $this->getJson('/api/v1/challenge')
            ->assertJsonPath('result.correct', 7)
            ->assertJsonPath('result.total', 10)
            ->assertJsonPath('result.players', 0);
    }

    public function test_dates_are_checked(): void
    {
        $this->student();
        $ids = $this->challengeIds();
        // Yesterday is accepted (started before midnight, uploaded later); older is refused.
        $this->postJson('/api/v1/attempts', $this->payload($ids, '2026-11-24'))->assertCreated();
        $this->postJson('/api/v1/attempts', $this->payload($ids, '2026-11-23'))
            ->assertUnprocessable()->assertJsonValidationErrors('challenge_date');
        $this->postJson('/api/v1/attempts', $this->payload($ids, '2026-11-26'))
            ->assertUnprocessable()->assertJsonValidationErrors('challenge_date');
        $this->postJson('/api/v1/attempts', [...$this->payload($ids), 'challenge_date' => null])
            ->assertUnprocessable()->assertJsonValidationErrors('challenge_date');
        $this->postJson('/api/v1/attempts', [...$this->payload($ids), 'mode' => 'quick10'])
            ->assertUnprocessable()->assertJsonValidationErrors('challenge_date');
    }

    public function test_better_than_percent_after_enough_players(): void
    {
        app(Settings::class)->set('percentile_min_students', 3);
        $this->student(12, 'natural');
        $ids = $this->challengeIds();
        foreach ([2, 5, 9] as $right) {
            $this->student(12, 'natural');
            $this->postJson('/api/v1/attempts', $this->payload($ids, right: $right))->assertCreated();
        }
        // Another stream's players are not compared.
        $this->student(12, 'social');
        $this->postJson('/api/v1/attempts', $this->payload($this->challengeIds(), right: 0))->assertCreated();

        $this->student(12, 'natural');
        $this->postJson('/api/v1/attempts', $this->payload($ids, right: 6))
            ->assertJsonPath('attempt.challenge.better_than_percent', 66)
            ->assertJsonPath('attempt.challenge.players', 3);
        $this->getJson('/api/v1/challenge')->assertJsonPath('result.better_than_percent', 66);
    }
}
