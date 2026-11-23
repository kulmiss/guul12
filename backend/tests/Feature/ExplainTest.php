<?php

namespace Tests\Feature;

use App\Enums\PremiumPlan;
use App\Enums\QuestionStatus;
use App\Enums\UserRole;
use App\Models\AiExplanation;
use App\Models\Question;
use App\Models\User;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\ExplanationProvider;
use App\Services\FeatureSwitches;
use App\Services\PremiumService;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request as HttpRequest;
use Illuminate\Support\Facades\Http;
use Illuminate\Testing\TestResponse;
use Laravel\Sanctum\Sanctum;
use Symfony\Component\HttpFoundation\Response;
use Tests\Fakes\FakeAi;
use Tests\TestCase;

class ExplainTest extends TestCase
{
    use RefreshDatabase;

    private FakeAi $ai;

    private Question $question;

    protected function setUp(): void
    {
        parent::setUp();
        $this->ai = new FakeAi;
        $this->app->instance(ExplanationProvider::class, $this->ai);
        $this->question = Question::factory()->withOptions()->create(['stem' => 'What is 2 + 2?', 'explanation' => 'Add them.']);
    }

    private function premium(): User
    {
        $user = User::factory()->create();
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        app(PremiumService::class)->grant($user, PremiumPlan::Term, $helper, 'TX-'.$user->id);
        Sanctum::actingAs($user);

        return $user;
    }

    /**
     * @return TestResponse<Response>
     */
    private function explain(?Question $q = null): TestResponse
    {
        return $this->postJson('/api/v1/questions/'.($q ?? $this->question)->id.'/explain');
    }

    public function test_free_students_see_premium_only(): void
    {
        Sanctum::actingAs(User::factory()->create());
        $this->explain()->assertForbidden()->assertJsonPath('code', 'premium_required');
        $this->assertSame([], $this->ai->prompts);
    }

    public function test_made_once_then_reused_for_everyone(): void
    {
        $this->premium();
        $this->explain()->assertOk()
            ->assertJsonPath('explanation.body', 'Step 1: add $2 + 2$. Answer #1.')
            ->assertJsonPath('explanation.cached', false)
            ->assertJsonPath('remaining_today', 19);
        $prompt = $this->ai->prompts[0];
        $this->assertStringContainsString('What is 2 + 2?', $prompt);
        $this->assertStringContainsString('Correct answer: '.$this->question->answer_key['correct'], $prompt);
        $this->assertStringContainsString('Add them.', $prompt);
        foreach ($this->question->options as $o) {
            $this->assertStringContainsString($o->key.') '.$o->body, $prompt);
        }

        $this->premium();
        $this->explain()->assertOk()->assertJsonPath('explanation.cached', true)->assertJsonPath('explanation.body', 'Step 1: add $2 + 2$. Answer #1.');
        $this->assertCount(1, $this->ai->prompts);
        $this->assertSame(280, AiExplanation::query()->sole()->cost_micro_usd);

        // A changed question (new version) gets a new explanation.
        $this->question->forceFill(['version' => 2])->save();
        $this->explain()->assertJsonPath('explanation.cached', false);
        $this->assertCount(2, $this->ai->prompts);
    }

    public function test_daily_limit_and_monthly_budget(): void
    {
        app(Settings::class)->set('premium_explain_more_per_day', 2);
        $this->premium();
        $this->explain()->assertOk();
        $this->explain()->assertOk()->assertJsonPath('remaining_today', 0);
        $this->explain()->assertStatus(429)->assertJsonPath('code', 'explain_limit');

        // Budget used up: saved explanations still work, new ones are refused.
        app(Settings::class)->set('ai_monthly_cap_usd', 0);
        $this->premium();
        $this->explain()->assertOk()->assertJsonPath('explanation.cached', true);
        $other = Question::factory()->withOptions()->create();
        $this->explain($other)->assertStatus(503)->assertJsonPath('code', 'ai_budget');
        $this->assertCount(1, $this->ai->prompts);
    }

