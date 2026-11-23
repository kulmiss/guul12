<?php

namespace Tests\Nova;

use App\Enums\UserRole;
use App\Models\BrandingVersion;
use App\Models\User;
use App\Services\Branding\Branding;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Nova → Settings → Branding: the page, saving (blocked by low contrast), logo upload, restore,
 * reset, super admins only, and Nova's own colours and logo following the branding.
 * Runs only where the licensed Nova package is installed (php artisan test --testsuite=Nova).
 */
class BrandingNovaTest extends TestCase
{
    use RefreshDatabase;
    use ResetsNovaPageData;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->admin = User::factory()->staff(UserRole::SuperAdmin)->create();
    }

    /** @return array<string, mixed> the page data the browser gets */
    private function page(User $as): array
    {
        $html = (string) $this->actingAs($as)->get('/nova/branding')->assertOk()->getContent();
        $this->assertSame(1, preg_match('/data-page="([^"]+)"/', $html, $m), 'an Inertia page');
        $page = json_decode(html_entity_decode($m[1] ?? ''), true);
        $this->assertIsArray($page);

        return $page;
    }

    /** @return array<string, mixed> */
    private function nile(): array
    {
        return [...Branding::sunrise(), 'palette' => 'nile', ...Branding::palettes()['nile']['colors'], 'logo_text' => 'Guul 12 & "Co"'];
    }

    public function test_the_page_shows_the_branding_palettes_and_history(): void
    {
        $page = $this->page($this->admin);
        $this->assertSame('GuulBranding', $page['component']);
        $props = $page['props'];
        $this->assertSame('sunrise', $props['current']['palette']);
        $this->assertSame(['sunrise', 'nile', 'highlands', 'jacaranda'], array_keys($props['palettes']));
        $this->assertSame(['nunito', 'noto_sans', 'inter', 'poppins'], array_column($props['fonts'], 'key'));
        $this->assertSame(Branding::NIGHT, $props['night']);
        $this->assertSame([], $props['history']);
        $this->assertStringContainsString('launcher icon', $props['notice'], 'the page says what cannot change');
    }

    public function test_save_upload_restore_and_reset(): void
    {
        Storage::fake('public');
        $logo = $this->actingAs($this->admin)->post('/nova/branding/api/logo', ['variant' => 'light', 'file' => UploadedFile::fake()->image('logo.png', 600, 300)])
            ->assertOk()->json();
        $this->assertMatchesRegularExpression('#^branding/logo-light-#', $logo['path']);

        $this->actingAs($this->admin)->postJson('/nova/branding/api/save', ['data' => [...$this->nile(), 'logo_light' => $logo['path']]])->assertOk();
        $this->assertSame('nile', app(Branding::class)->current()['palette']);

        $bad = [...$this->nile(), 'light' => [...Branding::palettes()['nile']['colors']['light'], 'text' => '#DDDDDD']];
        $this->actingAs($this->admin)->postJson('/nova/branding/api/save', ['data' => $bad])->assertUnprocessable()
            ->assertJsonFragment(['problems' => [
                'Text on the background: 1.27:1, needs 4.5:1. Choose a darker or lighter colour.',
                'Text on the accent colour: 2.26:1, needs 4.5:1. Choose a darker or lighter colour.',
            ]]);

        $first = BrandingVersion::query()->firstOrFail();
        app(Branding::class)->reset($this->admin);
        $this->actingAs($this->admin)->postJson("/nova/branding/api/restore/{$first->id}")->assertOk();
        $this->assertSame('nile', app(Branding::class)->current()['palette']);
        $this->actingAs($this->admin)->postJson('/nova/branding/api/reset')->assertOk();
        $this->assertSame('sunrise', app(Branding::class)->current()['palette']);
        $this->assertCount(4, $this->page($this->admin)['props']['history']);
    }

    public function test_super_admins_only(): void
    {
        foreach ([UserRole::PaymentsHelper, UserRole::ContentChecker] as $role) {
            $staff = User::factory()->staff($role)->create();
            $this->actingAs($staff)->get('/nova/branding')->assertForbidden();
            $this->actingAs($staff)->postJson('/nova/branding/api/save', ['data' => $this->nile()])->assertForbidden();
            $this->actingAs($staff)->postJson('/nova/branding/api/reset')->assertForbidden();
            $this->actingAs($staff)->post('/nova/branding/api/logo', ['variant' => 'light', 'file' => UploadedFile::fake()->image('l.png', 10, 10)])->assertForbidden();
        }
        $this->actingAs(User::factory()->create())->get('/nova/branding')->assertForbidden();
        $this->assertSame(0, BrandingVersion::query()->count());
    }

    public function test_nova_uses_the_brand_colours_and_logo_text(): void
    {
        app(Branding::class)->save($this->nile(), $this->admin);
        $html = (string) $this->actingAs($this->admin)->get('/nova/dashboards/main')->assertOk()->getContent();
        $this->assertStringContainsString('--colors-primary-500: 29, 78, 137;', html_entity_decode($html));

        $page = json_decode(html_entity_decode((string) preg_replace('/^.*data-page="([^"]+)".*$/s', '$1', $html)), true);
        $this->assertIsArray($page);
        $logo = (string) ($page['props']['novaConfig']['logo'] ?? '');
        $this->assertStringContainsString('Guul 12 &amp; &quot;Co&quot;', $logo, 'the text is escaped inside the SVG');
        $this->assertStringNotContainsString('& "Co"', $logo);
    }

    public function test_the_menu_has_branding_for_super_admins(): void
    {
        $html = (string) $this->actingAs($this->admin)->get('/nova/dashboards/main')->getContent();
        $this->assertStringContainsString('Branding', html_entity_decode($html));
        $helper = User::factory()->staff(UserRole::PaymentsHelper)->create();
        $this->assertStringNotContainsString('/branding', html_entity_decode((string) $this->actingAs($helper)->get('/nova/dashboards/main')->getContent()));
    }
}
