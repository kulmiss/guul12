<?php

namespace App\Nova\Actions\Concerns;

use Laravel\Nova\Fields\Text;

/**
 * Destructive actions ask staff to type the word CONFIRM (capital letters) before they run.
 */
trait NeedsConfirmWord
{
    public const CONFIRM_WORD = 'CONFIRM';

    protected function confirmWordField(): Text
    {
        return Text::make('Type CONFIRM to continue', 'confirm')
            ->rules('required', 'in:'.self::CONFIRM_WORD)
            ->help('This cannot be undone with one click. Type CONFIRM in capital letters.');
    }
}
