<?php

namespace App\Http\Presenters;

use App\Models\User;
use App\Services\PremiumService;

/**
 * The signed-in student's own data. Never used for other students (no public profiles).
 */
class MePresenter
{
    public function __construct(private readonly PremiumService $premium) {}

    /**
     * @return array<string, mixed>
     */
    public function present(User $user): array
    {
        $until = $this->premium->activeUntil($user);

        return [
            'student_id' => $user->student_id,
            'signed_in' => $user->google_sub !== null,
            'grade' => $user->grade,
            'stream' => $user->stream,
            'is_retaker' => $user->is_retaker,
            'region' => $user->region,
            'city' => $user->city,
            'gender' => $user->gender?->value,
            'target_score' => $user->target_score,
            'reminder_time' => $user->reminder_time !== null ? substr($user->reminder_time, 0, 5) : null,
            'nickname' => $user->nickname,
            'avatar_style' => $user->avatar_style,
            'avatar_seed' => $user->avatar_seed,
            'onboarded' => $user->onboarded_at !== null,
            'premium' => [
                'active' => $until !== null,
                'until' => $until?->toIso8601String(),
            ],
            'created_at' => $user->created_at->toIso8601String(),
        ];
    }
}
