<?php

namespace Tests\Feature;

use App\Models\Chapter;
use App\Models\Question;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Middleware\ThrottleRequests;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Route as Router;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

/**
 * Bug hunt: every API address, called the way a broken or hostile phone might call it.
 * Nothing may answer with a server error (500), and no address may work without a token
 * except the two public ones.
 */
class ApiRobustnessTest extends TestCase
{
    use RefreshDatabase;

    /** Addresses that work without a token. */
    private const PUBLIC = ['api.v1.config', 'api.v1.auth.device'];

    /** Every field name the API reads, so each one meets every bad value. */
    private const FIELDS = [
        'after', 'answers', 'app_version', 'avatar_seed', 'avatar_style', 'battle_id', 'challenge_date',
        'chapter_id', 'city', 'client_uuid', 'code', 'confirm', 'exam', 'finished_at', 'gender', 'grade',
        'helpful', 'id_token', 'install_id', 'is_retaker', 'kind', 'mock_id', 'mode', 'nickname', 'note',
        'onboarded', 'platform', 'reason', 'ref', 'region', 'reminder_time', 'since', 'started_at',
        'stream', 'subject_id', 'target_score', 'type', 'version', 'response', 'time_ms', 'question_id',
    ];

    /**
     * @return array<string, mixed>
     */
    private static function badValues(): array
    {
        return [
            'empty string' => '',
            'null' => null,
            'array' => ['a', 'b'],
            'nested array' => ['a' => ['b' => ['c' => 1]]],
            'list of arrays' => [[1], [2]],
            'huge string' => str_repeat('x', 100_000),
            'negative' => -1,
            'huge number' => PHP_INT_MAX,
            'float' => 1.5,
            'true' => true,
            'sql' => "1' OR '1'='1'; DROP TABLE users;--",
            'html' => '<script>alert(1)</script>',
            'path' => '../../../../etc/passwd',
            'null byte' => "a\0b",
            'emoji' => '😀😀😀',
            'number string' => '00012',
            'date' => '2026-13-45T99:99:99Z',
        ];
    }

    /**
     * @return list<Route>
     */
    private function apiRoutes(): array
    {
        return array_values(array_filter(
            Router::getRoutes()->getRoutes(),
            fn (Route $r): bool => str_starts_with((string) $r->getName(), 'api.v1.'),
        ));
    }

    private function uri(Route $route, string $param = '1'): string
    {
        return '/'.preg_replace('/\{[^}]+\}/', $param, $route->uri());
    }

    private function method(Route $route): string
    {
        return (string) collect($route->methods())->reject(fn (string $m): bool => $m === 'HEAD')->first();
    }

    public function test_every_address_needs_a_token_except_the_public_ones(): void
    {
        $routes = $this->apiRoutes();
        $this->assertGreaterThanOrEqual(34, count($routes), 'all API addresses are found');
        foreach ($routes as $route) {
            if (in_array($route->getName(), self::PUBLIC, true)) {
                continue;
            }
            $uri = $this->uri($route, $route->getName() === 'api.v1.attempts.show' ? '6a1f1f0e-7b9b-4f3e-9d39-1f2a3b4c5d6e' : '1');
            $this->json($this->method($route), $uri)->assertUnauthorized();
            $this->withToken('1|not-a-real-token')->json($this->method($route), $uri)->assertUnauthorized();
            $this->app['auth']->forgetGuards();
        }
    }

    public function test_bad_values_never_cause_a_server_error(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $chapter = Chapter::factory()->create();
        Question::factory()->count(3)->withOptions()->create(['chapter_id' => $chapter->id]);
        $me = User::factory()->create(['grade' => 12, 'stream' => 'natural', 'nickname' => 'Ayan']);

        $failures = [];
        foreach ($this->apiRoutes() as $route) {
            $method = $this->method($route);
            foreach (self::badValues() as $label => $value) {
                $payload = array_fill_keys(self::FIELDS, $value);
                foreach (['1', '999999'] as $param) {
                    Sanctum::actingAs($me->fresh() ?? $me);
                    $uri = $this->uri($route, $route->getName() === 'api.v1.attempts.show' ? '6a1f1f0e-7b9b-4f3e-9d39-1f2a3b4c5d6e' : $param);
                    $response = $method === 'GET'
                        ? $this->getJson($uri.'?'.http_build_query($payload))
                        : $this->json($method, $uri, $payload);
                    if ($response->status() >= 500) {
                        $failures[] = "{$method} {$uri} with {$label}: {$response->status()}";
                    }
                }
            }
        }
        $this->assertSame([], $failures);
        $this->assertNotNull($me->fresh(), 'the student still exists (no bad value deletes an account)');
    }

