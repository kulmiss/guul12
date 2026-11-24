<?php

namespace App\Nova\Actions;

use App\Models\User;
use App\Nova\Actions\Concerns\NeedsConfirmWord;
use App\Nova\Actions\Concerns\PaymentStaffOnly;
use App\Services\PremiumService;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Actions\DestructiveAction;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Stops Premium today (a refund, a rule broken). Progress is kept; the student is simply free again.
 */
class EndPremium extends DestructiveAction
{
    use NeedsConfirmWord;
    use PaymentStaffOnly;

    public $name = 'End Premium';

    public $onlyOnDetail = true;

    public $confirmText = 'Premium stops now. Days paid for later are cancelled too. Progress is kept.';

    public $confirmButtonText = 'End Premium';

    public function __construct()
    {
        $this->onlyPaymentStaff();
    }

    /** @param  Collection<int, User>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $actor */
        $actor = request()->user();
        $changed = 0;
        foreach ($models as $student) {
            $changed += app(PremiumService::class)->endNow($student, $actor, (string) $fields->get('reason'));
        }

        return $changed === 0 ? ActionResponse::danger('This student has no Premium to end.') : Action::message('Premium ended.');
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [Text::make('Reason')->rules('required', 'max:255'), $this->confirmWordField()];
    }
}
