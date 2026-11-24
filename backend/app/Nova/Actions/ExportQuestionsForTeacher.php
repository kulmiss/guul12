<?php

namespace App\Nova\Actions;

use App\Models\AuditLog;
use App\Models\Question;
use App\Models\User;
use App\Support\CsvCell;
use App\Support\QuestionPreview;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Actions\ExportAsCsv;

/**
 * The selected questions (or all that match the filters) with options, answer and explanation, for
 * a teacher to check. Every export is logged.
 */
class ExportQuestionsForTeacher extends ExportAsCsv
{
    public function __construct()
    {
        parent::__construct('Export for teacher check');

        $this->withQuery(function (Builder $query): Builder {
            $actor = request()->user();
            AuditLog::write($actor instanceof User ? $actor : null, 'export.questions', null, ['rows' => (clone $query)->count()]);

            return $query->with(['chapter', 'options']);
        })->withFormat(fn (Model $q): array => ! $q instanceof Question ? [] : CsvCell::row([
            'ID' => $q->id,
            'Chapter' => $q->chapter->title,
            'Type' => str_replace('_', ' ', $q->type->value),
            'Difficulty' => $q->difficulty,
            'Status' => $q->status->value,
            'Question' => $q->stem,
            'Options' => QuestionPreview::optionsText($q),
            'Answer' => QuestionPreview::answerText($q),
            'Explanation' => $q->explanation,
        ]));
    }

    public function uriKey(): string
    {
        return 'export-for-teacher-check';
    }
}
