<?php

namespace App\Nova\Actions;

use App\Jobs\RunBackup;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\Ops\StaffTools;
use App\Services\Ops\ToolResult;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;

/**
 * Settings tools for super admins (App settings page): refresh cached data, test Telegram and email,
 * run a backup now.
 */
class StaffTool extends Action
{
    public $standalone = true;

    private const TOOLS = [
        'refresh' => ['Refresh app and landing data', 'Settings and switches normally show within 5 minutes; this shows them now.'],
        'telegram' => ['Send test Telegram', 'A short test message goes to the staff Telegram group.'],
        'email' => ['Send test email', 'A short test email goes to your own staff email address.'],
        'backup' => ['Run backup now', 'The same backup as every night, in the background.'],
    ];

    public function __construct(private readonly string $tool)
    {
        [$this->name, $this->confirmText] = self::TOOLS[$tool];
    }

    /** @param  Collection<int, mixed>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $actor */
        $actor = request()->user();
        $tools = app(StaffTools::class);

        return match ($this->tool) {
            'refresh' => ActionResponse::message('Refreshed: '.implode(', ', $tools->refreshCaches($actor)).'. The app and the landing page show changes now.'),
            'telegram' => self::show($tools->sendTestTelegram($actor)),
            'email' => self::show($tools->sendTestEmail($actor)),
            default => $this->backup($actor),
        };
    }

    private function backup(User $actor): ActionResponse
    {
        RunBackup::dispatch($actor->id);
        AuditLog::write($actor, 'ops.backup_started');

        return ActionResponse::message('Backup started. You will get a message when it is done (usually within 2 minutes).');
    }

    /** Green when it worked, red with the explanation when not. */
    private static function show(ToolResult $result): ActionResponse
    {
        return $result->ok ? ActionResponse::message($result->message) : ActionResponse::danger($result->message);
    }
}
