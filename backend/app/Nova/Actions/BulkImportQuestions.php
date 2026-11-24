<?php

namespace App\Nova\Actions;

use App\Content\QuestionBulkImport;
use App\Models\Chapter;
use App\Models\User;
use App\Nova\Fields\ChapterChoices;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Actions\ActionResponse;
use Laravel\Nova\Fields\ActionFields;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\File;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Adds questions from a YAML file (the content-file format) to one chapter, as Draft. All or nothing.
 */
class BulkImportQuestions extends Action
{
    public $name = 'Bulk import';

    public $standalone = true;

    public $confirmText = 'Questions are added as Draft. They need the second solve and Beta before students see them.';

    public $confirmButtonText = 'Import';

    /** @param  Collection<int, mixed>  $models */
    public function handle(ActionFields $fields, Collection $models): ActionResponse
    {
        /** @var User $actor */
        $actor = request()->user();
        $chapter = Chapter::query()->findOrFail((int) $fields->get('chapter'));
        $file = $fields->get('file');
        $text = $file instanceof UploadedFile ? (string) file_get_contents($file->getRealPath()) : '';

        $result = app(QuestionBulkImport::class)->import($text, $chapter, $actor);
        if ($result['errors'] !== []) {
            return ActionResponse::danger('Nothing was added. '.implode(' · ', array_slice($result['errors'], 0, 5))
                .(count($result['errors']) > 5 ? ' · and '.(count($result['errors']) - 5).' more' : ''));
        }
        $n = count($result['created']);

        return Action::message($n === 1
            ? '1 question added as Draft. It needs the second solve before students see it.'
            : "{$n} questions added as Draft. They need the second solve before students see them.");
    }

    /** @return array<int, Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            Select::make('Chapter')->options(fn (): array => ChapterChoices::all())->searchable()->rules('required', 'integer', 'exists:chapters,id'),
            File::make('File')->rules('required', 'file', 'max:1024', 'extensions:yaml,yml,txt')
                ->help('Questions in the same YAML format as the content files (no uid, no passage). Up to 200.'),
        ];
    }
}
