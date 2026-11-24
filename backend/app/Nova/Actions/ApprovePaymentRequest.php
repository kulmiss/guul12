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

/** The money arrived: give Premium for each waiting request. */
class ApprovePaymentRequest extends Action
{
    use PaymentStaffOnly;

    public $name = 'Approve: give Premium';

    public $confirmText = 'Check that the money really arrived (amount and reference). Premium starts now, or after the student\'s current Premium ends.';

    public $confirmButtonText = 'Give Premium';

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
        $skipped = 0;
        foreach ($models as $request) {
            try {
                app(PaymentRequests::class)->approve($request, $staff);
                $done++;
            } catch (PaymentRequestProblem) {
                $skipped++;
            }
        }

        if ($done === 0) {
            return ActionResponse::danger("Nothing done: {$skipped} request(s) were already decided.");
        }

        return Action::message("Premium given for {$done} request(s).".($skipped > 0 ? " {$skipped} were already decided." : ''));
    }

    public function uriKey(): string
    {
        return 'approve-payment';
    }
}
