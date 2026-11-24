<?php

namespace App\Nova\Resources;

use App\Enums\UserRole;
use App\Models\Attempt;
use App\Models\AttemptAnswer;
use App\Models\Referral;
use App\Models\User;
use App\Nova\Actions\EndPremium;
use App\Nova\Actions\ExportStudents;
use App\Nova\Actions\ExtendPremium;
use App\Nova\Actions\GrantPremium;
use App\Nova\Actions\LeaderboardBlock;
use App\Nova\Actions\ResetNickname;
use App\Nova\Actions\UndoLastPremium;
use App\Nova\Fields\Show;
use App\Nova\Filters\FilterSets;
use App\Nova\Lenses;
use App\Services\PremiumService;
use App\Services\StudentIdGenerator;
use Illuminate\Contracts\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Laravel\Nova\Actions\Action;
use Laravel\Nova\Fields\Badge;
use Laravel\Nova\Fields\Boolean;
use Laravel\Nova\Fields\Field;
use Laravel\Nova\Fields\HasMany;
use Laravel\Nova\Fields\ID;
use Laravel\Nova\Fields\Number;
use Laravel\Nova\Fields\Text;
use Laravel\Nova\Filters\Filter;
use Laravel\Nova\Http\Requests\NovaRequest;
use Laravel\Nova\Lenses\Lens;
use Laravel\Nova\Panel;

/**
 * Students. Helpers find a student by the student ID the student sends with the payment proof.
 *
 * @extends \App\Nova\Resources\Resource<User>
 */
class Student extends Resource
{
    /** @var class-string<User> */
    public static $model = User::class;

    public static $title = 'student_id';

    public function subtitle(): ?string
    {
        $row = $this->resource;
        if ($row === null) {
            return null;
        }

        return 'Grade '.$row->grade.' · '.self::words($row->stream).' · '.($row->nickname ?? 'no nickname');
    }

    /** @var array<int, string> */
    public static $search = ['student_id', 'nickname', 'email'];

    public static string $ability = 'view-students';

    /** @var list<string> */
    public static $with = ['currentPremiumGrants'];

    public static function label(): string
    {
        return 'Students';
    }

    public static function uriKey(): string
    {
        return 'students';
    }

    public static function indexQuery(NovaRequest $request, Builder $query): Builder
    {
        return $query->where('role', UserRole::Student->value);
    }

    /**
     * "st 7k3q9" finds "ST-7K3Q9". A word that only looks like an ID ("zebra") still finds
     * nicknames too.
     */
    protected static function applySearch(Builder $query, string $search): Builder
    {
        $normalised = StudentIdGenerator::normalise($search);

        if (StudentIdGenerator::isValid($normalised)) {
            return $query->where(fn (Builder $q): Builder => parent::applySearch($q, $search)->orWhere('student_id', $normalised));
        }

        return parent::applySearch($query, $search);
    }

    public static function authorizedToCreate(Request $request): bool
    {
        return false;
    }

    public function authorizedToUpdate(Request $request): bool
    {
        return false;
    }

    public function authorizedToDelete(Request $request): bool
    {
        // Account deletion requests are handled by the super admin only.
        return $request->user()?->can('manage-settings') ?? false;
    }

