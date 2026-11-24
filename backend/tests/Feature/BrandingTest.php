<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\BrandingVersion;
use App\Models\User;
use App\Services\Branding\Branding;
use App\Services\Branding\BrandingInvalid;
use App\Support\Contrast;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Branding: Sunrise by default, palettes, WCAG AA contrast that blocks saving, 10 versions with
 * restore and reset (all logged), logo upload with resizing, and the same branding for the app
 * (config API), the landing page and Nova.
 */
class BrandingTest extends TestCase
{
    use RefreshDatabase;

    private Branding $branding;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();
        $this->branding = app(Branding::class);
        $this->admin = User::factory()->staff(UserRole::SuperAdmin)->create();
    }

    /** @return array<string, mixed> */
    private function nile(): array
    {
        return [...Branding::sunrise(), 'palette' => 'nile', ...Branding::palettes()['nile']['colors'], 'heading_font' => 'poppins', 'body_font' => 'inter', 'roundness' => 'round', 'logo_text' => 'Guul 12 Nile'];
    }

    public function test_sunrise_is_the_default_and_matches_the_app(): void
    {
        $current = $this->branding->current();
        $this->assertSame('sunrise', $current['palette']);
        $this->assertSame(['primary' => '#0E5E6F', 'accent' => '#F28C28', 'background' => '#FFF8EE', 'text' => '#1C2B30'], $current['light']);
        $this->assertSame(['primary' => '#5FC1CF', 'accent' => '#F5A35A'], $current['dark']);
        $this->assertSame(['nunito', 'noto_sans', 'soft', 'Guul 12'], [$current['heading_font'], $current['body_font'], $current['roundness'], $current['logo_text']]);
        $this->assertNull($this->branding->currentVersion());
    }

    public function test_contrast_follows_wcag(): void
    {
        $this->assertEqualsWithDelta(21.0, Contrast::ratio('#000000', '#FFFFFF'), 0.01);
        $this->assertEqualsWithDelta(1.0, Contrast::ratio('#777777', '#777777'), 0.001);
        $this->assertEqualsWithDelta(4.48, Contrast::ratio('#777777', '#FFFFFF'), 0.01, 'the classic just-too-light grey');
        $this->assertSame('#FFFFFF', Contrast::bestOn('#0E5E6F', '#1C2B30'));
        $this->assertSame('#1C2B30', Contrast::bestOn('#F28C28', '#1C2B30'));
        // Lighter until readable on a dark colour; white when nothing lighter is enough.
        $light = Contrast::lighterOn('#F28C28', '#09404B');
        $this->assertGreaterThanOrEqual(4.5, Contrast::ratio($light, '#09404B'));
        $this->assertSame('#FFC27A', Contrast::lighterOn('#FFC27A', '#000000'), 'already readable: unchanged');
        $this->assertSame('#FFFFFF', Contrast::lighterOn('#F28C28', '#EEEEEE'));
    }

    public function test_every_palette_gives_the_website_readable_colour_pairs(): void
    {
        foreach (array_keys(Branding::palettes()) as $key) {
            $this->branding->save([...Branding::sunrise(), 'palette' => $key, ...Branding::palettes()[$key]['colors']], $this->admin);
            $css = $this->branding->cssVariables();
            preg_match_all('/--([a-z-]+): (#[0-9A-F]{6});/', $css, $m);
            $v = array_combine($m[1], $m[2]);
            // Every text/background pair the website uses.
            foreach ([['ink', 'cream'], ['teal', 'cream'], ['orange', 'cream'], ['on-teal', 'teal'], ['on-accent', 'accent'],
                ['on-deep', 'deep'], ['on-deep', 'deep-light'], ['highlight', 'deep'], ['highlight', 'deep-light'],
                ['teal', 'tint'], ['ink', 'accent-tint']] as [$fg, $bg]) {
                $this->assertGreaterThanOrEqual(4.5, Contrast::ratio($v[$fg], $v[$bg]), "{$key}: {$fg} on {$bg}");
            }
        }
    }

    public function test_every_palette_passes_the_checks(): void
    {
        $this->assertSame(['sunrise', 'nile', 'highlands', 'jacaranda'], array_keys(Branding::palettes()));
        foreach (Branding::palettes() as $key => $palette) {
            $data = [...Branding::sunrise(), 'palette' => $key, ...$palette['colors']];
            $this->assertSame([], $this->branding->problems($data), $key);
            foreach ($this->branding->contrastReport($data) as $row) {
                $this->assertGreaterThanOrEqual(4.5, $row['ratio'], "{$key}: {$row['label']}");
            }
        }
    }

    public function test_low_contrast_or_bad_values_block_saving(): void
    {
        $bad = [...Branding::sunrise(), 'palette' => 'custom', 'light' => ['primary' => '#0E5E6F', 'accent' => '#F28C28', 'background' => '#FFFFFF', 'text' => '#AAAAAA']];
        try {
            $this->branding->save($bad, $this->admin);
            $this->fail('low contrast was saved');
        } catch (BrandingInvalid $e) {
            $this->assertStringContainsString('Text on the background: 2.32:1, needs 4.5:1', implode("\n", $e->problems));
        }

        $wrong = [...Branding::sunrise(), 'heading_font' => 'comic_sans', 'roundness' => 'blob', 'logo_text' => '<b>x</b>',
            'light' => ['primary' => 'teal', 'accent' => '#F28C28', 'background' => '#FFF8EE', 'text' => '#1C2B30']];
        $problems = $this->branding->problems($wrong);
        foreach (['Heading font', 'Roundness', 'Logo text', 'Main colour'] as $word) {
            $this->assertNotEmpty(array_filter($problems, fn (string $p): bool => str_starts_with($p, $word)), $word);
        }
        $this->assertSame(0, BrandingVersion::query()->count());
    }

    public function test_save_restore_reset_keep_ten_versions_and_are_logged(): void
    {
        $first = $this->branding->save($this->nile(), $this->admin, 'Try Nile');
        $this->assertSame('nile', $this->branding->current()['palette']);
        $log = AuditLog::query()->where('action', 'branding.saved')->firstOrFail();
        $this->assertSame('sunrise', $log->data['old']['palette'] ?? null);
        $this->assertSame('nile', $log->data['new']['palette'] ?? null);
        $this->assertArrayNotHasKey('dark', $log->data['old'] ?? [], 'only what changed is logged');

        for ($i = 0; $i < 11; $i++) {
            $this->branding->save([...$this->nile(), 'logo_text' => "Guul 12 v{$i}"], $this->admin);
        }
        $this->assertSame(Branding::HISTORY, BrandingVersion::query()->count());
        $this->assertFalse(BrandingVersion::query()->whereKey($first->id)->exists(), 'the oldest version is dropped');

        $older = BrandingVersion::query()->orderBy('id')->firstOrFail();
        $restored = $this->branding->restore($older, $this->admin);
        $this->assertSame($older->data['logo_text'], $this->branding->current()['logo_text']);
        $this->assertNotSame($older->id, $restored->id, 'restoring adds a new version; history stays');
        $this->assertSame(1, AuditLog::query()->where('action', 'branding.restored')->count());

        $this->branding->reset($this->admin);
        $this->assertSame('sunrise', $this->branding->current()['palette']);
        $this->assertSame(1, AuditLog::query()->where('action', 'branding.reset')->count());
        $this->assertSame(Branding::HISTORY, BrandingVersion::query()->count());
    }

    public function test_logo_upload_is_resized_to_png_and_refuses_unsafe_files(): void
    {
        Storage::fake('public');
        $path = $this->branding->storeLogo(UploadedFile::fake()->image('logo.jpg', 2000, 1000), 'light');
        $this->assertMatchesRegularExpression('#^branding/logo-light-[0-9a-f]{16}\.png$#', $path);
        Storage::disk('public')->assertExists($path);
        $size = getimagesizefromstring((string) Storage::disk('public')->get($path));
        $this->assertIsArray($size);
        $this->assertSame([512, 256], [$size[0], $size[1]], 'longest side 512 px, same shape');

        $small = $this->branding->storeLogo(UploadedFile::fake()->image('small.png', 120, 60), 'dark');
        $smallSize = getimagesizefromstring((string) Storage::disk('public')->get($small));
        $this->assertIsArray($smallSize);
        $this->assertSame(120, $smallSize[0], 'small logos are not enlarged');

        foreach ([
            UploadedFile::fake()->createWithContent('logo.svg', '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>'),
            UploadedFile::fake()->createWithContent('logo.png', 'not really a picture'),
            UploadedFile::fake()->image('huge.png', 100, 100)->size(3000),
        ] as $bad) {
            try {
                $this->branding->storeLogo($bad, 'light');
                $this->fail('an unsafe or broken file was stored: '.$bad->getClientOriginalName());
            } catch (BrandingInvalid $e) {
                $this->assertNotEmpty($e->problems);
            }
        }
        $this->expectException(\InvalidArgumentException::class);
        $this->branding->storeLogo(UploadedFile::fake()->image('x.png', 10, 10), 'sideways');
    }

    public function test_the_app_gets_the_branding_in_the_config_and_changes_show_at_once(): void
    {
        $token = User::factory()->create()->createToken('app')->plainTextToken;
        $sunrise = $this->withToken($token)->getJson('/api/v1/config')->assertOk()->json('branding');
        $this->assertSame(0, $sunrise['version']);
        $this->assertSame('#0E5E6F', $sunrise['light']['primary']);
        $this->assertSame('#FFFFFF', $sunrise['on_primary']);
        $this->assertSame(['card' => 18, 'button' => 14], $sunrise['radius']);
        $this->assertNull($sunrise['logo_light_url']);

        Storage::fake('public');
        $logo = $this->branding->storeLogo(UploadedFile::fake()->image('l.png', 200, 100), 'light');
        $version = $this->branding->save([...$this->nile(), 'logo_light' => $logo], $this->admin);
        $nile = $this->withToken($token)->getJson('/api/v1/config')->assertOk()->json('branding');
        $this->assertSame($version->id, $nile['version']);
        $this->assertSame('#1D4E89', $nile['light']['primary']);
        $this->assertSame(['poppins', 'inter', 'round'], [$nile['heading_font'], $nile['body_font'], $nile['roundness']]);
        $this->assertSame(asset('storage/'.$logo), $nile['logo_light_url']);
    }

    public function test_landing_and_nova_use_the_same_colours(): void
    {
        $this->branding->save($this->nile(), $this->admin);

        $css = $this->branding->cssVariables();
        $this->assertStringContainsString('--teal: #1D4E89;', $css);
        $this->assertStringContainsString('--cream: #F6F8FC;', $css);
        $this->assertStringContainsString('--ink: #14213D;', $css);
        $this->assertStringContainsString('--radius-card: 24px;', $css);
        // The accent used as text on the page is darkened until it is readable.
        $this->assertSame(1, preg_match('/--orange: (#[0-9A-F]{6});/', $css, $m));
        $this->assertGreaterThanOrEqual(4.5, Contrast::ratio($m[1] ?? '', '#F6F8FC'));

        $page = $this->get('/')->assertOk();
        $page->assertSee('--teal: #1D4E89;', false)->assertSee('Guul 12 Nile')->assertSee('<meta name="theme-color" content="#1D4E89">', false);

        $nova = $this->branding->novaColors();
        $this->assertSame([50, 100, 200, 300, 400, 500, 600, 700, 800, 900, 950], array_keys($nova));
        $this->assertSame('29, 78, 137', $nova[500] ?? null, 'the main colour itself, as Nova wants it');
    }
}
