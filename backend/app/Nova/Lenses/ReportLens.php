<?php

namespace App\Nova\Lenses;

use App\Models\NicknameReport;
use App\Models\QuestionReport;
use App\Nova\Fields\Show;
use App\Nova\Filters\FilterSets;
use BackedEnum;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;

/**
 * Shared rules of the report lenses. Used on question reports and nickname reports alike; both
 * keep "open" until a staff member decides.
 */
abstract class ReportLens extends Lens
{
    protected static function open(Builder $query): Builder
    {
        return $query->where('status', 'open');
    }

    /** @return list<Field> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->sortable(),
            Text::make('About', fn (Model $r): string => match (true) {
                $r instanceof QuestionReport => 'Question #'.$r->question_id,
                $r instanceof NicknameReport => 'Nickname "'.$r->nickname.'"',
                default => '—',
            }),
            Text::make('Reason', function (Model $r): string {
                $reason = $r->getAttribute('reason');

                return ucfirst(str_replace('_', ' ', $reason instanceof BackedEnum ? (string) $reason->value : (string) $reason));
            }),
            ...Show::when('Sent', 'created_at'),
        ];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return $this->resource instanceof NicknameReport ? FilterSets::nicknameReports() : FilterSets::questionReports();
    }
}
