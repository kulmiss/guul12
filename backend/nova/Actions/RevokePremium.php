<?php

namespace App\Nova\Actions;

use App\Models\PremiumGrant;
use App\Models\User;
use App\Services\PremiumService;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

class RevokePremium extends Action
{
    public $name = 'Undo Premium';

    public $confirmText = 'Undo this Premium grant? The student goes back to free (progress is kept).';

    public $confirmButtonText = 'Undo';

    public $destructive = true;

    /**
     * @param  Collection<int, PremiumGrant>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $service = app(PremiumService::class);
        /** @var User $actor */
        $actor = request()->user();

        foreach ($models as $grant) {
            $service->revoke($grant, $actor, $fields->get('reason') !== null ? (string) $fields->get('reason') : null);
        }

        return Action::message('Premium undone.');
    }

    public function fields(NovaRequest $request): array
    {
        return [
            Text::make('Reason')->rules('required', 'max:255'),
        ];
    }
}