    /**
     * A good request with ONE field broken at a time (the all-fields test can hide a crash behind
     * an earlier validation error).
     */
    public function test_one_bad_field_in_a_good_request_never_causes_a_server_error(): void
    {
        $this->withoutMiddleware(ThrottleRequests::class);
        $chapter = Chapter::factory()->create();
        $question = Question::factory()->withOptions()->create(['chapter_id' => $chapter->id]);
        $me = User::factory()->create(['grade' => 12, 'stream' => 'natural', 'nickname' => 'Ayan']);
        $subjectId = $chapter->unit->subject_id;
        $answer = ['question_id' => $question->id, 'version' => 1, 'response' => 'A', 'time_ms' => 5000];

        /** @var array<string, array{0: string, 1: string, 2: array<string, mixed>}> $good */
        $good = [
            'device' => ['POST', '/api/v1/auth/device', ['install_id' => '6a1f1f0e-7b9b-4f3e-9d39-1f2a3b4c5d6e', 'platform' => 'android', 'app_version' => '1.0.0']],
            'google' => ['POST', '/api/v1/auth/google', ['id_token' => 'abc', 'install_id' => '6a1f1f0e-7b9b-4f3e-9d39-1f2a3b4c5d6e']],
            'me' => ['PATCH', '/api/v1/me', ['grade' => 12, 'stream' => 'natural', 'is_retaker' => false, 'region' => 'Somali', 'city' => 'Jigjiga',
                'gender' => 'female', 'target_score' => 400, 'reminder_time' => '19:30', 'nickname' => 'Ayan2', 'avatar_style' => 'thumbs',
                'avatar_seed' => 'a01', 'onboarded' => true]],
            'attempt' => ['POST', '/api/v1/attempts', ['client_uuid' => '7b2f1f0e-7b9b-4f3e-9d39-1f2a3b4c5d6e', 'mode' => 'quick10',
                'started_at' => now()->subMinutes(5)->toIso8601String(), 'finished_at' => now()->toIso8601String(), 'answers' => [$answer]]],
            'answer' => ['POST', '/api/v1/attempts', ['client_uuid' => '8c2f1f0e-7b9b-4f3e-9d39-1f2a3b4c5d6e', 'mode' => 'quick10',
                'started_at' => now()->subMinutes(5)->toIso8601String(), 'finished_at' => now()->toIso8601String()]],
            'random exam' => ['POST', '/api/v1/exams/random', ['subject_id' => $subjectId, 'grade' => 12]],
            'reward' => ['POST', '/api/v1/rewards', ['kind' => 'random_exam']],
            'battle' => ['POST', '/api/v1/battles', ['type' => 'friend', 'subject_id' => $subjectId]],
            'join' => ['POST', '/api/v1/battles/join', ['code' => 'ABC123']],
            'referral' => ['POST', '/api/v1/referral', ['code' => 'ABC123']],
            'report' => ['POST', '/api/v1/questions/'.$question->id.'/reports', ['reason' => 'typo', 'note' => 'x']],
            'nickname report' => ['POST', '/api/v1/nickname-reports', ['ref' => 'x', 'reason' => 'rude', 'note' => 'x']],
            'explain' => ['POST', '/api/v1/questions/'.$question->id.'/explain', []],
            'feedback' => ['POST', '/api/v1/explanations/1/feedback', ['helpful' => true]],
            'changes' => ['GET', '/api/v1/sync/changes', ['since' => 0]],
            'pack' => ['GET', '/api/v1/sync/pack', ['subject_id' => $subjectId, 'grade' => 12]],
            'history' => ['GET', '/api/v1/history', ['after' => 0]],
            'delete' => ['DELETE', '/api/v1/me', ['confirm' => 'nope']],
        ];

        $failures = [];
        $sent = 0;
        foreach ($good as $name => [$method, $uri, $fields]) {
            $cases = [];
            foreach (array_keys($fields) as $field) {
                foreach (self::badValues() as $label => $value) {
                    $cases["{$field} = {$label}"] = [...$fields, $field => $value];
                }
            }
            if ($name === 'answer') {
                foreach (array_keys($answer) as $field) {
                    foreach (self::badValues() as $label => $value) {
                        $cases["answers.0.{$field} = {$label}"] = [...$fields, 'answers' => [[...$answer, $field => $value]]];
                    }
                }
                $cases['answer not an object'] = [...$fields, 'answers' => ['x']];
                $cases['1000 answers'] = [...$fields, 'answers' => array_fill(0, 1000, $answer)];
            }
            foreach ($cases as $case => $payload) {
                if ($name === 'attempt' || $name === 'answer') {
                    $payload['client_uuid'] = is_string($payload['client_uuid'] ?? null) && str_contains((string) $payload['client_uuid'], '-')
                        ? (string) Str::uuid() : ($payload['client_uuid'] ?? null);
                }
                Sanctum::actingAs($me);
                $response = $method === 'GET'
                    ? $this->getJson($uri.'?'.http_build_query($payload))
                    : $this->json($method, $uri, $payload);
                $sent++;
                if ($response->status() >= 500) {
                    $failures[] = "{$name}: {$case}: {$response->status()}";
                }
            }
        }
        $this->assertSame([], $failures);
        $this->assertGreaterThanOrEqual(800, $sent, 'every field of every request was tried');
        $this->assertNotNull($me->fresh());
    }
}
