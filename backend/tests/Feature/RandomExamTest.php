<?php

namespace Tests\Feature;

use App\Enums\PremiumPlan;
use App\Enums\QuestionStatus;
use App\Enums\QuestionType;
use App\Enums\UserRole;
use App\Models\Chapter;
use App\Models\Passage;
use App\Models\Question;
use App\Models\Unit;
use App\Models\User;
use App\Services\FeatureSwitches;
use App\Services\PremiumService;
use App\Services\RandomExamBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class RandomExamTest extends TestCase
{
    use RefreshDatabase;

    private User $student;

    private Unit $unit;

    /** @var list<Chapter> */
    private array $chapters;

    private const LETTERS = ['A', 'C', 'B', 'D', 'B', 'A', 'D', 'C', 'A', 'B', 'D', 'C'];

    private int $n = 0;

    protected function setUp(): void
    {
        parent::setUp();
        $this->student = User::factory()->create(['grade' => 12, 'stream' => 'natural']);
        Sanctum::actingAs($this->student);
        $first = Chapter::factory()->create(['number' => 1]);
        $this->unit = $first->unit;
        $this->unit->forceFill(['grade' => 12])->save();
        $this->chapters = [
            $first,
            Chapter::factory()->create(['unit_id' => $this->unit->id, 'number' => 2]),
            Chapter::factory()->create(['unit_id' => $this->unit->id, 'number' => 3]),
        ];
        // 30 + 20 + 10 choice questions, plus questions that must never appear.
        foreach ([30, 20, 10] as $i => $count) {
            for ($k = 0; $k < $count; $k++) {
                $this->question($this->chapters[$i]);
            }
        }
        $this->question($this->chapters[0], ['status' => QuestionStatus::Draft]);
        $this->question($this->chapters[0], ['type' => QuestionType::TrueFalse, 'answer_key' => ['correct' => 'TRUE']]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function question(Chapter $chapter, array $attributes = []): Question
    {
        $letter = self::LETTERS[$this->n++ % count(self::LETTERS)];

        return Question::factory()->withOptions()->create([
            'chapter_id' => $chapter->id,
            'stem' => "Question {$this->n}",
            'answer_key' => ['correct' => $letter],
            ...$attributes,
        ]);
    }

    /**
     * @return TestResponse<JsonResponse>
     */
    private function draw(): TestResponse
    {
        return $this->postJson('/api/v1/exams/random', ['subject_id' => $this->unit->subject_id]);
    }

    private function makePremium(): void
    {
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        app(PremiumService::class)->grant($this->student, PremiumPlan::Term, $helper, 'TX-R');
    }

    public function test_a_random_mock_covers_every_chapter_with_choice_questions_only(): void
    {
        $json = $this->draw()->assertCreated()->json();

        $this->assertSame([50, 75], [$json['exam']['question_count'], $json['exam']['minutes']]);
        $ids = array_column($json['questions'], 'id');
        $this->assertCount(50, array_unique($ids));
        $this->assertSame(['single_choice'], array_values(array_unique(array_column($json['questions'], 'type'))));
        $this->assertNotContains('Question 61', array_column($json['questions'], 'stem'), 'hidden question');
        // In proportion: 30/20/10 of 60 → 25/17/8, and in chapter order.
        $chapterIds = array_column($json['questions'], 'chapter_id');
        $perChapter = array_count_values($chapterIds);
        foreach ([25, 17, 8] as $i => $expected) {
            $this->assertSame($expected, $perChapter[$this->chapters[$i]->id]);
        }
        $this->assertSame(array_map(fn (Chapter $c) => $c->id, $this->chapters), array_values(array_unique($chapterIds)));
        // No pattern a student could learn.
        $byId = Question::query()->whereIn('id', $ids)->get()->keyBy('id');
        $ordered = array_map(fn (int $id) => $byId->get($id) ?? throw new \RuntimeException('missing'), $ids);
        $this->assertFalse(app(RandomExamBuilder::class)->hasPattern($ordered));
    }

    public function test_reading_passages_stay_whole_and_together(): void
    {
        $passage = Passage::query()->create(['uid' => 'g12-english-1-1-ps01', 'title' => 'Read', 'body' => 'Text']);
        foreach (range(1, 4) as $_) {
            $this->question($this->chapters[2], ['type' => QuestionType::PassageChoice, 'passage_id' => $passage->id]);
        }

        // Repeat draws: whenever the passage is picked, all four questions sit side by side.
        // (About 4 in 5 draws pick it, so 20 draws all missing it would take ~1 in 10^14.)
        $seen = 0;
        for ($i = 0; $i < 20 && $seen < 3; $i++) {
            $this->makePremiumOnce();
            $json = $this->draw()->assertCreated()->json();
            $positions = array_keys(array_filter($json['questions'], fn (array $q) => $q['passage_id'] === $passage->id));
            if ($positions === []) {
                continue;
            }
            $seen++;
            $this->assertCount(4, $positions);
            $this->assertSame(range($positions[0], $positions[0] + 3), $positions);
            $this->assertSame([['id' => $passage->id, 'title' => 'Read', 'body' => 'Text']], $json['passages']);
        }
        $this->assertGreaterThan(0, $seen);
    }

    private bool $premiumDone = false;

    private function makePremiumOnce(): void
    {
        if (! $this->premiumDone) {
            $this->makePremium();
            $this->premiumDone = true;
        }
    }

    public function test_free_students_get_one_random_mock_per_ethiopian_day(): void
    {
        // 23:30 in Addis Ababa = 20:30 UTC.
        Carbon::setTestNow(Carbon::parse('2026-12-01 20:30:00', 'UTC'));
        $this->draw()->assertCreated();
        $this->draw()->assertStatus(429)->assertJsonPath('code', 'daily_limit');

        // 00:10 the next day in Addis Ababa (21:10 UTC, still the same UTC day).
        Carbon::setTestNow(Carbon::parse('2026-12-01 21:10:00', 'UTC'));
        $this->draw()->assertCreated();
        $this->draw()->assertStatus(429);

        $this->makePremium();
        $this->draw()->assertCreated();
        $this->draw()->assertCreated();
        Carbon::setTestNow();
    }

    public function test_a_rewarded_video_unlocks_one_more_random_mock_up_to_two_a_day(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-12-01 08:00:00', 'UTC'));
        $this->draw()->assertCreated();
        $this->draw()->assertStatus(429)->assertJsonPath('code', 'daily_limit');

        $this->postJson('/api/v1/rewards', ['kind' => 'random_exam'])->assertCreated()->assertJsonPath('remaining_today', 1);
        $this->draw()->assertCreated();
        $this->draw()->assertStatus(429);
        $this->postJson('/api/v1/rewards', ['kind' => 'random_exam'])->assertCreated()->assertJsonPath('remaining_today', 0);
        $this->draw()->assertCreated();
        $this->postJson('/api/v1/rewards', ['kind' => 'random_exam'])->assertStatus(429)->assertJsonPath('code', 'reward_limit');
        $this->draw()->assertStatus(429);

        // A new day starts from zero again.
        Carbon::setTestNow(Carbon::parse('2026-12-02 08:00:00', 'UTC'));
        $this->postJson('/api/v1/rewards', ['kind' => 'random_exam'])->assertCreated();
        Carbon::setTestNow();
    }

    public function test_rewards_are_refused_when_switched_off_premium_or_unknown(): void
    {
        $this->postJson('/api/v1/rewards', ['kind' => 'gold'])->assertUnprocessable();
        app(FeatureSwitches::class)->set('rewarded_ads', false);
        $this->postJson('/api/v1/rewards', ['kind' => 'random_exam'])->assertForbidden();
        app(FeatureSwitches::class)->set('rewarded_ads', true);
        app(FeatureSwitches::class)->set('ads', false);
        $this->postJson('/api/v1/rewards', ['kind' => 'random_exam'])->assertForbidden();
        app(FeatureSwitches::class)->set('ads', true);
        $this->makePremium();
        $this->postJson('/api/v1/rewards', ['kind' => 'random_exam'])->assertForbidden();
    }

    public function test_the_other_streams_units_and_empty_subjects_are_left_out(): void
    {
        $social = Unit::factory()->create(['subject_id' => $this->unit->subject_id, 'grade' => 12, 'stream' => 'social', 'number' => 9]);
        $socialChapter = Chapter::factory()->create(['unit_id' => $social->id]);
        $this->question($socialChapter, ['stem' => 'Social only']);

        $this->makePremium();
        for ($i = 0; $i < 3; $i++) {
            $stems = array_column($this->draw()->assertCreated()->json('questions'), 'stem');
            $this->assertNotContains('Social only', $stems);
        }

        $empty = Unit::factory()->create(['grade' => 12]);
        $this->postJson('/api/v1/exams/random', ['subject_id' => $empty->subject_id])->assertNotFound()->assertJsonPath('code', 'no_questions');
        $this->postJson('/api/v1/exams/random', [])->assertUnprocessable();
    }
}
