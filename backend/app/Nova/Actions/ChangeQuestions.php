<?php

namespace App\Nova\Actions;

use App\Nova\Actions\Concerns\ChangesQuestions;
use Laravel\Nova\Actions\Action;

/**
 * Publish, Move to Beta, Change difficulty, Move to chapter (see ChangesQuestions).
 */
class ChangeQuestions extends Action
{
    use ChangesQuestions;

    public function __construct(string $operation)
    {
        $this->forOperation($operation);
    }
}