    /** @return array<int, Field|Panel> */
    public function fields(NovaRequest $request): array
    {
        $until = fn (User $user): ?Carbon => PremiumService::untilFromGrants($user->currentPremiumGrants, Carbon::now());

        return [
            new Panel('Profile', [
                ID::make()->hideFromIndex(),
                Text::make('Student ID', 'student_id')->sortable()->copyable(),
                Text::make('Nickname')->sortable()->help('Shown to other students on the leaderboard and in battles.'),
                Boolean::make('Signed in', fn (User $user): bool => $user->google_sub !== null)
                    ->help('Signed in with Google, so progress moves to a new phone.'),
                Number::make('Grade')->sortable(),
                Text::make('Stream')->displayUsing(fn (?string $v): string => $v !== null ? ucfirst($v) : '—')->sortable(),
                Text::make('Region')->displayUsing(fn (?string $v): string => $v !== null ? ucfirst(str_replace('_', ' ', $v)) : '—')->sortable(),
                Text::make('City')->hideFromIndex(),
                Text::make('Gender', fn (User $user): string => $user->gender !== null ? ucfirst($user->gender->value) : 'Not given')->hideFromIndex(),
                Boolean::make('Retaker', 'is_retaker')->hideFromIndex(),
                Number::make('Target score', 'target_score')->hideFromIndex(),
                Boolean::make('Leaderboard blocked', fn (User $user): bool => $user->leaderboard_blocked_at !== null)->onlyOnDetail(),
                ...Show::when('Joined', 'created_at'),
                ...Show::when('Last active', 'last_active_at'),
            ]),
            new Panel('Premium', [
                Badge::make('Plan', fn (User $user): string => $until($user) !== null ? 'Premium' : 'Free')
                    ->map(['Free' => 'info', 'Premium' => 'success'])->withIcons(),
                Text::make('Premium until', fn (User $user): string => $until($user)?->setTimezone(Show::TIMEZONE)->format('j M Y') ?? '—'),
            ]),
            new Panel('Progress', [
                Number::make('Answers', fn (User $user): int => self::answers($user))->onlyOnDetail(),
                Text::make('Right answers', fn (User $user): string => Show::percent(self::answers($user, true), self::answers($user)))->onlyOnDetail(),
                Number::make('Exams finished', fn (User $user): int => Attempt::query()->where('user_id', $user->id)->where('mode', 'like', 'exam%')->count())->onlyOnDetail(),
            ]),
            new Panel('Referrals', [
                Text::make('Invite code', fn (User $user): string => (string) (User::query()->whereKey($user->id)->value('referral_code') ?? '—'))->onlyOnDetail(),
                Text::make('Friends invited', fn (User $user): string => self::referralSummary($user))->onlyOnDetail(),
            ]),
            new Panel('Reports', [
                Number::make('Reports sent', fn (User $user): int => $user->questionReports()->count())->onlyOnDetail()
                    ->help('"Report a problem" messages this student sent about questions.'),
                Number::make('Nickname reports about them', fn (User $user): int => $user->nicknameReportsAbout()->count())->onlyOnDetail(),
            ]),
            HasMany::make('Premium grants', 'premiumGrants', PremiumGrant::class),
            HasMany::make('Payment requests', 'paymentRequests', PaymentRequest::class),
            HasMany::make('Activity', 'auditLogs', AuditLog::class),
        ];
    }

    private static function answers(User $user, bool $onlyRight = false): int
    {
        return AttemptAnswer::query()
            ->whereIn('attempt_id', Attempt::query()->where('user_id', $user->id)->select('id'))
            ->when($onlyRight, fn ($q) => $q->where('is_correct', true))
            ->count();
    }

    private static function referralSummary(User $user): string
    {
        $valid = $user->referralsMade()->where('status', Referral::VALID)->count();
        $all = $user->referralsMade()->count();

        return $all === 0 ? 'None' : "{$all} invited, {$valid} counted";
    }

    /** @return array<int, Action> */
    public function actions(NovaRequest $request): array
    {
        return [
            GrantPremium::make()
                ->canSee(fn ($request): bool => $request->user()?->can('manage-premium') ?? false)
                ->canRun(fn ($request): bool => $request->user()?->can('manage-premium') ?? false),
            new ExtendPremium,
            new EndPremium,
            new UndoLastPremium,
            new ResetNickname,
            new LeaderboardBlock(true),
            new LeaderboardBlock(false),
            new ExportStudents,
        ];
    }

    /** @return array<int, Filter> */
    public function filters(NovaRequest $request): array
    {
        return FilterSets::students();
    }

    /** @return array<int, Lens> */
    public function lenses(NovaRequest $request): array
    {
        return [new Lenses\PremiumEndsSoon, new Lenses\MostActiveThisWeek, new Lenses\InactiveStudents, new Lenses\NewToday];
    }
}
