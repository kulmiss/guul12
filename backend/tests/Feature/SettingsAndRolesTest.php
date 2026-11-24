<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\FeatureSwitches;
use App\Services\Settings;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Gate;
use Tests\TestCase;

class SettingsAndRolesTest extends TestCase
{
    use RefreshDatabase;

    public function test_settings_fall_back_to_defaults(): void
    {
        $settings = app(Settings::class);

        $this->assertSame(250, $settings->get('premium_price_term_birr'));
        $this->assertNull($settings->get('exam_date'));
    }

    public function test_settings_are_saved(): void
    {
        $settings = app(Settings::class);
        $settings->set('premium_price_year_birr', 650);

        $this->assertSame(650, app(Settings::class)->get('premium_price_year_birr'));
        $this->assertDatabaseHas('app_settings', ['key' => 'premium_price_year_birr']);
    }

    public function test_unknown_setting_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(Settings::class)->set('premuim_price', 1);
    }

    public function test_public_settings_never_include_prices(): void
    {
        foreach (array_keys(app(Settings::class)->publicValues()) as $key) {
            $this->assertStringNotContainsString('price', $key);
        }
        $this->assertNotContains('premium_price_term_birr', Settings::publicKeys());
    }

    public function test_feature_switches(): void
    {
        $switches = app(FeatureSwitches::class);

        $this->assertTrue($switches->enabled('battles'));
        $switches->set('battles', false);
        $this->assertFalse(app(FeatureSwitches::class)->enabled('battles'));
        $this->assertCount(count(FeatureSwitches::defaults()), $switches->all());
    }

    public function test_unknown_feature_switch_is_refused(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(FeatureSwitches::class)->enabled('nope');
    }

    public function test_role_permissions(): void
    {
        $admin = User::factory()->staff(UserRole::SuperAdmin)->create();
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $checker = User::factory()->staff(UserRole::ContentChecker)->create();
        $student = User::factory()->create();

        $matrix = [
            'manage-premium' => [true, true, false, false],
            'manage-content' => [true, false, true, false],
            'moderate' => [true, false, true, false],
            'manage-settings' => [true, false, false, false],
            'view-students' => [true, true, false, false],
        ];

        foreach ($matrix as $ability => [$a, $h, $c, $s]) {
            $this->assertSame($a, Gate::forUser($admin)->allows($ability), "admin {$ability}");
            $this->assertSame($h, Gate::forUser($helper)->allows($ability), "helper {$ability}");
            $this->assertSame($c, Gate::forUser($checker)->allows($ability), "checker {$ability}");
            $this->assertSame($s, Gate::forUser($student)->allows($ability), "student {$ability}");
        }
    }
}
