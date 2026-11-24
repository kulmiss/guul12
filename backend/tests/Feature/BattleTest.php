<?php

namespace Tests\Feature;

use App\Models\Battle;
use App\Models\Chapter;
use App\Models\Question;
use App\Models\User;
use App\Services\BattleService;
use App\Services\FeatureSwitches;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class BattleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-11-25 07:00', 'UTC'));
        $chapter = Chapter::factory()->create();
        Question::factory()->count(15)->withOptions()->create(['chapter_id' => $chapter->id]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    private function student(?string $nickname = 'Player', string $stream = 'natural'): User
    {
        return User::factory()->create([
            'grade' => 12,
            'stream' => $stream,
            'nickname' => $nickname,
            'avatar_style' => 'thumbs',
            'avatar_seed' => 'a01',
            'region' => 'somali',
        ]);
    }

    private function as(User $user): static
    {
        Sanctum::actingAs($user);

        return $this;
    }

    /**
     * @param  list<int>  $ids
     */
    private function play(int $battleId, array $ids, int $right, int $msEach = 5000): void
    {
        $answers = [];
        foreach ($ids as $i => $id) {
            $key = Question::query()->findOrFail($id)->answer_key['correct'];
            $answers[] = ['question_id' => $id, 'version' => 1, 'response' => $i < $right ? $key : 'Z', 'time_ms' => $msEach];
        }
        $this->postJson('/api/v1/attempts', [
            'client_uuid' => (string) Str::uuid(),
            'mode' => 'battle',
            'battle_id' => $battleId,
            'started_at' => now()->subMinutes(5)->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'answers' => $answers,
        ])->assertCreated();
    }

    public function test_friend_battle_code_join_and_result(): void
    {
        $ayan = $this->student('Ayan');
        $abdi = $this->student('Abdi');

        $created = $this->as($ayan)->postJson('/api/v1/battles', ['type' => 'friend'])->assertCreated();
        $code = $created->json('battle.code');
        $this->assertMatchesRegularExpression('/^[A-HJ-NP-Z2-9]{6}$/', $code);
        $created->assertJsonPath('battle.status', 'your_turn')
            ->assertJsonPath('battle.opponent', null)
            ->assertJsonPath('battle.question_count', 10)
            ->assertJsonPath('battle.seconds_per_question', 60)
            ->assertJsonCount(10, 'questions');
        $id = $created->json('battle.id');
        $ids = array_column($created->json('questions'), 'id');

        $this->play($id, $ids, 7);
        $this->getJson("/api/v1/battles/{$id}")->assertJsonPath('battle.status', 'waiting');

        // The friend joins with the code (small letters and spaces are fine) and gets the same questions.
        $joined = $this->as($abdi)->postJson('/api/v1/battles/join', ['code' => ' '.strtolower($code).' '])->assertOk();
        $this->assertSame($ids, array_column($joined->json('questions'), 'id'));
        $joined->assertJsonPath('battle.code', null)
            ->assertJsonPath('battle.status', 'your_turn')
            ->assertJsonPath('battle.opponent.nickname', 'Ayan')
            ->assertJsonPath('battle.opponent.result.correct', 7);
        $this->assertStringNotContainsString('somali', $joined->getContent() ?: '');

        $this->play($id, $ids, 7, 4000); // same score, faster
        $this->getJson("/api/v1/battles/{$id}")->assertJsonPath('battle.status', 'won');
        $this->as($ayan)->getJson("/api/v1/battles/{$id}")
            ->assertJsonPath('battle.status', 'lost')
            ->assertJsonPath('battle.opponent.nickname', 'Abdi')
            ->assertJsonPath('battle.opponent.result', ['correct' => 7, 'total' => 10, 'time_ms' => 40000]);

        // A second try does not change the result.
        $this->play($id, $ids, 10, 1000);
        $this->getJson("/api/v1/battles/{$id}")->assertJsonPath('battle.status', 'lost');
    }

    public function test_join_errors(): void
    {
        $ayan = $this->student('Ayan');
        $code = $this->as($ayan)->postJson('/api/v1/battles', ['type' => 'friend'])->json('battle.code');
        $this->postJson('/api/v1/battles/join', ['code' => $code])->assertStatus(409)->assertJsonPath('code', 'own_battle');
        $this->as($this->student('B'))->postJson('/api/v1/battles/join', ['code' => 'ZZZZZZ'])->assertNotFound();
        $this->postJson('/api/v1/battles/join', ['code' => $code])->assertOk();
        $this->as($this->student('C'))->postJson('/api/v1/battles/join', ['code' => $code])->assertStatus(409)->assertJsonPath('code', 'battle_full');

        $late = $this->as($ayan)->postJson('/api/v1/battles', ['type' => 'friend'])->json('battle.code');
        Carbon::setTestNow(Carbon::now()->addHours(BattleService::HOURS + 1));
        $this->as($this->student('D'))->postJson('/api/v1/battles/join', ['code' => $late])->assertStatus(409)->assertJsonPath('code', 'expired');
    }

    public function test_quick_battle_matches_a_waiting_student_of_the_same_grade_and_stream(): void
    {
        $first = $this->student('First');
        $a = $this->as($first)->postJson('/api/v1/battles', ['type' => 'random'])->assertCreated();
        $a->assertJsonPath('battle.code', null)->assertJsonPath('battle.is_random', true);
        $battleId = $a->json('battle.id');

        // Another stream gets its own battle; the same student never meets themself.
        $social = $this->as($this->student('Social', 'social'))->postJson('/api/v1/battles', ['type' => 'random']);
        $this->assertNotSame($battleId, $social->json('battle.id'));
        $again = $this->as($first)->postJson('/api/v1/battles', ['type' => 'random']);
        $this->assertNotSame($battleId, $again->json('battle.id'));

        $second = $this->as($this->student('Second'))->postJson('/api/v1/battles', ['type' => 'random']);
        $this->assertSame($battleId, $second->json('battle.id'));
        $second->assertJsonPath('battle.opponent.nickname', 'First');
        $this->assertSame(array_column($a->json('questions'), 'id'), array_column($second->json('questions'), 'id'));

        // Full now: the next student starts a new one.
        $third = $this->as($this->student('Third'))->postJson('/api/v1/battles', ['type' => 'random']);
        $this->assertNotSame($battleId, $third->json('battle.id'));
    }

    public function test_expired_battle_without_both_results(): void
    {
        $ayan = $this->student('Ayan');
        $id = $this->as($ayan)->postJson('/api/v1/battles', ['type' => 'friend'])->json('battle.id');
        Carbon::setTestNow(Carbon::now()->addHours(BattleService::HOURS + 1));
        $this->getJson("/api/v1/battles/{$id}")->assertJsonPath('battle.status', 'expired');
    }

    public function test_rules_nickname_limit_switch_privacy(): void
    {
        $nameless = $this->student(null);
        $this->as($nameless)->postJson('/api/v1/battles', ['type' => 'friend'])->assertUnprocessable()->assertJsonPath('code', 'nickname_required');
        $this->postJson('/api/v1/battles/join', ['code' => 'ABCDEF'])->assertUnprocessable()->assertJsonPath('code', 'nickname_required');

        $busy = $this->student('Busy');
        $this->as($busy);
        for ($i = 0; $i < BattleService::MAX_PER_DAY; $i++) {
            $this->postJson('/api/v1/battles', ['type' => 'friend'])->assertCreated();
        }
        $this->postJson('/api/v1/battles', ['type' => 'friend'])->assertStatus(429)->assertJsonPath('code', 'battle_limit');
        $this->assertCount(20, $this->getJson('/api/v1/battles')->assertOk()->json('battles'));

        // Someone else's battle cannot be seen or played.
        $id = Battle::query()->where('creator_id', $busy->id)->value('id');
        $stranger = $this->student('Stranger');
        $this->as($stranger)->getJson("/api/v1/battles/{$id}")->assertNotFound();
        $this->postJson('/api/v1/attempts', [
            'client_uuid' => (string) Str::uuid(),
            'mode' => 'battle',
            'battle_id' => $id,
            'started_at' => now()->subMinute()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'answers' => [['question_id' => Question::query()->value('id'), 'version' => 1, 'response' => 'A', 'time_ms' => 5000]],
        ])->assertUnprocessable()->assertJsonValidationErrors('battle_id');

        app(FeatureSwitches::class)->set('battles', false);
        $this->getJson('/api/v1/battles')->assertForbidden()->assertJsonPath('code', 'feature_disabled');
    }

    public function test_only_battle_questions_count_and_battle_id_rules(): void
    {
        $ayan = $this->student('Ayan');
        $created = $this->as($ayan)->postJson('/api/v1/battles', ['type' => 'friend']);
        $ids = array_column($created->json('questions'), 'id');
        $outside = Question::query()->whereNotIn('id', $ids)->value('id');
        $this->play($created->json('battle.id'), [...$ids, $outside], 10);
        $this->getJson('/api/v1/battles/'.$created->json('battle.id'))->assertJsonPath('battle.me.result.total', 10);

        $base = [
            'client_uuid' => (string) Str::uuid(),
            'started_at' => now()->subMinute()->toIso8601String(),
            'finished_at' => now()->toIso8601String(),
            'answers' => [['question_id' => $ids[0], 'version' => 1, 'response' => 'A', 'time_ms' => 5000]],
        ];
        $this->postJson('/api/v1/attempts', [...$base, 'mode' => 'battle'])->assertJsonValidationErrors('battle_id');
        $this->postJson('/api/v1/attempts', [...$base, 'mode' => 'quick10', 'battle_id' => 1])->assertJsonValidationErrors('battle_id');
    }

    public function test_draw_on_equal_score_and_time(): void
    {
        $this->assertSame('draw', BattleService::status(
            new Battle(['expires_at' => Carbon::now()->addHour()]),
            true,
            ['correct' => 5, 'total' => 10, 'time_ms' => 1000],
            ['correct' => 5, 'total' => 10, 'time_ms' => 1000],
        ));
    }
}
