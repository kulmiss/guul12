<?php

namespace App\Nova\Resources;

use App\Enums\QuestionType;
use App\Models\Question as QuestionModel;
use App\Nova\Actions\BulkImportQuestions;
use App\Nova\Actions\ChangeQuestions;
use App\Nova\Actions\ChangeQuestionsCarefully;
use App\Nova\Actions\DuplicateQuestion;
use App\Nova\Actions\ExportQuestionsForTeacher;
use App\Nova\Fields\Show;
use App\Nova\Filters\FilterSets;
use App\Nova\Lenses;
use App\Support\QuestionPreview;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\Code;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Select;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Fields\Textarea;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;
use Laravel\Nova\Query\Search\SearchableJson;
use Laravel\Nova\Query\Search\SearchableRelation;

/**
 * Questions of all types. LaTeX goes between $…$ (inline) or $$…$$ (block).
 *
 * @extends \App\Nova\Resources\Resource<QuestionModel>
 */
class Question extends Resource
{
    /** @var class-string<QuestionModel> */
    public static $model = QuestionModel::class;

    public static $title = 'id';

    public static string $ability = 'manage-content';

    /** @var list<string> */
    public static $with = ['chapter'];

    /** @return list<string|SearchableRelation|SearchableJson> */
    public static function searchableColumns(): array
    {
        return ['id', 'stem', new SearchableRelation('chapter', 'title'), new SearchableJson('tags')];
    }

    /** Status badge colours, shared by lists and the detail page. */
    public const STATUS_COLOURS = [
        'draft' => 'info',
        'checked' => 'info',
        'beta' => 'warning',
        'published' => 'success',
        'reported' => 'danger',
        'fixed' => 'warning',
        'retired' => 'info',
    ];

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        $types = [];
        foreach (QuestionType::cases() as $type) {
            $types[$type->value] = ucwords(str_replace('_', ' ', $type->value));
        }

        return [
            ID::make()->sortable(),
            BelongsTo::make('Chapter', 'chapter', Chapter::class)->sortable()->searchable(),
            BelongsTo::make('Passage', 'passage', Passage::class)->nullable()->hideFromIndex(),
            Select::make('Type')->options($types)->rules('required')->displayUsingLabels()
                ->resolveUsing(fn ($value) => $value instanceof QuestionType ? $value->value : $value),
            Textarea::make('Question', 'stem')->rules('required')->alwaysShow()
                ->help('Maths between $…$ (in the line) or $$…$$ (on its own line). A real dollar sign: \\$.'),
            Text::make('Question', fn (QuestionModel $q): string => self::short($q->stem))->onlyOnIndex(),
            Text::make('Image / diagram path', 'media_path')->nullable()->hideFromIndex()
                ->help('A file in storage/app/public, for example diagrams/physics/lens-1.svg. Empty = no picture.'),
            Number::make('Difficulty')->rules('required', 'integer', 'between:1,5')->sortable()
                ->help('1 = very easy … 5 = very hard. Used to mix practice sets.'),
            Badge::make('Status', fn (QuestionModel $q): string => $q->status->value)
                ->map(self::STATUS_COLOURS)
                ->labels(array_combine(array_keys(self::STATUS_COLOURS), array_map('ucfirst', array_keys(self::STATUS_COLOURS))))
                ->help('Changes only through the quality gate: Draft → (second solve) → Beta → Published.')
                ->exceptOnForms(),
            Code::make('Answer key', 'answer_key')->json()->rules('required', 'json')
                ->help('e.g. {"correct":"B"} · {"correct":["A","C"]} · {"value":2.5,"tolerance":0.01,"unit":"s"} · {"accepted":["ion"]} · {"pairs":{"L1":"R2"}} · {"order":["C","A","B"]}'),
            Textarea::make('Explanation')->rules('required')->alwaysShow(),
            Text::make('Source')->nullable()->hideFromIndex()
                ->help('Where the question comes from (written for Guul 12, past paper year, …).'),
            Number::make('Version')->exceptOnForms()->hideFromIndex()
                ->help('Goes up when students would see a change; old answers stay with their version.'),
            Number::make('Answered', 'times_answered')->exceptOnForms()->sortable(),
            Text::make('Right', fn (QuestionModel $q): string => Show::percent($q->times_correct, $q->times_answered))->exceptOnForms(),
            Number::make('Reports', 'reports_count')->exceptOnForms()->sortable(),
            Panel::make('Preview', [
                Text::make('Preview', fn (QuestionModel $q): string => QuestionPreview::html($q->loadMissing('options', 'passage')))
                    ->asHtml()->onlyOnDetail()
                    ->help('What students see. The right answer is marked in green.'),
            ]),
            HasMany::make('Options', 'options', QuestionOption::class),
        ];
    }

    private static function short(string $text): string
    {
        $plain = trim((string) preg_replace('/\s+/u', ' ', $text));

        return mb_strlen($plain) > 70 ? mb_substr($plain, 0, 69).'…' : $plain;
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::questions();
    }

    /** @return array<int, Lens> */
    public function lenses(NovaRequest $request): array
    {
        return [new Lenses\MostReported, new Lenses\HardestQuestions, new Lenses\EasiestQuestions, new Lenses\ReadyToPublish, new Lenses\MissingExplanationOrDiagram];
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [
            new ChangeQuestions('publish'),
            new ChangeQuestions('beta'),
            new ChangeQuestionsCarefully('draft'),
            new ChangeQuestionsCarefully('fixed'),
            new ChangeQuestions('difficulty'),
            new ChangeQuestions('chapter'),
            new DuplicateQuestion,
            new ExportQuestionsForTeacher,
            new BulkImportQuestions,
        ];
    }
}
