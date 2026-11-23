<?php

namespace App\Nova\Actions;

use App\Enums\Access;
use App\Models\Chapter;
use App\Models\User;
use App\Services\Staff\ContentChanges;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/**
 * Makes the lesson video of the selected chapters free or Premium. Chapters without a video are skipped.
 */
class SetVideoAccess extends Action
{
    public function __construct(private readonly Access $access)
    {
        $this->name = $access === Access::Premium ? 'Video: Premium' : 'Video: Free';
        $this->confirmText = $access === Access::Premium
            ? 'Only Premium students can watch the lesson video of these chapters.'
            : 'Every student can watch the lesson video of these chapters.';
    }

    /** @param  Collection<int, Chapter>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $actor */
        $actor = request()->user();
        $changed = $models->filter(fn (Chapter $c): bool => app(ContentChanges::class)->setVideoAccess($c, $this->access, $actor))->count();
        $word = $this->access === Access::Premium ? 'Premium' : 'free';

        return $changed === 0
            ? ActionResponse::danger('Nothing changed (no video, or already '.$word.').')
            : ActionResponse::message("Video is {$word} now for {$changed} chapter".($changed === 1 ? '.' : 's.'));
    }
}
