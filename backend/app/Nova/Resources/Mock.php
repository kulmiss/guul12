<?php

namespace App\Nova\Resources;

use App\Enums\Access;
use App\Models\Mock as MockModel;
use App\Nova\Actions\CheckMock;
use App\Nova\Actions\PreviewMockAsStudent;
use App\Nova\Actions\PublishMock;
use App\Nova\Actions\UnpublishMock;
use App\Nova\Fields\Show;
use App\Nova\Filters\FilterSets;
use App\Nova\Resources\Concerns\ReadOnlyResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\BelongsTo;
use Laravel\Nova\Fields\BelongsToMany;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Fixed mock exams. They come from the content files (content/<grade>/mocks/*.yaml), so they are
 * read-only here: a change made in Nova would be lost at the next content import.
 *
 * @extends \App\Nova\Resources\Resource<MockModel>
 */
class Mock extends Resource
{
    use ReadOnlyResource;

    /** @var class-string<MockModel> */
    public static $model = MockModel::class;

    public static $title = 'title';

    /** @var array<int, string> */
    public static $search = ['title', 'uid'];

    public static string $ability = 'manage-content';

    /** @var list<string> */
    public static $with = ['subject'];

    public static function label(): string
    {
        return 'Mock exams';
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->withCount('questions');
    }

    public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->withCount('questions');
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable()->hideFromIndex(),
            Text::make('Title')->sortable(),
            Text::make('Content ID', 'uid')->hideFromIndex()->copyable()
                ->help('Change mocks in content/<grade>/mocks/*.yaml, then run the content import.'),
            BelongsTo::make('Subject', 'subject', Subject::class)->sortable(),
            Number::make('Grade')->sortable()->displayUsing(fn (mixed $grade): string => 'Grade '.$grade),
            Number::make('Number')->sortable()->hideFromIndex(),
            Number::make('Questions', 'questions_count')->resolveUsing(fn (mixed $count): int => (int) $count),
            Number::make('Minutes')->displayUsing(fn (mixed $minutes): string => $minutes.' min'),
            Badge::make('Access', fn (MockModel $mock): string => $mock->access->value)
                ->map([Access::Free->value => 'info', Access::Premium->value => 'warning'])
                ->labels([Access::Free->value => 'Free', Access::Premium->value => 'Premium']),
            Badge::make('Shown to students', fn (MockModel $m): string => match (true) {
                $m->unpublished_at !== null => 'unpublished',
                ! $m->is_active => 'not_in_content',
                default => 'shown',
            })->map(['shown' => 'success', 'unpublished' => 'warning', 'not_in_content' => 'danger'])
                ->labels(['shown' => 'Shown', 'unpublished' => 'Unpublished by staff', 'not_in_content' => 'Not in the content files'])
                ->help('"Unpublish" hides a mock until staff publish it again; the content import does not undo it.'),
            ...Show::when('Updated', 'updated_at'),
            BelongsToMany::make('Questions', 'questions', Question::class)
                ->fields(fn (): array => [Number::make('Order', 'sort')]),
        ];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::mocks();
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [new PublishMock, new UnpublishMock, new CheckMock, new PreviewMockAsStudent];
    }
}
