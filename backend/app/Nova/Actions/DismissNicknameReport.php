<?php

namespace App\Nova\Actions;

use App\Enums\NicknameReportStatus;
use App\Models\NicknameReport;
use App\Models\User;
use App\Nova\Actions\Concerns\ModeratorsOnly;
use App\Services\NicknameModeration;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/** The nickname is fine: close the report and keep the nickname. */
class DismissNicknameReport extends Action
{
    use ModeratorsOnly;

    public $name = 'Not a problem';

    public $confirmText = 'Close the report and keep the nickname?';

    public $confirmButtonText = 'Close report';

    public function __construct()
    {
        $this->onlyModerators();
    }

    /**
     * @param  Collection<int, NicknameReport>  $models
     */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $staff */
        $staff = request()->user();
        $open = $models->filter(fn (NicknameReport $report): bool => $report->status === NicknameReportStatus::Open);
        foreach ($open as $report) {
            app(NicknameModeration::class)->dismiss($report, $staff);
        }

        return Action::message("{$open->count()} report(s) closed.");
    }

    public function uriKey(): string
    {
        return 'not-a-problem';
    }
}