    public function test_no_key_switch_off_failures_and_hidden_questions(): void
    {
        $this->ai->configured = false;
        $this->getJson('/api/v1/config')->assertJsonPath('features.explain_more', false);
        $this->premium();
        $this->explain()->assertForbidden()->assertJsonPath('code', 'feature_disabled');

        $this->ai->configured = true;
        $this->getJson('/api/v1/config')->assertJsonPath('features.explain_more', true);
        app(FeatureSwitches::class)->set('explain_more', false);
        $this->getJson('/api/v1/config')->assertJsonPath('features.explain_more', false);
        $this->explain()->assertForbidden();
        app(FeatureSwitches::class)->set('explain_more', true);

        $this->ai->fail = true;
        $this->explain()->assertStatus(503)->assertJsonPath('code', 'ai_unavailable');
        $this->assertSame(0, AiExplanation::query()->count());
        $this->ai->fail = false;
        // A failed try does not count.
        $this->explain()->assertOk()->assertJsonPath('remaining_today', 19);

        $draft = Question::factory()->withOptions()->status(QuestionStatus::Draft)->create();
        $this->explain($draft)->assertNotFound();
    }

    public function test_feedback_votes_and_hiding(): void
    {
        $this->premium();
        $id = (int) $this->explain()->json('explanation.id');
        $voters = [];
        for ($i = 0; $i < 3; $i++) {
            $voters[] = $this->premium();
            $this->explain()->assertJsonPath('explanation.id', $id);
            $this->postJson("/api/v1/explanations/{$id}/feedback", ['helpful' => false])->assertOk();
        }
        // The same vote twice counts once; a changed vote moves.
        $this->postJson("/api/v1/explanations/{$id}/feedback", ['helpful' => false])->assertOk();
        $e = AiExplanation::query()->findOrFail($id);
        $this->assertSame([0, 3, true], [$e->helpful, $e->not_helpful, $e->hidden]);

        // Hidden: the next student gets a new one.
        $this->premium();
        $this->explain()->assertJsonPath('explanation.cached', false);
        $this->assertNotSame($id, $this->explain()->json('explanation.id'));

        Sanctum::actingAs($voters[0]);
        $this->postJson("/api/v1/explanations/{$id}/feedback", ['helpful' => true])->assertOk();
        $e->refresh();
        $this->assertSame([1, 2], [$e->helpful, $e->not_helpful]);

        // Only students who were shown it can vote.
        Sanctum::actingAs(User::factory()->create());
        $this->postJson("/api/v1/explanations/{$id}/feedback", ['helpful' => true])->assertNotFound();
    }

    public function test_anthropic_provider_request_answer_and_cost(): void
    {
        Http::fake([AnthropicProvider::URL => Http::response([
            'model' => 'claude-haiku-5-5',
            'content' => [['type' => 'text', 'text' => ' Because 2 + 2 = 4. ']],
            'usage' => ['input_tokens' => 800, 'output_tokens' => 400],
        ])]);
        $provider = new AnthropicProvider('sk-test-key', 'claude-haiku-5-5', 0.10, 0.50);
        $answer = $provider->answer('system text', 'prompt text', 500);

        $this->assertSame(['Because 2 + 2 = 4.', 800, 400, 'claude-haiku-5-5'], [$answer->text, $answer->inputTokens, $answer->outputTokens, $answer->model]);
        $this->assertSame(280, $provider->costMicroUsd($answer)); // 800 × 0.10 + 400 × 0.50 micro-dollars
        Http::assertSent(fn (HttpRequest $r): bool => $r->url() === AnthropicProvider::URL
            && $r->hasHeader('x-api-key', 'sk-test-key')
            && $r->hasHeader('anthropic-version', '2023-06-01')
            && $r['model'] === 'claude-haiku-5-5'
            && $r['max_tokens'] === 500
            && $r['system'] === 'system text'
            && $r['messages'] === [['role' => 'user', 'content' => 'prompt text']]);
    }

    public function test_anthropic_provider_errors_never_show_the_key(): void
    {
        $this->assertFalse((new AnthropicProvider(null, 'm', 0.1, 0.5))->isConfigured());
        $this->assertFalse((new AnthropicProvider('  ', 'm', 0.1, 0.5))->isConfigured());

        Http::fake([AnthropicProvider::URL => Http::response(['error' => 'overloaded'], 529)]);
        try {
            (new AnthropicProvider('sk-secret', 'm', 0.1, 0.5))->answer('s', 'p', 10);
            $this->fail('no exception');
        } catch (AiUnavailable $e) {
            $this->assertStringNotContainsString('sk-secret', $e->getMessage());
            $this->assertStringContainsString('529', $e->getMessage());
        }

        Http::fake([AnthropicProvider::URL => Http::response(['content' => []])]);
        $this->expectException(AiUnavailable::class);
        (new AnthropicProvider('sk-secret', 'm', 0.1, 0.5))->answer('s', 'p', 10);
    }
}
