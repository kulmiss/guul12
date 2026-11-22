<?php

namespace App\Nova\Actions;

use App\Enums\PremiumPlan;
use App\Models\User;
use App\Services\PremiumService;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Helper flow: student paid → find by student ID → choose plan → note payment reference → Premium on.
 */
class GrantPremium extends Action
{
    public $name = 'Give Premium';

    public $onlyOnDetail = true;

    public $confirmButtonText = 'Give Premium';

    /**
     * @param  Collection<int, User>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        $service = app(PremiumService::class);
        /** @var User $actor */
        $actor = request()->user();
        $plan = PremiumPlan::from((string) $fields->get('plan'));

        foreach ($models as $student) {
            $service->grant($student, $plan, $actor, (string) $fields->get('payment_ref'), $fields->get('note') !== null ? (string) $fields->get('note') : null);
        }

        return Action::message('Premium given.');
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            Select::make('Plan')->options([
                PremiumPlan::Term->value => 'Term',
                PremiumPlan::Year->value => 'Year',
            ])->rules('required'),
            Text::make('Payment reference', 'payment_ref')->rules('required', 'max:128'),
            Textarea::make('Note')->rules('nullable', 'max:255'),
        ];
    }
}
