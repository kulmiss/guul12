<?php

namespace App\Nova\Lenses;

use App\Enums\QuestionStatus;
use App\Models\Chapter;
use App\Nova\Filters\FilterSets;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\LensRequest;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Chapters missing something students should have: a summary, solved exercises, flashcards, the
 * textbook link, or at least 30 questions students can see. Only content students can see counts.
 */
class IncompleteContent extends Lens
{
    public const SLUG = 'incomplete-content';

    public const MIN_QUESTIONS = 30;

    public $name = 'Incomplete content';

    public static function query(LensRequest $request, Builder $query): Builder
    {
        $visible = fn (EloquentBuilder $q) => $q->whereIn('status', self::visible());

        return self::finish($request, $query->with('unit.subject')
            ->withExists(['summary as has_summary' => $visible, 'solvedExercises as has_solved' => $visible, 'flashcards as has_flashcards' => $visible])
            ->withCount(['questions as visible_questions' => $visible])
            ->where(fn (Builder $q) => $q
                ->whereDoesntHave('summary', $visible)
                ->orWhereDoesntHave('solvedExercises', $visible)
                ->orWhereDoesntHave('flashcards', $visible)
                ->orWhereNull('textbook_url')
                ->orWhereHas('questions', $visible, '<', self::MIN_QUESTIONS)
                ->orWhereDoesntHave('questions', $visible)),
            fn (Builder $q) => $q->orderBy('unit_id')->orderBy('number'));
    }

    /** @return list<string> */
    private static function visible(): array
    {
        return array_map(fn (QuestionStatus $s): string => $s->value, QuestionStatus::visibleToStudents());
    }

    /** What a chapter still needs, in easy words. */
    public static function missing(Chapter $chapter): string
    {
        $questions = (int) $chapter->getAttribute('visible_questions');
        $missing = array_keys(array_filter([
            'summary' => ! $chapter->getAttribute('has_summary'),
            'solved exercises' => ! $chapter->getAttribute('has_solved'),
            'flashcards' => ! $chapter->getAttribute('has_flashcards'),
            'textbook link' => $chapter->textbook_url === null,
            'questions ('.$questions.' of '.self::MIN_QUESTIONS.')' => $questions < self::MIN_QUESTIONS,
        ]));

        return implode(', ', $missing);
    }

    /** @return list<Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->hideFromIndex(),
            Text::make('Chapter', fn (Chapter $c): string => $c->title),
            Text::make('Subject', fn (Chapter $c): string => $c->unit->subject->name.' · Grade '.$c->unit->grade),
            Text::make('Missing', fn (Chapter $c): string => self::missing($c)),
        ];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::chapters();
    }
}
