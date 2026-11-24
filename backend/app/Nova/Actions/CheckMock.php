<?php

namespace App\Nova\Actions;

use App\Models\Mock;
use App\Services\Staff\ContentChanges;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/**
 * Says which of the mock's questions students see. The app picks up mock changes by itself
 * (each mock has a hash of its questions), so there is nothing to rebuild by hand.
 */
class CheckMock extends Action
{
    public $name = 'Check mock';

    public $withoutConfirmation = true;

    public $sole = true;

    /** @param  Collection<int, Mock>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $mock = $models->first();

        return $mock instanceof Mock ? ActionResponse::message(app(ContentChanges::class)->checkMock($mock)) : ActionResponse::danger('Choose one mock.');
    }
}
