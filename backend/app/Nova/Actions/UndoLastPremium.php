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
 * Undoes the newest Premium grant of this student (given by mistake: wrong student or wrong plan).
 */
class UndoLastPremium extends DestructiveAction
{
    use NeedsConfirmWord;
    use PaymentStaffOnly;

    public $name = 'Undo last Premium';

    public $onlyOnDetail = true;

    public $confirmText = 'The newest Premium grant of this student is undone, as if it was never given.';

    public $confirmButtonText = 'Undo';

    public function __construct()
    {
        $this->onlyPaymentStaff();
    }

    /** @param  Collection<int, User>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $actor */
        $actor = request()->user();
        $undone = 0;
        foreach ($models as $student) {
            $undone += app(PremiumService::class)->undoLatest($student, $actor, (string) $fields->get('reason')) !== null ? 1 : 0;
        }

        return $undone === 0 ? ActionResponse::danger('Nothing to undo.') : Action::message('Premium grant undone.');
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [Text::make('Reason')->rules('required', 'max:255'), $this->confirmWordField()];
    }
}
