<?php

namespace App\Nova\Branding;

use App\Models\BrandingVersion;
use App\Models\User;
use App\Services\Branding\Branding;
use App\Services\Branding\BrandingInvalid;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Nova → Settings → Branding (super admins): the page and its save, logo, restore and reset calls.
 */
class BrandingController
{
    public const NOTICE = 'Saved branding reaches the app at its next start (it keeps the last one offline), the landing page and Nova at once. The app launcher icon and the splash screen are part of the app file: they change only with a new app version.';

    public function __construct(private readonly Branding $branding) {}

    public function show(Request $request): Response
    {
        $this->mustManage($request);

        return Inertia::render('GuulBranding', [
            'current' => $this->withUrls($this->branding->current()),
            'palettes' => Branding::palettes(),
            'fonts' => array_map(fn (string $key): array => ['key' => $key, 'label' => Branding::FONTS[$key][0], 'css' => Branding::FONTS[$key][2]], array_keys(Branding::FONTS)),
            'roundness' => array_map(fn (string $key): array => ['key' => $key, ...Branding::ROUNDNESS[$key]], array_keys(Branding::ROUNDNESS)),
            'night' => Branding::NIGHT,
            'needed' => 4.5,
            'history' => $this->history(),
            'notice' => self::NOTICE,
        ]);
    }

    public function save(Request $request): JsonResponse
    {
        $actor = $this->mustManage($request);
        $data = $request->input('data');
        try {
            $version = $this->branding->save(is_array($data) ? $data : [], $actor, self::note($request));
        } catch (BrandingInvalid $e) {
            return response()->json(['problems' => $e->problems], 422);
        }

        return $this->saved($version, 'Branding saved.');
    }

    public function logo(Request $request): JsonResponse
    {
        $this->mustManage($request);
        $file = $request->file('file');
        $variant = (string) $request->input('variant');
        if (! $file instanceof UploadedFile || ! in_array($variant, ['light', 'dark'], true)) {
            return response()->json(['problems' => ['Choose a picture and whether it is for light or dark mode.']], 422);
        }
        try {
            $path = $this->branding->storeLogo($file, $variant);
        } catch (BrandingInvalid $e) {
            return response()->json(['problems' => $e->problems], 422);
        }

        return response()->json(['path' => $path, 'url' => Storage::disk('public')->url($path)]);
    }

    public function restore(Request $request, BrandingVersion $version): JsonResponse
    {
        $actor = $this->mustManage($request);
        try {
            $restored = $this->branding->restore($version, $actor);
        } catch (BrandingInvalid $e) {
            return response()->json(['problems' => $e->problems], 422);
        }

        return $this->saved($restored, "Version #{$version->id} is in use again.");
    }

    public function reset(Request $request): JsonResponse
    {
        return $this->saved($this->branding->reset($this->mustManage($request)), 'Back to Sunrise.');
    }

    private function mustManage(Request $request): User
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('manage-settings'), 403);

        return $user;
    }

    private function saved(BrandingVersion $version, string $message): JsonResponse
    {
        return response()->json([
            'message' => $message,
            'version' => $version->id,
            'current' => $this->withUrls($this->branding->current()),
            'history' => $this->history(),
        ]);
    }

    /** @return list<array<string, mixed>> */
    private function history(): array
    {
        $latest = $this->branding->currentVersion()?->id;

        return array_values(BrandingVersion::query()->with('creator')->latest('id')->limit(Branding::HISTORY)->get()
            ->map(fn (BrandingVersion $v): array => [
                'id' => $v->id,
                'at' => $v->created_at->setTimezone('Africa/Addis_Ababa')->format('j M Y, H:i'),
                'by' => $v->creator->name ?? '—',
                'note' => $v->note,
                'palette' => $v->data['palette'] ?? 'custom',
                'logo_text' => $v->data['logo_text'] ?? '',
                'current' => $v->id === $latest,
            ])->all());
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function withUrls(array $data): array
    {
        foreach (['logo_light', 'logo_dark'] as $key) {
            $data[$key.'_url'] = is_string($data[$key]) ? Storage::disk('public')->url($data[$key]) : null;
        }

        return $data;
    }

    private static function note(Request $request): ?string
    {
        $note = $request->input('note');

        return is_string($note) && trim($note) !== '' ? mb_substr(trim($note), 0, 255) : null;
    }
}
