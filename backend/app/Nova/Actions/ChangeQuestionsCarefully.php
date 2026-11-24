<?php

namespace App\Nova\Actions;

use App\Nova\Actions\Concerns\ChangesQuestions;
use Laravel\Nova\Actions\DestructiveAction;

/**
 * Back to Draft (students stop seeing the question) and Mark fixed + re-score (students' scores
 * change). Both ask for the word CONFIRM.
 */
class ChangeQuestionsCarefully extends DestructiveAction
{
    use ChangesQuestions;

    public function __construct(string $operation)
    {
        $this->forOperation($operation);
        $this->confirmText = $operation === 'draft'
            ? 'Students stop seeing these questions until they pass the quality gate again.'
            : 'Every stored answer is scored again with the current key. Students\' scores change quietly.';
    }
}
