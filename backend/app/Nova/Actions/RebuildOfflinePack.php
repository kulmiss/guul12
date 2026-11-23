<?php

namespace App\Nova\Actions;

use App\Models\Chapter;
use App\Models\User;
use App\Services\Staff\ContentChanges;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/**
 * Offline packs are built when a phone asks; this makes phones download the chapters again at their
 * next sync (after fixing a picture or a file that is not in the database, for example).
 */
class RebuildOfflinePack extends Action
{
    public $name = 'Rebuild offline pack';

    public $confirmText = 'Phones download these chapters again at their next sync.';

    /** @param  Collection<int, Chapter>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $actor */
        $actor = request()->user();
        $models->each(fn (Chapter $c) => app(ContentChanges::class)->resendChapter($c, $actor));
        $n = $models->count();

        return ActionResponse::message("Phones download {$n} chapter".($n === 1 ? '' : 's').' again at their next sync.');
    }
}
