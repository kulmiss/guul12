<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Battle;
use App\Models\Question;
use App\Models\User;
use App\Services\BattleService;
use App\Services\ContentPayload;
use App\Services\FeatureSwitches;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class BattleController extends Controller
{
    public function __construct(
        private readonly BattleService $battles,
        private readonly ContentPayload $payload,
        private readonly FeatureSwitches $switches,
    ) {}

    /** GET /v1/battles — the student's battles of the last 7 days, newest first. */
    public function index(Request $request): JsonResponse
    {
        if ($off = $this->off()) {
            return $off;
        }
        /** @var User $me */
        $me = $request->user();
        $list = Battle::query()
            ->where(fn ($q) => $q->where('creator_id', $me->id)->orWhere('opponent_id', $me->id))
            ->where('created_at', '>=', Carbon::now()->subDays(7))
            ->with(['creator', 'opponent'])
            ->orderByDesc('id')
            ->limit(30)
            ->get();

        return response()->json(['battles' => $list->map(fn (Battle $b): array => $this->battles->present($b, $me))->values()->all()]);
    }

    /** POST /v1/battles — type "random" (quick battle) or "friend" (with a code). */
    public function store(Request $request): JsonResponse
    {
        if ($off = $this->off()) {
            return $off;
        }
        /** @var User $me */
        $me = $request->user();
        $data = $request->validate([
            'type' => ['required', 'in:random,friend'],
            'subject_id' => ['nullable', 'integer', 'exists:subjects,id'],
        ]);
        if ($blocked = $this->needsNickname($me)) {
            return $blocked;
        }
        if ($this->battles->startedToday($me) >= BattleService::MAX_PER_DAY) {
            return response()->json(['code' => 'battle_limit', 'message' => 'Enough battles for today.'], 429);
        }

        $subjectId = isset($data['subject_id']) ? (int) $data['subject_id'] : null;
        $battle = $data['type'] === 'random'
            ? $this->battles->quick($me, $subjectId)
            : $this->battles->forFriend($me, $subjectId);
        if ($battle === null) {
            return response()->json(['code' => 'no_questions', 'message' => 'No questions yet'], 404);
        }

        return $this->full($battle, $me, 201);
    }

    /** POST /v1/battles/join — join a friend's battle with its code. */
    public function join(Request $request): JsonResponse
    {
        if ($off = $this->off()) {
            return $off;
        }
        /** @var User $me */
        $me = $request->user();
        $data = $request->validate(['code' => ['required', 'string', 'max:8']]);
        if ($blocked = $this->needsNickname($me)) {
            return $blocked;
        }
        $result = $this->battles->join($me, (string) $data['code']);
        if (is_string($result)) {
            return response()->json(['code' => $result, 'message' => 'Cannot join this battle.'], $result === 'not_found' ? 404 : 409);
        }

        return $this->full($result, $me, 200);
    }

    /** GET /v1/battles/{battle} — for its two players only. */
    public function show(Request $request, Battle $battle): JsonResponse
    {
        if ($off = $this->off()) {
            return $off;
        }
        /** @var User $me */
        $me = $request->user();
        abort_unless($battle->hasPlayer($me), 404);

        return $this->full($battle, $me, 200);
    }

    private function full(Battle $battle, User $me, int $status): JsonResponse
    {
        $battle->load(['creator', 'opponent']);

        return response()->json([
            'battle' => $this->battles->present($battle, $me),
            'questions' => $this->battles->questions($battle)->map(fn (Question $q): array => $this->payload->question($q))->values()->all(),
        ], $status);
    }

    private function off(): ?JsonResponse
    {
        return $this->switches->enabled('battles')
            ? null
            : response()->json(['code' => 'feature_disabled', 'message' => 'Battles are turned off.'], 403);
    }

    private function needsNickname(User $me): ?JsonResponse
    {
        return $me->nickname === null
            ? response()->json(['code' => 'nickname_required', 'message' => 'Choose a nickname first.'], 422)
            : null;
    }
}
