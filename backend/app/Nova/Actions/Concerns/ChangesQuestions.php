<?php

namespace App\Nova\Actions\Concerns;

use App\Jobs\ApplyQuestionChanges;
use App\Models\User;
use App\Nova\Fields\ChapterChoices;
use App\Services\Staff\QuestionChanges;
use Illuminate\Support\Str;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\Response;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\ActionRequest;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * One question change (see QuestionChanges::OPERATIONS) on the selected questions, or on all that
 * match the filters. Up to QuestionChanges::BULK run at once; more run in the background and the
 * staff member gets a bell message when done.
 */
trait ChangesQuestions
{
    use NeedsConfirmWord;

    protected string $operation = '';

    protected function forOperation(string $operation): void
    {
        $this->operation = $operation;
        $this->name = QuestionChanges::OPERATIONS[$operation];
    }

    protected function dispatchRequestUsing(ActionRequest $request, Response $response, ActionFields $fields): Response
    {
        /** @var User $actor */
        $actor = $request->user();
        /** @var list<int> $ids */
        $ids = $request->toSelectedResourceQuery()->pluck('questions.id')->map(fn (mixed $id): int => (int) $id)->all();
        $params = ['difficulty' => (int) $fields->get('difficulty'), 'chapter_id' => (int) $fields->get('chapter')];

        if (count($ids) > QuestionChanges::BULK) {
            ApplyQuestionChanges::dispatch($this->operation, $ids, $params, $actor->id);

            return $response->successful([Action::message('Started in the background for '.count($ids).' questions. You will get a message when it is done.')]);
        }

        $result = app(QuestionChanges::class)->apply($this->operation, $ids, $params, $actor);
        $text = "{$result['changed']} changed".($result['skipped'] > 0 ? ", {$result['skipped']} skipped (not allowed from its status)." : '.');

        return $response->successful([$result['changed'] === 0 ? Action::danger('Nothing changed: '.$text) : Action::message($text)]);
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return match ($this->operation) {
            'difficulty' => [Select::make('Difficulty')->options(['1' => '1 – very easy', '2' => '2', '3' => '3', '4' => '4', '5' => '5 – very hard'])
                ->rules('required', 'in:1,2,3,4,5')],
            'chapter' => [Select::make('Chapter')->options(fn (): array => ChapterChoices::all())->searchable()
                ->rules('required', 'integer', 'exists:chapters,id')],
            'draft' => [$this->confirmWordField()],
            'fixed' => [$this->confirmWordField()],
            default => [],
        };
    }

    public function uriKey(): string
    {
        return Str::slug(QuestionChanges::OPERATIONS[$this->operation]);
    }
}
