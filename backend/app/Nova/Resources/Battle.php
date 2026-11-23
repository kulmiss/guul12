<?php

namespace App\Nova\Resources;

use App\Models\Battle as BattleModel;
use App\Nova\Fields\Show;
use App\Nova\Filters\FilterSets;
use App\Nova\Resources\Concerns\ReadOnlyResource;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Battles between two students, for moderators. Only nicknames are shown (no student IDs, no
 * matching key, no question list): it is enough to follow a nickname report.
 *
 * @extends \App\Nova\Resources\Resource<BattleModel>
 */
class Battle extends Resource
{
    use ReadOnlyResource;

    /** @var class-string<BattleModel> */
    public static $model = BattleModel::class;

    /** Kept out of the search box at the top: too noisy to be useful there. */
    public static $globallySearchable = false;

    public static $title = 'code';

    /** @var array<int, string> */
    public static $search = ['code'];

    public static string $ability = 'moderate';

    /** @var list<string> */
    public static $with = ['creator', 'opponent', 'subject'];

    private const STATUS = ['waiting' => 'info', 'matched' => 'success', 'expired' => 'warning'];

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return self::withFinished($query);
    }

    public static function detailQuery(NovaRequest $request, Builder $query): Builder
    {
        return self::withFinished($query);
    }

    private static function withFinished(Builder $query): Builder
    {
        // The app saves an attempt only when the player has finished, so every attempt counts.
        return $query->withCount('attempts as finished_count');
    }

    public static function status(BattleModel $battle): string
    {
        if ($battle->opponent_id !== null) {
            return 'matched';
        }

        return $battle->expires_at->isPast() ? 'expired' : 'waiting';
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable()->hideFromIndex(),
            Text::make('Code')->copyable()->help('The code a student shares with a friend to join.'),
            Badge::make('Status', fn (BattleModel $b): string => self::status($b))
                ->map(self::STATUS)
                ->labels(['waiting' => 'Waiting', 'matched' => 'Matched', 'expired' => 'Expired'])
                ->help('Waiting = nobody joined yet · Matched = two players · Expired = nobody joined in time.'),
            Text::make('Type', fn (BattleModel $b): string => $b->is_random ? 'Random opponent' : 'With a friend'),
            Text::make('Subject', fn (BattleModel $b): string => $b->subject->name ?? 'Mixed'),
            Text::make('Creator', fn (BattleModel $b): string => $b->creator->nickname ?? '(no nickname)'),
            Text::make('Opponent', fn (BattleModel $b): string => $b->opponent_id === null ? '—' : ($b->opponent->nickname ?? '(no nickname)')),
            Number::make('Questions', fn (BattleModel $b): int => count($b->question_ids)),
            Number::make('Finished', 'finished_count')->resolveUsing(fn (mixed $count): int => (int) $count)
                ->help('How many of the two players have finished.'),
            ...Show::when('Started', 'created_at'),
            DateTime::make('Expires', 'expires_at')->onlyOnDetail()->displayUsing(fn (mixed $v): string => Show::full($v)),
        ];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::battles();
    }
}
