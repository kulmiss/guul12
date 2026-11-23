<?php

namespace App\Nova\Actions;

use App\Models\Mock;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/**
 * Opens the mock the way a student sees it (answers hidden) in a new tab.
 */
class PreviewMockAsStudent extends Action
{
    public $name = 'Preview as student';

    public $withoutConfirmation = true;

    public $sole = true;

    /** @param  Collection<int, Mock>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $mock = $models->first();

        return $mock instanceof Mock ? ActionResponse::openInNewTab(route('staff.preview.mock', $mock)) : ActionResponse::danger('Choose one mock.');
    }
}
