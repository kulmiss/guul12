<?php

namespace App\Nova\Actions;

use App\Models\Mock;
use App\Models\User;
use App\Services\Staff\ContentChanges;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/**
 * Shows unpublished mocks to students again (if they are still in the content files).
 */
class PublishMock extends Action
{
    public $name = 'Publish';

    public $confirmText = 'Students see these mocks again (if they are in the content files).';

    /** @param  Collection<int, Mock>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $actor */
        $actor = request()->user();
        $n = $models->filter(fn (Mock $m): bool => app(ContentChanges::class)->publishMock($m, $actor))->count();

        return $n === 0 ? ActionResponse::danger('Nothing changed: already published.') : ActionResponse::message("{$n} published.");
    }
}
