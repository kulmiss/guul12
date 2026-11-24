<?php

namespace App\Services\Branding;

use App\Models\AuditLog;
use App\Models\BrandingVersion;
use App\Models\User;
use App\Support\Contrast;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * One branding for the app, the landing page and Nova (DECISIONS #35): logo, colours, fonts and
 * roundness. Sunrise is the default and the fallback. Colours must pass WCAG AA (4.5:1) before they
 * can be saved. The newest saved version is used; the last HISTORY versions are kept for restoring.
 */
final class Branding
{
    public const HISTORY = 10;

    /** Dark-mode background in the app (fixed; dark text is light). */
    public const NIGHT = '#0F1C20';

    /** Font key => [label staff see, Flutter family, CSS family]. All four are bundled. */
    public const FONTS = [
        'nunito' => ['Nunito', 'Nunito', 'Nunito'],
        'noto_sans' => ['Noto Sans', 'NotoSans', 'Noto Sans'],
        'inter' => ['Inter', 'Inter', 'Inter'],
        'poppins' => ['Poppins', 'Poppins', 'Poppins'],
    ];

    /** Roundness => corner radius in pixels for cards and buttons. */
    public const ROUNDNESS = [
        'square' => ['card' => 6, 'button' => 6],
        'soft' => ['card' => 18, 'button' => 14],
        'round' => ['card' => 24, 'button' => 28],
    ];

    public const LOGO_MAX_SIDE = 512;

    public const LOGO_MAX_KB = 2048;

    private const CACHE_KEY = 'branding.current';

    /** @return array<string, array{name: string, colors: array{light: array<string, string>, dark: array<string, string>}}> */
    public static function palettes(): array
    {
        return [
            'sunrise' => ['name' => 'Sunrise', 'colors' => [
                'light' => ['primary' => '#0E5E6F', 'accent' => '#F28C28', 'background' => '#FFF8EE', 'text' => '#1C2B30'],
                'dark' => ['primary' => '#5FC1CF', 'accent' => '#F5A35A'],
            ]],
            'nile' => ['name' => 'Nile', 'colors' => [
                'light' => ['primary' => '#1D4E89', 'accent' => '#E0A100', 'background' => '#F6F8FC', 'text' => '#14213D'],
                'dark' => ['primary' => '#8FB8F0', 'accent' => '#F2C14E'],
            ]],
            'highlands' => ['name' => 'Highlands', 'colors' => [
                'light' => ['primary' => '#2D6A4F', 'accent' => '#E9A23B', 'background' => '#F7F5EE', 'text' => '#1B2621'],
                'dark' => ['primary' => '#74C69D', 'accent' => '#F2BE6C'],
            ]],
            'jacaranda' => ['name' => 'Jacaranda', 'colors' => [
                'light' => ['primary' => '#5B3C88', 'accent' => '#E07A5F', 'background' => '#FBF8FF', 'text' => '#221A2E'],
                'dark' => ['primary' => '#C3A6F0', 'accent' => '#F0A08A'],
            ]],
        ];
    }

    /** @return array<string, mixed> the default branding, the same as the app's built-in theme */
    public static function sunrise(): array
    {
        return [
            'palette' => 'sunrise',
            ...self::palettes()['sunrise']['colors'],
            'heading_font' => 'nunito',
            'body_font' => 'noto_sans',
            'roundness' => 'soft',
            'logo_text' => 'Guul 12',
            'logo_light' => null,
            'logo_dark' => null,
        ];
    }

    /** @return array<string, mixed> */
    public function current(): array
    {
        /** @var array<string, mixed> */
        return Cache::rememberForever(self::CACHE_KEY, function (): array {
            $version = $this->currentVersion();
            $data = $version === null ? self::sunrise() : self::normalise($version->data);

            // Saved data was checked; if it was changed by hand in the database, fall back to Sunrise.
            return $this->problems($data) === [] ? $data : self::sunrise();
        });
    }

    public function currentVersion(): ?BrandingVersion
    {
        return BrandingVersion::query()->latest('id')->first();
    }

    public function forgetCache(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /**
     * Everything that stops saving, in easy words (empty = fine).
     *
     * @param  array<string, mixed>  $data
     * @return list<string>
     */
    public function problems(array $data): array
    {
        $problems = [];
        $colours = ['light.primary' => 'Main colour', 'light.accent' => 'Accent colour', 'light.background' => 'Background',
            'light.text' => 'Text colour', 'dark.primary' => 'Main colour (dark mode)', 'dark.accent' => 'Accent colour (dark mode)'];
        foreach ($colours as $path => $label) {
            $value = data_get($data, $path);
            if (! is_string($value) || preg_match('/^#[0-9A-Fa-f]{6}$/', $value) !== 1) {
                $problems[] = "{$label} must be a colour like #0E5E6F.";
            }
        }
        foreach (['heading_font' => 'Heading font', 'body_font' => 'Text font'] as $key => $label) {
            if (! is_string($data[$key] ?? null) || ! isset(self::FONTS[$data[$key]])) {
                $problems[] = "{$label} must be one of: ".implode(', ', array_column(self::FONTS, 0)).'.';
            }
        }
        if (! is_string($data['roundness'] ?? null) || ! isset(self::ROUNDNESS[$data['roundness']])) {
            $problems[] = 'Roundness must be square, soft or round.';
        }
        $text = $data['logo_text'] ?? null;
        if (! is_string($text) || trim($text) === '' || mb_strlen($text) > 30 || preg_match('/[<>]/', $text) === 1) {
            $problems[] = 'Logo text must be 1–30 characters, without < or >.';
        }
        foreach (['logo_light', 'logo_dark'] as $key) {
            $logo = $data[$key] ?? null;
            if ($logo !== null && (! is_string($logo) || preg_match('#^branding/logo-(light|dark)-[0-9a-f]{16}\.png$#', $logo) !== 1)) {
                $problems[] = 'Upload the logo with the upload button.';
            }
        }
        if ($problems !== []) {
            return $problems;
        }

        foreach ($this->contrastReport($data) as $row) {
            if (! $row['ok']) {
                $problems[] = sprintf('%s: %.2f:1, needs %.1f:1. Choose a darker or lighter colour.', $row['label'], $row['ratio'], $row['needed']);
            }
        }

        return $problems;
    }

    /**
     * Every colour pair students read, with its WCAG contrast. All need 4.5:1 (normal text, AA).
     *
     * @param  array<string, mixed>  $data
     * @return list<array{label: string, ratio: float, needed: float, ok: bool}>
     */
    public function contrastReport(array $data): array
    {
        $l = self::strings($data['light'] ?? []);
        $d = self::strings($data['dark'] ?? [], dark: true);
        $pairs = [
            'Text on the background' => [$l['text'], $l['background']],
            'Main colour on the background (links, titles)' => [$l['primary'], $l['background']],
            'Button text on the main colour' => [Contrast::bestOn($l['primary'], $l['text']), $l['primary']],
            'Text on the accent colour' => [Contrast::bestOn($l['accent'], $l['text']), $l['accent']],
            'Main colour on the dark background' => [$d['primary'], self::NIGHT],
            'Accent colour on the dark background' => [$d['accent'], self::NIGHT],
        ];
        $rows = [];
        foreach ($pairs as $label => [$fg, $bg]) {
            $ratio = Contrast::ratio($fg, $bg);
            // Shown rounded down, so a ratio never looks better than it is.
            $rows[] = ['label' => $label, 'ratio' => floor($ratio * 100 + 1e-9) / 100, 'needed' => Contrast::AA_TEXT, 'ok' => $ratio >= Contrast::AA_TEXT];
        }

        return $rows;
    }

    /**
     * @param  array<string, mixed>  $data
     *
     * @throws BrandingInvalid
     */
    public function save(array $data, User $actor, ?string $note = null, string $action = 'branding.saved'): BrandingVersion
    {
        $data = self::normalise($data);
        $problems = $this->problems($data);
        if ($problems !== []) {
            throw new BrandingInvalid($problems);
        }

        return DB::transaction(function () use ($data, $actor, $note, $action): BrandingVersion {
            $before = $this->current();
            $version = BrandingVersion::query()->create(['data' => $data, 'note' => $note, 'created_by' => $actor->id]);
            BrandingVersion::query()->whereNotIn('id', BrandingVersion::query()->latest('id')->limit(self::HISTORY)->pluck('id'))->delete();
            AuditLog::change($actor, $action, $version, self::flat($before), self::flat($data), $note !== null ? ['note' => $note] : []);
            $this->forgetCache();

            return $version;
        });
    }

    /** Saves an older version again as the newest (history is kept). */
    public function restore(BrandingVersion $version, User $actor): BrandingVersion
    {
        return $this->save($version->data, $actor, 'Restored version #'.$version->id, 'branding.restored');
    }

    public function reset(User $actor): BrandingVersion
    {
        return $this->save(self::sunrise(), $actor, 'Back to Sunrise', 'branding.reset');
    }

    /**
     * Stores a logo as a PNG whose longest side is at most LOGO_MAX_SIDE pixels. Only PNG, JPEG and
     * WebP pictures (SVG can carry scripts). Returns the path on the public disk.
     *
     * @throws BrandingInvalid
     */
    public function storeLogo(UploadedFile $file, string $variant): string
    {
        if (! in_array($variant, ['light', 'dark'], true)) {
            throw new \InvalidArgumentException('The logo is for light or dark mode.');
        }
        if ($file->getSize() > self::LOGO_MAX_KB * 1024) {
            throw new BrandingInvalid(['The logo file is too big (at most 2 MB).']);
        }
        $bytes = (string) file_get_contents($file->getRealPath());
        $info = @getimagesizefromstring($bytes);
        if ($info === false || ! in_array($info[2], [IMAGETYPE_PNG, IMAGETYPE_JPEG, IMAGETYPE_WEBP], true)) {
            throw new BrandingInvalid(['The logo must be a PNG, JPG or WebP picture.']);
        }
        $source = @imagecreatefromstring($bytes);
        if ($source === false) {
            throw new BrandingInvalid(['The logo picture could not be read.']);
        }
        [$width, $height] = [imagesx($source), imagesy($source)];
        $scale = min(1, self::LOGO_MAX_SIDE / max($width, $height));
        [$w, $h] = [max(1, (int) round($width * $scale)), max(1, (int) round($height * $scale))];
        $target = imagecreatetruecolor($w, $h);
        imagealphablending($target, false);
        imagesavealpha($target, true);
        imagefill($target, 0, 0, (int) imagecolorallocatealpha($target, 0, 0, 0, 127));
        imagecopyresampled($target, $source, 0, 0, 0, 0, $w, $h, $width, $height);
        ob_start();
        imagepng($target, null, 9);
        $png = (string) ob_get_clean();

        $path = 'branding/logo-'.$variant.'-'.substr(hash('sha256', $png), 0, 16).'.png';
        Storage::disk('public')->put($path, $png);

        return $path;
    }

    /** @return array<string, mixed> what the app needs (config API) */
    public function forApp(): array
    {
        $b = $this->current();
        $light = self::strings($b['light']);
        $dark = self::strings($b['dark'], dark: true);

        return [
            'version' => $this->currentVersion()->id ?? 0,
            'palette' => $b['palette'],
            'light' => $light,
            'dark' => $dark,
            'on_primary' => Contrast::bestOn($light['primary'], $light['text']),
            'on_accent' => Contrast::bestOn($light['accent'], $light['text']),
            'heading_font' => $b['heading_font'],
            'body_font' => $b['body_font'],
            'roundness' => $b['roundness'],
            'radius' => self::ROUNDNESS[(string) $b['roundness']],
            'logo_text' => $b['logo_text'],
            'logo_light_url' => is_string($b['logo_light']) ? asset('storage/'.$b['logo_light']) : null,
            'logo_dark_url' => is_string($b['logo_dark']) ? asset('storage/'.$b['logo_dark']) : null,
        ];
    }

    /**
     * What the landing page shows: colours as CSS variables, the fonts to load, the logo.
     *
     * @return array{css: string, theme_color: string, logo_text: string, logo: array{url: string, width: int, height: int}|null, fonts: array{heading: string, body: string}}
     */
    public function forLanding(): array
    {
        $b = $this->current();
        $logo = null;
        if (is_string($b['logo_light']) && Storage::disk('public')->exists($b['logo_light'])) {
            $size = @getimagesizefromstring((string) Storage::disk('public')->get($b['logo_light']));
            if ($size !== false) {
                // Shown 32 px high; the width keeps the logo's shape.
                $logo = ['url' => asset('storage/'.$b['logo_light']), 'height' => 32, 'width' => max(1, (int) round(32 * $size[0] / max(1, $size[1])))];
            }
        }

        return [
            'css' => $this->cssVariables(),
            'theme_color' => self::strings($b['light'])['primary'],
            'logo_text' => (string) $b['logo_text'],
            'logo' => $logo,
            'fonts' => ['heading' => (string) $b['heading_font'], 'body' => (string) $b['body_font']],
        ];
    }

    /** CSS custom properties for the landing page. */
    public function cssVariables(): string
    {
        $b = $this->current();
        $l = self::strings($b['light']);
        $radius = self::ROUNDNESS[(string) $b['roundness']];

        return implode(' ', [
            "--teal: {$l['primary']};",
            '--orange: '.Contrast::readableOn($l['accent'], $l['background']).';',
            "--cream: {$l['background']};",
            "--ink: {$l['text']};",
            '--on-teal: '.Contrast::bestOn($l['primary'], $l['text']).';',
            "--radius-card: {$radius['card']}px;",
            "--radius-button: {$radius['button']}px;",
            "--font-heading: '".self::FONTS[(string) $b['heading_font']][2]."';",
            "--font-body: '".self::FONTS[(string) $b['body_font']][2]."';",
        ]);
    }

    /**
     * Nova's brand colour scale ("R, G, B" strings, 50 lightest … 950 darkest; 500 is the main colour).
     *
     * @return array<int, string>
     */
    public function novaColors(): array
    {
        $primary = self::strings($this->current()['light'])['primary'];
        $steps = [50 => 0.95, 100 => 0.88, 200 => 0.74, 300 => 0.56, 400 => 0.3, 500 => 0.0,
            600 => -0.15, 700 => -0.3, 800 => -0.45, 900 => -0.6, 950 => -0.75];
        $out = [];
        foreach ($steps as $key => $amount) {
            $colour = $amount >= 0 ? Contrast::mix($primary, '#FFFFFF', $amount) : Contrast::mix($primary, '#000000', -$amount);
            $out[$key] = implode(', ', Contrast::rgb($colour));
        }

        return $out;
    }

    /**
     * Nova's logo (Nova shows SVG only): the uploaded logo inside an SVG, or the logo text.
     */
    public function novaLogoSvg(): string
    {
        $b = $this->current();
        if (is_string($b['logo_light']) && Storage::disk('public')->exists($b['logo_light'])) {
            $png = (string) Storage::disk('public')->get($b['logo_light']);
            $size = @getimagesizefromstring($png);
            if ($size !== false) {
                $width = max(1, (int) round(32 * $size[0] / max(1, $size[1])));

                return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' 32" height="32" role="img" aria-label="'
                    .htmlspecialchars((string) $b['logo_text'], ENT_QUOTES | ENT_XML1).'"><image width="'.$width.'" height="32" href="data:image/png;base64,'
                    .base64_encode($png).'"/></svg>';
            }
        }
        $text = htmlspecialchars((string) $b['logo_text'], ENT_QUOTES | ENT_XML1);
        $width = 16 + 12 * mb_strlen((string) $b['logo_text']);

        return '<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 '.$width.' 32" height="32" role="img" aria-label="'.$text.'">'
            .'<text x="0" y="24" font-family="'.self::FONTS[(string) $b['heading_font']][2].', sans-serif" font-size="22" font-weight="800" fill="currentColor">'
            .$text.'</text></svg>';
    }

    /**
     * Fills missing keys from Sunrise and writes colours in capitals.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    public static function normalise(array $data): array
    {
        $base = self::sunrise();
        $out = [];
        foreach ($base as $key => $default) {
            $value = $data[$key] ?? $default;
            if (is_array($default)) {
                $value = is_array($value) ? array_map(fn (mixed $c): mixed => is_string($c) ? strtoupper($c) : $c, array_intersect_key($value + $default, $default)) : $default;
            }
            $out[$key] = is_string($value) && in_array($key, ['logo_text'], true) ? trim($value) : $value;
        }

        return $out;
    }

    /**
     * @param  mixed  $colours  a light (4 colours) or dark (2 colours) map
     * @return array<string, string>
     */
    private static function strings(mixed $colours, bool $dark = false): array
    {
        $out = [];
        foreach ($dark ? ['primary', 'accent'] : ['primary', 'accent', 'background', 'text'] as $key) {
            $value = is_array($colours) ? ($colours[$key] ?? null) : null;
            $out[$key] = is_string($value) ? $value : '#000000';
        }

        return $out;
    }

    /**
     * Branding as flat keys for the change log ("light.primary" => "#0E5E6F").
     *
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private static function flat(array $data): array
    {
        $out = [];
        foreach ($data as $key => $value) {
            if (is_array($value)) {
                foreach ($value as $sub => $v) {
                    $out["{$key}.{$sub}"] = $v;
                }
            } else {
                $out[$key] = $value;
            }
        }

        return $out;
    }
}
