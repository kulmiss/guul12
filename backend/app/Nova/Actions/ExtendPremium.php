<?php

namespace App\Nova\Actions;

use App\Models\User;
use App\Nova\Actions\Concerns\PaymentStaffOnly;
use App\Services\PremiumService;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Adds Premium days by hand (compensation, a prize). No payment: the reason is required and logged.
 */
class ExtendPremium extends Action
{
    use PaymentStaffOnly;

    public $name = 'Extend Premium';

    public $onlyOnDetail = true;

    public $confirmText = 'The days are added after the current Premium ends (or from today).';

    public $confirmButtonText = 'Add days';

    public function __construct()
    {
        $this->onlyPaymentStaff();
    }

    /** @param  Collection<int, User>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $actor */
        $actor = request()->user();
        $days = (int) $fields->get('days');
        foreach ($models as $student) {
            app(PremiumService::class)->extend($student, $days, $actor, (string) $fields->get('reason'));
        }

        return Action::message("{$days} Premium days added.");
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            Number::make('Days')->min(1)->max(PremiumService::MAX_EXTRA_DAYS)
                ->rules('required', 'integer', 'between:1,'.PremiumService::MAX_EXTRA_DAYS),
            Text::make('Reason')->rules('required', 'max:255')->help('For example: the app was down for a week.'),
        ];
    }
}
