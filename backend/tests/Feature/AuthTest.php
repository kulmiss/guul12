<?php

namespace Tests\Feature;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\Bookmark;
use App\Models\Device;
use App\Models\Question;
use App\Models\User;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Services\FeatureSwitches;
use App\Services\PremiumService;
use App\Services\StudentIdGenerator;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\Support\FakeGoogleVerifier;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->app->instance(GoogleIdTokenVerifier::class, new FakeGoogleVerifier);
    }

    /**
     * @return array{0: string, 1: string, 2: array<string, mixed>}
     */
    private function registerDevice(?string $installId = null): array
    {
        $installId ??= (string) Str::uuid();
        $response = $this->postJson('/api/v1/auth/device', [
            'install_id' => $installId,
            'platform' => 'android',
            'app_version' => '1.0.0+1',
        ]);
        $response->assertSuccessful();

        return [$installId, (string) $response->json('token'), (array) $response->json('user')];
    }

    public function test_new_install_gets_an_anonymous_account_with_a_student_id(): void
    {
        $response = $this->postJson('/api/v1/auth/device', [
            'install_id' => (string) Str::uuid(),
            'platform' => 'android',
        ]);

        $response->assertCreated()
            ->assertJsonPath('created', true)
            ->assertJsonPath('user.signed_in', false)
            ->assertJsonPath('user.premium.active', false);

        $this->assertTrue(StudentIdGenerator::isValid((string) $response->json('user.student_id')));
        $this->assertDatabaseCount('users', 1);
        $this->assertDatabaseCount('devices', 1);
    }

    public function test_install_id_is_stored_hashed(): void
    {
        $installId = (string) Str::uuid();
        $this->registerDevice($installId);

        $this->assertDatabaseMissing('devices', ['device_hash' => $installId]);
        $this->assertDatabaseHas('devices', ['device_hash' => Device::hashInstallId($installId)]);
    }

    public function test_same_install_gets_the_same_account_and_old_token_stops_working(): void
    {
        [$installId, $firstToken, $firstUser] = $this->registerDevice();

        $again = $this->postJson('/api/v1/auth/device', ['install_id' => $installId, 'platform' => 'android']);

        $again->assertOk()->assertJsonPath('created', false)
            ->assertJsonPath('user.student_id', $firstUser['student_id']);
        $this->assertDatabaseCount('users', 1);

        $this->app['auth']->forgetGuards();
        $this->withToken($firstToken)->getJson('/api/v1/me')->assertUnauthorized();

        $this->app['auth']->forgetGuards();
        $this->withToken((string) $again->json('token'))->getJson('/api/v1/me')->assertOk();
    }

    public function test_device_registration_validates_input(): void
    {
        $this->postJson('/api/v1/auth/device', ['install_id' => 'not-a-uuid', 'platform' => 'android'])
            ->assertUnprocessable()->assertJsonValidationErrors('install_id');
        $this->postJson('/api/v1/auth/device', ['install_id' => (string) Str::uuid(), 'platform' => 'windows'])
            ->assertUnprocessable()->assertJsonValidationErrors('platform');
    }

    public function test_api_needs_a_token(): void
    {
        $this->getJson('/api/v1/me')->assertUnauthorized();
        $this->getJson('/api/v1/catalog')->assertUnauthorized();
        $this->postJson('/api/v1/attempts', [])->assertUnauthorized();
    }

    public function test_google_sign_in_links_the_current_account(): void
    {
        [$installId, $token, $user] = $this->registerDevice();

        $response = $this->withToken($token)->postJson('/api/v1/auth/google', [
            'id_token' => 'good:sub-1:hodan@example.com',
            'install_id' => $installId,
        ]);

        $response->assertOk()
            ->assertJsonPath('merged', false)
            ->assertJsonPath('user.signed_in', true)
            ->assertJsonPath('user.student_id', $user['student_id']);
        $this->assertDatabaseHas('users', ['student_id' => $user['student_id'], 'google_sub' => 'sub-1', 'email' => 'hodan@example.com']);
        $this->assertDatabaseHas('audit_logs', ['action' => 'account.google_linked']);
    }

    public function test_google_sign_in_on_a_new_phone_moves_progress_into_the_existing_account(): void
    {
        // Phone 1: signed in, has one attempt and a bookmark.
        [$install1, $token1, $user1] = $this->registerDevice();
        $this->withToken($token1)->postJson('/api/v1/auth/google', ['id_token' => 'good:sub-1:', 'install_id' => $install1])->assertOk();
        $owner = User::query()->where('student_id', $user1['student_id'])->firstOrFail();
        $question = Question::factory()->withOptions()->create();
        Attempt::query()->create(['user_id' => $owner->id, 'client_uuid' => (string) Str::uuid(), 'mode' => 'quick10', 'started_at' => now(), 'finished_at' => now(), 'total' => 1, 'correct' => 1]);

        // Phone 2: anonymous, has its own attempt, a bookmark, a shared attempt uuid and a paid Premium grant.
        $this->app['auth']->forgetGuards();
        [$install2, $token2, $user2] = $this->registerDevice();
        $anon = User::query()->where('student_id', $user2['student_id'])->firstOrFail();
        Attempt::query()->create(['user_id' => $anon->id, 'client_uuid' => (string) Str::uuid(), 'mode' => 'quick10', 'started_at' => now(), 'finished_at' => now(), 'total' => 2, 'correct' => 1]);
        Bookmark::query()->create(['user_id' => $anon->id, 'question_id' => $question->id]);
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        app(PremiumService::class)->grant($anon, PremiumPlan::Term, $helper, 'TX-1');

        $response = $this->withToken($token2)->postJson('/api/v1/auth/google', ['id_token' => 'good:sub-1:', 'install_id' => $install2]);

        $response->assertOk()
            ->assertJsonPath('merged', true)
            ->assertJsonPath('user.student_id', $user1['student_id'])
            ->assertJsonPath('user.premium.active', true);

        $this->assertDatabaseMissing('users', ['id' => $anon->id]);
        $this->assertSame(2, Attempt::query()->where('user_id', $owner->id)->count());
        $this->assertSame(1, Bookmark::query()->where('user_id', $owner->id)->count());
        $this->assertSame(2, Device::query()->where('user_id', $owner->id)->count());

        // The old anonymous token is gone; the new one works.
        $this->app['auth']->forgetGuards();
        $this->withToken($token2)->getJson('/api/v1/me')->assertUnauthorized();
        $this->app['auth']->forgetGuards();
        $this->withToken((string) $response->json('token'))->getJson('/api/v1/me')
            ->assertOk()->assertJsonPath('user.student_id', $user1['student_id']);
    }

    public function test_google_sign_in_with_another_google_account_is_refused(): void
    {
        [$installId, $token] = $this->registerDevice();
        $this->withToken($token)->postJson('/api/v1/auth/google', ['id_token' => 'good:sub-1:', 'install_id' => $installId])->assertOk();

        $user = User::query()->where('google_sub', 'sub-1')->firstOrFail();
        $newToken = $user->createToken('t')->plainTextToken;

        $this->app['auth']->forgetGuards();
        $this->withToken($newToken)->postJson('/api/v1/auth/google', ['id_token' => 'good:sub-2:', 'install_id' => $installId])
            ->assertStatus(409)->assertJsonPath('code', 'account_conflict');
    }

    public function test_invalid_google_token_is_refused(): void
    {
        [$installId, $token] = $this->registerDevice();

        $this->withToken($token)->postJson('/api/v1/auth/google', ['id_token' => 'forged', 'install_id' => $installId])
            ->assertUnprocessable()->assertJsonPath('code', 'invalid_google_token');
        $this->assertDatabaseMissing('users', ['google_sub' => 'forged']);
    }

    public function test_google_identity_of_a_staff_member_cannot_be_taken(): void
    {
        $staff = User::factory()->staff(UserRole::SuperAdmin)->create();
        $staff->google_sub = 'staff-sub';
        $staff->save();

        [$installId, $token] = $this->registerDevice();

        $this->withToken($token)->postJson('/api/v1/auth/google', ['id_token' => 'good:staff-sub:', 'install_id' => $installId])
            ->assertStatus(409);
        $this->assertDatabaseHas('users', ['id' => $staff->id, 'role' => 'super_admin']);
    }

    public function test_google_sign_in_can_be_switched_off(): void
    {
        app(FeatureSwitches::class)->set('google_sign_in', false);
        [$installId, $token] = $this->registerDevice();

        $this->withToken($token)->postJson('/api/v1/auth/google', ['id_token' => 'good:sub-1:', 'install_id' => $installId])
            ->assertForbidden()->assertJsonPath('code', 'feature_disabled');
    }

    public function test_logout_revokes_the_token(): void
    {
        [, $token] = $this->registerDevice();

        $this->withToken($token)->postJson('/api/v1/auth/logout')->assertOk();
        $this->app['auth']->forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertUnauthorized();
    }
}
