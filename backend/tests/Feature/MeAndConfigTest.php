<?php

namespace Tests\Feature;

use App\Enums\PremiumPlan;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\FeatureSwitches;
use App\Services\PremiumService;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class MeAndConfigTest extends TestCase
{
    use RefreshDatabase;

    public function test_student_updates_profile(): void
    {
        $user = User::factory()->create(['grade' => 9, 'stream' => null]);
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me', [
            'grade' => 12,
            'stream' => 'social',
            'is_retaker' => true,
            'region' => 'Somali',
            'city' => 'Jigjiga',
            'gender' => 'female',
            'target_score' => 420,
            'reminder_time' => '19:30',
            'nickname' => '  Hodan   the  Star ',
            'avatar_style' => 'thumbs',
            'avatar_seed' => 'abc_123',
            'onboarded' => true,
        ])->assertOk()
            ->assertJsonPath('user.grade', 12)
            ->assertJsonPath('user.stream', 'social')
            ->assertJsonPath('user.is_retaker', true)
            ->assertJsonPath('user.reminder_time', '19:30')
            ->assertJsonPath('user.nickname', 'Hodan the Star')
            ->assertJsonPath('user.onboarded', true);

        $this->assertNotNull($user->refresh()->onboarded_at);
    }

    public function test_profile_validation(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/me', [
            'grade' => 8,
            'stream' => 'arts',
            'gender' => 'other',
            'target_score' => 601,
            'reminder_time' => '25:00',
            'nickname' => 'ab',
            'avatar_style' => 'photo',
            'avatar_seed' => 'has space',
        ])->assertUnprocessable()->assertJsonValidationErrors([
            'grade', 'stream', 'gender', 'target_score', 'reminder_time', 'nickname', 'avatar_style', 'avatar_seed',
        ]);
    }

    public function test_rude_nickname_is_blocked(): void
    {
        Sanctum::actingAs(User::factory()->create());

        $this->patchJson('/api/v1/me', ['nickname' => 'F.u.c.k you'])
            ->assertUnprocessable()->assertJsonValidationErrors('nickname');
    }

    public function test_profile_cannot_change_role_or_student_id(): void
    {
        $user = User::factory()->create();
        $studentId = $user->student_id;
        Sanctum::actingAs($user);

        $this->patchJson('/api/v1/me', ['role' => 'super_admin', 'student_id' => 'ST-AAAAA', 'google_sub' => 'x'])->assertOk();

        $user->refresh();
        $this->assertSame(UserRole::Student, $user->role);
        $this->assertSame($studentId, $user->student_id);
        $this->assertNull($user->google_sub);
    }

    public function test_config_never_contains_prices(): void
    {
        app(Settings::class)->set('contact_whatsapp', '+251 91 234 5678');
        $response = $this->getJson('/api/v1/config')->assertOk();
        $body = strtolower((string) $response->getContent());

        $this->assertStringNotContainsString('price', $body);
        $this->assertStringNotContainsString('birr', $body);
        $this->assertStringNotContainsString('payment', $body);
        $response->assertJsonPath('premium', false)->assertJsonPath('show_ads', true);
    }

    /** Owner decision #40: the app's Premium page shows WhatsApp and Telegram contact buttons. */
    public function test_config_gives_the_premium_contacts_cleaned(): void
    {
        $this->getJson('/api/v1/config')
            ->assertJsonPath('contact.whatsapp', null)
            ->assertJsonPath('contact.telegram', null);

        app(Settings::class)->set('contact_whatsapp', '+251 91 234 5678');
        app(Settings::class)->set('contact_telegram', '@Guul12_Help');
        $this->getJson('/api/v1/config')
            ->assertJsonPath('contact.whatsapp', '251912345678')
            ->assertJsonPath('contact.telegram', 'Guul12_Help');

        app(Settings::class)->set('contact_telegram', 'https://t.me/guul12help');
        $this->getJson('/api/v1/config')->assertJsonPath('contact.telegram', 'guul12help');

        foreach (['abc', 'bad name!', 'https://evil.example/x', str_repeat('a', 33)] as $bad) {
            app(Settings::class)->set('contact_telegram', $bad);
            $this->getJson('/api/v1/config')->assertJsonPath('contact.telegram', null);
        }
    }

    public function test_premium_contact_switch_hides_the_contacts(): void
    {
        app(Settings::class)->set('contact_whatsapp', '+251 91 234 5678');
        app(Settings::class)->set('contact_telegram', 'Guul12_Help');
        app(FeatureSwitches::class)->set('premium_contact', false);

        $this->getJson('/api/v1/config')
            ->assertJsonPath('contact.whatsapp', null)
            ->assertJsonPath('contact.telegram', null)
            ->assertJsonPath('features.premium_contact', false);
    }

    public function test_config_reflects_settings_and_switches(): void
    {
        app(Settings::class)->set('exam_date', '2027-06-29');
        app(FeatureSwitches::class)->set('battles', false);

        $this->getJson('/api/v1/config')
            ->assertJsonPath('settings.exam_date', '2027-06-29')
            ->assertJsonPath('features.battles', false)
            ->assertJsonPath('features.leaderboard', true);
    }

    public function test_premium_student_gets_no_ads(): void
    {
        $student = User::factory()->create();
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        app(PremiumService::class)->grant($student, PremiumPlan::Year, $helper, 'TX-9');
        Sanctum::actingAs($student);

        $this->getJson('/api/v1/config')->assertJsonPath('premium', true)->assertJsonPath('show_ads', false);
    }

    public function test_ads_switch_off_hides_ads_for_everyone(): void
    {
        app(FeatureSwitches::class)->set('ads', false);

        $this->getJson('/api/v1/config')->assertJsonPath('show_ads', false);
    }
}
