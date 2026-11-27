<?php

namespace Tests\Nova;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

/**
 * Bug hunt: staff sign-in to Nova. Passwords cannot be guessed (rate limit), and two-step
 * sign-in (authenticator app) works. Requests are sent as JSON, like Nova's sign-in screen does.
 * Runs only with the licensed Nova package.
 */
class SignInSecurityNovaTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        RateLimiter::clear('boss@example.com|127.0.0.1');
        RateLimiter::clear('127.0.0.1');
        for ($i = 1; $i <= 21; $i++) {
            RateLimiter::clear("guess{$i}@example.com|127.0.0.1");
        }
    }

    private function boss(): User
    {
        return User::factory()->staff(UserRole::SuperAdmin)->create([
            'email' => 'boss@example.com', 'password' => Hash::make('correct horse battery'),
        ]);
    }

    public function test_the_sixth_wrong_password_in_a_minute_is_refused(): void
    {
        $this->boss();
        for ($i = 1; $i <= 5; $i++) {
            $this->postJson('/nova/login', ['email' => 'boss@example.com', 'password' => "guess{$i}"])->assertUnprocessable();
            $this->assertGuest();
        }
        $this->postJson('/nova/login', ['email' => 'boss@example.com', 'password' => 'correct horse battery'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_one_address_cannot_try_many_emails(): void
    {
        $this->boss();
        for ($i = 1; $i <= 20; $i++) {
            $this->postJson('/nova/login', ['email' => "guess{$i}@example.com", 'password' => 'secret'])->assertUnprocessable();
        }
        $this->postJson('/nova/login', ['email' => 'boss@example.com', 'password' => 'correct horse battery'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_with_two_step_sign_in_the_password_alone_is_not_enough(): void
    {
        $boss = $this->boss();
        $boss->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one-1234', 'recovery-two-5678'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->postJson('/nova/login', ['email' => 'boss@example.com', 'password' => 'correct horse battery'])
            ->assertOk()->assertJson(['two_factor' => true]);
        $this->assertGuest();
        $this->get('/nova/dashboards/main')->assertRedirect();

        // Wrong codes are refused, and limited: 5 a minute.
        for ($i = 0; $i < 5; $i++) {
            $this->postJson('/nova/user-security/two-factor-challenge', ['code' => '000000'])->assertUnprocessable();
        }
        $this->postJson('/nova/user-security/two-factor-challenge', ['recovery_code' => 'recovery-one-1234'])->assertStatus(429);
        $this->assertGuest();
    }

    public function test_a_recovery_code_signs_in_once(): void
    {
        $boss = $this->boss();
        $boss->forceFill([
            'two_factor_secret' => encrypt('JBSWY3DPEHPK3PXP'),
            'two_factor_recovery_codes' => encrypt(json_encode(['recovery-one-1234', 'recovery-two-5678'])),
            'two_factor_confirmed_at' => now(),
        ])->save();

        $this->postJson('/nova/login', ['email' => 'boss@example.com', 'password' => 'correct horse battery'])->assertJson(['two_factor' => true]);
        $this->postJson('/nova/user-security/two-factor-challenge', ['recovery_code' => 'recovery-one-1234'])->assertSuccessful();
        $this->assertAuthenticatedAs($boss);
        $this->get('/nova/user-security')->assertOk();
        $this->assertNotContains('recovery-one-1234', $boss->refresh()->recoveryCodes(), 'used once');
    }
}
