<?php

namespace App\Jobs;

use App\Contracts\StaffNotifier;
use App\Models\User;
use App\Services\Staff\QuestionChanges;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * A big question change from Nova (more than QuestionChanges::BULK at once), done in the background.
 * The staff member gets a message when it is finished.
 */
class ApplyQuestionChanges implements ShouldQueue
{
    use Queueable;

    public int $timeout = 900;

    /**
     * @param  list<int>  $ids
     * @param  array<string, mixed>  $params
     */
    public function __construct(
        public readonly string $operation,
        public readonly array $ids,
        public readonly array $params,
        public readonly int $actorId,
    ) {}

    public function handle(QuestionChanges $changes, StaffNotifier $notifier): void
    {
        $actor = User::query()->find($this->actorId);
        if ($actor === null || ! $actor->can('manage-content')) {
            return;
        }
        $result = $changes->apply($this->operation, $this->ids, $this->params, $actor);
        $label = QuestionChanges::OPERATIONS[$this->operation] ?? $this->operation;
        $notifier->notify($actor, "{$label} finished: {$result['changed']} changed, {$result['skipped']} skipped.", 'success', '/resources/questions');
    }
}
