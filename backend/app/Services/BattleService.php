<?php

namespace App\Services;

use App\Enums\PracticeMode;
use App\Models\Attempt;
use App\Models\Battle;
use App\Models\Question;
use App\Models\User;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Battles: two students answer the same 10 questions, each when they like. More right answers
 * wins; on a tie, less total time. No chat and no contact: only nickname, avatar and scores.
 */
class BattleService
{
    public const SIZE = 10;

    public const SECONDS_PER_QUESTION = 60;

    public const HOURS = 48;

    /** Battles one student may start per day (stops spam, plenty for real use). */
    public const MAX_PER_DAY = 20;

    private const CODE_LETTERS = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    public function __construct(private readonly QuestionPicker $picker) {}

    public function startedToday(User $user): int
    {
        return Battle::query()
            ->where('creator_id', $user->id)
            ->where('created_at', '>=', Carbon::now(DailyChallengeService::TIMEZONE)->startOfDay()->utc())
            ->count();
    }

    /**
     * Joins the oldest waiting random battle of another student with the same grade, stream and
     * subject, or starts a new one that waits for an opponent. Null when there are no questions.
     */
    public function quick(User $user, ?int $subjectId): ?Battle
    {
        $key = DailyChallengeService::keyFor($user);
        $open = Battle::query()
            ->where('is_random', true)
            ->where('key', $key)
            ->where('subject_id', $subjectId)
            ->whereNull('opponent_id')
            ->where('creator_id', '!=', $user->id)
            ->where('expires_at', '>', Carbon::now())
            ->orderBy('id')
            ->limit(5)
            ->pluck('id');

        foreach ($open->map(fn ($id): int => (int) $id) as $id) {
            // Claim it only if nobody else did in the meantime.
            $claimed = Battle::query()->whereKey($id)->whereNull('opponent_id')->update(['opponent_id' => $user->id]);
            if ($claimed === 1) {
                return Battle::query()->findOrFail($id);
            }
        }

        return $this->create($user, $subjectId, true);
    }

    /** A battle for a friend, who joins with the code. Null when there are no questions. */
    public function forFriend(User $user, ?int $subjectId): ?Battle
    {
        return $this->create($user, $subjectId, false);
    }

    /**
     * @return Battle|string the battle, or an error code: not_found, own_battle, battle_full, expired
     */
    public function join(User $user, string $code): Battle|string
    {
        $battle = Battle::query()->where('code', strtoupper(trim($code)))->first();
        if ($battle === null) {
            return 'not_found';
        }
        if ($battle->creator_id === $user->id) {
            return 'own_battle';
        }
        if ($battle->opponent_id === $user->id) {
            return $battle;
        }
        if ($battle->expires_at->isPast()) {
            return 'expired';
        }
        $claimed = Battle::query()->whereKey($battle->id)->whereNull('opponent_id')->update(['opponent_id' => $user->id]);

        return $claimed === 1 ? $battle->refresh() : 'battle_full';
    }

    /**
     * @return Collection<int, Question> in battle order (hidden questions left out)
     */
    public function questions(Battle $battle): Collection
    {
        $found = Question::query()->visibleToStudents()->whereIn('id', $battle->question_ids)->with('options')->get()->keyBy('id');

        return collect($battle->question_ids)->map(fn (int $id) => $found->get($id))->filter()->values();
    }

    /**
     * The player's first try: right answers, questions and total time.
     *
     * @return array{correct: int, total: int, time_ms: int}|null
     */
    public function result(Battle $battle, int $userId): ?array
    {
        $attempt = Attempt::query()
            ->where('battle_id', $battle->id)
            ->where('user_id', $userId)
            ->where('mode', PracticeMode::Battle->value)
            ->where('total', '>', 0)
            ->orderBy('id')
            ->first();
        if ($attempt === null) {
            return null;
        }
        $time = (int) DB::table('attempt_answers')->where('attempt_id', $attempt->id)->sum('time_ms');

        return ['correct' => $attempt->correct, 'total' => $attempt->total, 'time_ms' => $time];
    }

    /**
     * What one player sees about a battle.
     *
     * @return array<string, mixed>
     */
    public function present(Battle $battle, User $me): array
    {
        $isCreator = $battle->creator_id === $me->id;
        $other = $isCreator ? $battle->opponent : $battle->creator;
        $mine = $this->result($battle, $me->id);
        $theirs = $other === null ? null : $this->result($battle, $other->id);

        return [
            'id' => $battle->id,
            // Only the student who started a friend battle needs the code.
            'code' => $isCreator && ! $battle->is_random ? $battle->code : null,
            'is_random' => $battle->is_random,
            'subject_id' => $battle->subject_id,
            'question_count' => count($battle->question_ids),
            'seconds_per_question' => self::SECONDS_PER_QUESTION,
            'expires_at' => $battle->expires_at->toIso8601String(),
            'status' => self::status($battle, $other !== null, $mine, $theirs),
            'me' => ['nickname' => $me->nickname, 'avatar_style' => $me->avatar_style, 'avatar_seed' => $me->avatar_seed, 'result' => $mine],
            'opponent' => $other === null ? null : [
                'nickname' => $other->nickname,
                'avatar_style' => $other->avatar_style,
                'avatar_seed' => $other->avatar_seed,
                'result' => $theirs,
            ],
        ];
    }

    /**
     * waiting (no opponent yet), your_turn, their_turn, won, lost, draw, expired.
     *
     * @param  array{correct: int, total: int, time_ms: int}|null  $mine
     * @param  array{correct: int, total: int, time_ms: int}|null  $theirs
     */
    public static function status(Battle $battle, bool $hasOpponent, ?array $mine, ?array $theirs): string
    {
        if ($mine !== null && $theirs !== null) {
            $byScore = $mine['correct'] <=> $theirs['correct'];
            $order = $byScore !== 0 ? $byScore : $theirs['time_ms'] <=> $mine['time_ms'];

            return match ($order) {
                1 => 'won',
                -1 => 'lost',
                default => 'draw',
            };
        }
        if ($battle->expires_at->isPast()) {
            return 'expired';
        }
        if ($mine === null) {
            return 'your_turn';
        }

        return $hasOpponent ? 'their_turn' : 'waiting';
    }

    private function create(User $user, ?int $subjectId, bool $random): ?Battle
    {
        $key = DailyChallengeService::keyFor($user);
        [$gradePart, $stream] = explode('-', $key, 2);
        $ids = $this->picker->pick((int) substr($gradePart, 1), $stream === 'all' ? null : $stream, $subjectId, self::SIZE, random_int(1, PHP_INT_MAX));
        if ($ids === []) {
            return null;
        }

        for ($try = 0; ; $try++) {
            try {
                return Battle::query()->create([
                    'code' => $this->code(),
                    'key' => $key,
                    'subject_id' => $subjectId,
                    'is_random' => $random,
                    'question_ids' => $ids,
                    'creator_id' => $user->id,
                    'expires_at' => Carbon::now()->addHours(self::HOURS),
                ]);
            } catch (UniqueConstraintViolationException $e) {
                // The same code twice: very rare; try another one.
                if ($try >= 5) {
                    throw $e;
                }
            }
        }
    }

    private function code(): string
    {
        $code = '';
        for ($i = 0; $i < 6; $i++) {
            $code .= self::CODE_LETTERS[random_int(0, strlen(self::CODE_LETTERS) - 1)];
        }

        return $code;
    }
}
