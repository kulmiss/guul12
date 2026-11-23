<?php

namespace App\Nova\Resources;

use App\Enums\NicknameReportReason;
use App\Enums\NicknameReportStatus;
use App\Models\NicknameReport as NicknameReportModel;
use App\Nova\Actions\DismissNicknameReport;
use App\Nova\Actions\LeaderboardBlock;
use App\Nova\Actions\ResetNickname;
use App\Nova\Fields\Show;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\DateTime;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Panel;

/**
 * Nicknames students reported from the leaderboard or a battle (DECISIONS #33).
 * Moderators: Reset nickname (it was bad), Not a problem, or Block from leaderboard.
 *
 * @extends \App\Nova\Resources\Resource<NicknameReportModel>
 */
class NicknameReport extends Resource
{
    /** @var class-string<NicknameReportModel> */
    public static $model = NicknameReportModel::class;

    public static $title = 'nickname';

    /** @var array<int, string> */
    public static $search = ['nickname'];

    public static string $ability = 'moderate';

    /** @var list<string> */
    public static $with = ['reporter', 'reportedUser', 'resolver'];

    public static function label(): string
    {
        return 'Nickname reports';
    }

    public static function singularLabel(): string
    {
        return 'Nickname report';
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        if ($request->filled('orderBy')) {
            return $query;
        }

        // Default order: open reports first, newest first.
        return $query->orderByRaw('case when status = ? then 0 else 1 end', [NicknameReportStatus::Open->value])->orderByDesc('created_at');
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        return [
            ID::make()->hideFromIndex(),
            ...Show::when('Reported', 'created_at'),
            Text::make('Nickname')->sortable(),
            Text::make('Now called', fn (NicknameReportModel $r): string => $r->reportedUser->nickname ?? '— (removed)')
                ->help('The nickname today. It may differ from the reported one.'),
            Text::make('Student ID', fn (NicknameReportModel $r): string => $r->reportedUser->student_id)->copyable(),
            Text::make('Reason', fn (NicknameReportModel $r): string => match ($r->reason) {
                NicknameReportReason::Rude => 'Rude or hurtful',
                NicknameReportReason::Impersonation => 'Pretends to be someone else',
                NicknameReportReason::Other => 'Other',
            }),
            Text::make('Note')->onlyOnDetail(),
            Badge::make('Status', fn (NicknameReportModel $r): string => $r->status->value)->map([
                NicknameReportStatus::Open->value => 'warning',
                NicknameReportStatus::Reset->value => 'danger',
                NicknameReportStatus::Dismissed->value => 'info',
            ])->labels([
                NicknameReportStatus::Open->value => 'Open',
                NicknameReportStatus::Reset->value => 'Nickname removed',
                NicknameReportStatus::Dismissed->value => 'Not a problem',
            ]),
            Text::make('Reported by', fn (NicknameReportModel $r): string => $r->reporter->student_id)->onlyOnDetail(),
            Text::make('Checked by', fn (NicknameReportModel $r): string => $r->resolver->name ?? '—')->onlyOnDetail(),
            DateTime::make('Checked at', 'resolved_at')->onlyOnDetail()->displayUsing(fn ($v): string => Show::full($v)),
        ];
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [new ResetNickname, new DismissNicknameReport, new LeaderboardBlock(true)];
    }
}
