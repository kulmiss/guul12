<?php

namespace App\Nova\Actions;

use App\Models\Question;
use App\Models\User;
use App\Services\Staff\QuestionChanges;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/**
 * Makes a Draft copy (to write a variant). One question opens its copy; several stay in the list.
 */
class DuplicateQuestion extends Action
{
    public $name = 'Duplicate';

    public $confirmText = 'A Draft copy is made with the same text, options and key. It needs the second solve like any new question.';

    public $confirmButtonText = 'Make a copy';

    /** @param  Collection<int, Question>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $actor */
        $actor = request()->user();
        $copies = $models->map(fn (Question $q): Question => app(QuestionChanges::class)->duplicate($q, $actor));

        return $copies->count() === 1
            ? ActionResponse::visit('/resources/questions/'.$copies->first()?->id)
            : Action::message($copies->count().' Draft copies made.');
    }
}
