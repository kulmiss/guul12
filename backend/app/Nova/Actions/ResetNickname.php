<?php

namespace App\Nova\Actions;

use App\Models\NicknameReport;
use App\Models\User;
use App\Nova\Actions\Concerns\ModeratorsOnly;
use App\Nova\Actions\Concerns\NeedsConfirmWord;
use App\Services\NicknameModeration;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Removes a student's nickname (from a nickname report or the student page). They must pick a new one,
 * the old one can never be chosen again, and all open reports about them are closed.
 */
class ResetNickname extends Action
{
    use ModeratorsOnly;
    use NeedsConfirmWord;

    public $name = 'Reset nickname';

    public $confirmText = 'The student loses this nickname and must choose a new one. The old nickname can never be used again. All open reports about this student are closed.';

    public $confirmButtonText = 'Reset nickname';

    public function __construct()
    {
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
            ->filter(fn (?User $user): bool => $user !== null && $user->nickname !== null)
            ->unique('id');

        foreach ($students as $student) {
            /** @var User $student */
            $moderation->reset($student, $staff);
        }

        return $students->isEmpty()
            ? ActionResponse::danger('Nothing to reset: the nickname was already removed.')
            : Action::message($students->count() === 1 ? 'Nickname removed.' : "{$students->count()} nicknames removed.");
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [$this->confirmWordField()];
    }
}
