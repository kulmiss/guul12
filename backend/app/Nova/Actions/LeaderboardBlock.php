<?php

namespace App\Nova\Actions;

use App\Models\NicknameReport;
use App\Models\User;
use App\Nova\Actions\Concerns\ModeratorsOnly;
use App\Services\NicknameModeration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/**
 * Hides a student from the weekly leaderboard (or shows them again). They keep studying normally.
 * Works from the student page and from a nickname report.
 */
class LeaderboardBlock extends Action
{
    use ModeratorsOnly;

    public function __construct(private readonly bool $block)
    {
        $this->name = $block ? 'Block from leaderboard' : 'Unblock from leaderboard';
        $this->confirmText = $block
            ? 'The student disappears from the weekly leaderboard at once. They can still study and play battles.'
            : 'The student appears on the weekly leaderboard again.';
        $this->confirmButtonText = $block ? 'Block' : 'Unblock';
        $this->onlyModerators();
    }

    /**
     * @param  Collection<int, Model>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $staff */
        $staff = request()->user();
        $moderation = app(NicknameModeration::class);
        $students = $models
            ->map(fn (Model $model): ?User => $model instanceof NicknameReport ? $model->reportedUser : ($model instanceof User ? $model : null))
            ->filter()
            ->unique('id');

        foreach ($students as $student) {
            /** @var User $student */
            $this->block ? $moderation->block($student, $staff) : $moderation->unblock($student, $staff);
        }

        return Action::message($this->block ? 'Blocked from the leaderboard.' : 'Shown on the leaderboard again.');
    }

    public function uriKey(): string
    {
        return $this->block ? 'block-from-leaderboard' : 'unblock-from-leaderboard';
    }
}
