<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Chapter;
use App\Models\ContentChange;
use App\Models\User;
use App\Services\ContentPayload;
use App\Services\PremiumService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Offline-first content delivery.
 *
 * - /catalog: subjects, units, chapter headers (small, free).
 * - /chapters/{id}: one chapter with everything to study it offline (free).
 * - /sync/changes: what changed since the app's last cursor, so it refreshes only those chapters.
 * - /sync/pack: a whole subject + grade at once (offline pack, Premium).
 */
class ContentController extends Controller
{
    private const CHANGES_PAGE = 500;

    public function __construct(private readonly ContentPayload $payload) {}

    public function catalog(): JsonResponse
    {
        return response()->json([
            'cursor' => ContentChange::latestCursor(),
            ...$this->payload->catalog(),
        ]);
    }

    public function chapter(Chapter $chapter): JsonResponse
    {
        abort_unless($chapter->is_active, 404);

        return response()->json([
            'cursor' => ContentChange::latestCursor(),
            ...$this->payload->chapter($chapter),
        ]);
    }

    public function changes(Request $request): JsonResponse
    {
        $data = $request->validate([
            'since' => ['required', 'integer', 'min:0'],
        ]);

        $rows = ContentChange::query()
            ->where('id', '>', (int) $data['since'])
            ->orderBy('id')
            ->limit(self::CHANGES_PAGE + 1)
            ->get(['id', 'entity', 'chapter_id']);

        $hasMore = $rows->count() > self::CHANGES_PAGE;
        $rows = $rows->take(self::CHANGES_PAGE);

        $catalogChanged = $rows->contains(fn (ContentChange $c): bool => in_array($c->entity, ['subject', 'unit', 'chapter'], true));

        return response()->json([
            'cursor' => $rows->isEmpty() ? (int) $data['since'] : (int) $rows->max('id'),
            'has_more' => $hasMore,
            'catalog_changed' => $catalogChanged,
            'chapter_ids' => $rows->pluck('chapter_id')->filter()->unique()->sort()->values()->all(),
        ]);
    }

    public function pack(Request $request, PremiumService $premium): JsonResponse
    {
        /** @var User $user */
        $user = $request->user();

        if (! $premium->isPremium($user)) {
            return response()->json(['code' => 'premium_required', 'message' => 'Premium'], 403);
        }

        $data = $request->validate([
            'subject_id' => ['required', 'integer', 'exists:subjects,id'],
            'grade' => ['required', 'integer', 'between:9,12'],
        ]);

        $chapters = Chapter::query()
            ->where('is_active', true)
            ->whereHas('unit', fn ($q) => $q->where('is_active', true)
                ->where('subject_id', $data['subject_id'])
                ->where('grade', $data['grade']))
            ->orderBy('sort')->orderBy('number')
            ->get();

        return response()->json([
            'cursor' => ContentChange::latestCursor(),
            'chapters' => $chapters->map(fn (Chapter $c): array => $this->payload->chapter($c))->values()->all(),
        ]);
    }
}
