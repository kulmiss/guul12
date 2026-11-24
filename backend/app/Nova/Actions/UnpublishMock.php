<?php

namespace App\Nova\Actions;

use App\Models\Mock;
use App\Models\User;
use App\Nova\Actions\Concerns\NeedsConfirmWord;
use App\Services\Staff\ContentChanges;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Hides mocks from students until staff publish them again. The content import does not undo this.
 */
class UnpublishMock extends DestructiveAction
{
    use NeedsConfirmWord;

    public $name = 'Unpublish';

    public $confirmText = 'Students stop seeing these mocks. Results already taken stay.';

    /** @param  Collection<int, Mock>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $actor */
        $actor = request()->user();
        $n = $models->filter(fn (Mock $m): bool => app(ContentChanges::class)->unpublishMock($m, $actor))->count();

        return $n === 0 ? ActionResponse::danger('Nothing changed: already unpublished.') : ActionResponse::message("{$n} unpublished.");
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [$this->confirmWordField()];
    }
}
