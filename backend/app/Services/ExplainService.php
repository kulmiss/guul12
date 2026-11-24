<?php

namespace App\Services;

use App\Models\AiExplanation;
use App\Models\Question;
use App\Models\User;
use App\Services\Ai\AiUnavailable;
use App\Services\Ai\ExplanationProvider;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * "Explain more" (Premium): an AI explanation of one of OUR questions, saved and reused for every
 * student. A daily limit per student and a monthly spending cap keep the cost small.
 */
class ExplainService
{
    public const MAX_TOKENS = 500;

    /** Hidden when at least this many students said "not helpful" (and more than said helpful). */
    public const HIDE_AFTER = 3;

    private const SYSTEM = 'You are a patient tutor for Ethiopian Grade 12 students preparing for the national exam. '
        .'Explain why the correct answer is right, step by step, in simple English with short sentences. '
        .'If useful, say briefly why a tempting wrong option is wrong. Write maths in LaTeX between $ signs. '
        .'Never change or doubt the given correct answer. No links, no greetings, at most 150 words.';

    public function __construct(
        private readonly ExplanationProvider $ai,
        private readonly Settings $settings,
    ) {}

    public function isAvailable(): bool
    {
        return $this->ai->isConfigured();
    }

    public function usedToday(User $user): int
    {
        return DB::table('ai_requests')
            ->where('user_id', $user->id)
            ->where('created_at', '>=', Carbon::now(DailyChallengeService::TIMEZONE)->startOfDay()->utc())
            ->count();
    }

    public function dailyLimit(): int
    {
        return $this->settings->int('premium_explain_more_per_day');
    }

    /** Spending on new explanations this month (Addis Ababa calendar), in millionths of a dollar. */
    public function spentThisMonth(): int
    {
        return (int) AiExplanation::query()
            ->where('created_at', '>=', Carbon::now(DailyChallengeService::TIMEZONE)->startOfMonth()->utc())
            ->sum('cost_micro_usd');
    }

    /**
     * @return array{explanation: AiExplanation, cached: bool}|string the explanation, or an error code:
     *                                                                explain_limit, ai_budget, ai_unavailable
     */
    public function explain(User $user, Question $question): array|string
    {
        if ($this->usedToday($user) >= $this->dailyLimit()) {
            return 'explain_limit';
        }

        $saved = AiExplanation::query()
            ->where('question_id', $question->id)
            ->where('question_version', $question->version)
            ->where('hidden', false)
            ->orderByDesc('id')
            ->first();
        if ($saved !== null) {
            $this->record($user, $saved, true);

            return ['explanation' => $saved, 'cached' => true];
        }

        if (! $this->ai->isConfigured()) {
            return 'ai_unavailable';
        }
        if ($this->spentThisMonth() >= $this->settings->int('ai_monthly_cap_usd') * 1_000_000) {
            return 'ai_budget';
        }

        try {
            $answer = $this->ai->answer(self::SYSTEM, $this->prompt($question), self::MAX_TOKENS);
        } catch (AiUnavailable) {
            return 'ai_unavailable';
        }

        $explanation = AiExplanation::query()->create([
            'question_id' => $question->id,
            'question_version' => $question->version,
            'body' => $answer->text,
            'model' => mb_substr($answer->model, 0, 64),
            'input_tokens' => $answer->inputTokens,
            'output_tokens' => $answer->outputTokens,
            'cost_micro_usd' => $this->ai->costMicroUsd($answer),
        ]);
        $this->record($user, $explanation, false);

        return ['explanation' => $explanation, 'cached' => false];
    }

    /** One vote per student; changing the vote moves it. Too many "not helpful" hides it. */
    public function feedback(User $user, AiExplanation $explanation, bool $helpful): void
    {
        DB::transaction(function () use ($user, $explanation, $helpful): void {
            $previous = DB::table('ai_feedback')->where('user_id', $user->id)->where('ai_explanation_id', $explanation->id)->first();
            if ($previous !== null && (bool) $previous->helpful === $helpful) {
                return;
            }
            DB::table('ai_feedback')->updateOrInsert(
                ['user_id' => $user->id, 'ai_explanation_id' => $explanation->id],
                ['helpful' => $helpful, 'updated_at' => Carbon::now(), 'created_at' => $previous->created_at ?? Carbon::now()],
            );
            $up = $helpful ? 'helpful' : 'not_helpful';
            $down = $helpful ? 'not_helpful' : 'helpful';
            AiExplanation::query()->whereKey($explanation->id)->increment($up);
            if ($previous !== null) {
                AiExplanation::query()->whereKey($explanation->id)->where($down, '>', 0)->decrement($down);
            }
            $fresh = $explanation->refresh();
            if ($fresh->not_helpful >= self::HIDE_AFTER && $fresh->not_helpful > $fresh->helpful) {
                $fresh->forceFill(['hidden' => true])->save();
            }
        });
    }

    private function record(User $user, AiExplanation $explanation, bool $cached): void
    {
        DB::table('ai_requests')->insert([
            'user_id' => $user->id,
            'ai_explanation_id' => $explanation->id,
            'cached' => $cached,
            'created_at' => Carbon::now(),
        ]);
    }

    private function prompt(Question $question): string
    {
        $lines = ['Question: '.$question->stem];
        foreach ($question->options as $option) {
            $lines[] = $option->key.') '.$option->body;
        }
        $key = $question->answer_key;
        $correct = $key['correct'] ?? $key['value'] ?? null;
        $lines[] = 'Correct answer: '.(is_scalar($correct) ? (string) $correct : (json_encode($key) ?: ''));
        $lines[] = 'Short explanation from the course: '.$question->explanation;
        $lines[] = 'Explain it more clearly for a student who did not understand.';

        return implode("\n", $lines);
    }
}
