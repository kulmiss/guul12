<?php

namespace App\Nova\Actions;

use App\Models\PaymentRequest;
use App\Models\User;
use App\Nova\Actions\Concerns\PaymentStaffOnly;
use App\Services\PaymentRequests;
use App\Services\PaymentRequests\PaymentRequestProblem;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Http\Requests\NovaRequest;

/** The payment is wrong or missing: no Premium, with a reason others can read. */
class RejectPaymentRequest extends Action
{
    use PaymentStaffOnly;

    public $name = 'Reject';

    public $confirmText = 'No Premium is given. Write the reason (for example: money not received, wrong amount).';

    public $confirmButtonText = 'Reject';

    public function __construct()
    {
        $this->onlyPaymentStaff();
    }

    /**
     * @param  Collection<int, PaymentRequest>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $staff */
        $staff = request()->user();
        $done = 0;
        foreach ($models as $request) {
            try {
                app(PaymentRequests::class)->reject($request, $staff, (string) $fields->get('reason'));
                $done++;
            } catch (PaymentRequestProblem) {
                // Already decided by someone else: leave it.
            }
        }

        return $done === 0
            ? ActionResponse::danger('Nothing done: the request(s) were already decided.')
            : Action::message("{$done} request(s) rejected.");
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [Textarea::make('Reason')->rules('required', 'string', 'max:255')];
    }

    public function uriKey(): string
    {
        return 'reject-payment';
    }
}
