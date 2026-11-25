<?php

namespace App\Nova;

use App\Enums\NicknameReportStatus;
use App\Enums\PaymentRequestStatus;
use App\Enums\ReportStatus;
use App\Models\NicknameReport;
use App\Models\PaymentRequest;
use App\Models\QuestionReport;
use App\Models\User;
use App\Nova\Dashboards\Content;
use App\Nova\Dashboards\Main;
use App\Nova\Dashboards\Payments;
use Illuminate\Http\Request;
use Laravel\Nova\Menu\MenuItem;
use Laravel\Nova\Menu\MenuSection;

/**
 * The left menu: grouped by daily use, each section only for the roles that work there.
 * Nova hides menu items a role cannot open, but not empty section headers, so sections check roles too.
 */
class NovaMenu
{
    /** @return list<MenuSection> */
    public static function sections(Request $request): array
    {
        return [
            MenuSection::make('Dashboards', [
                MenuItem::dashboard(Main::class),
                MenuItem::dashboard(Payments::class)->canSee(self::allowedTo('manage-premium')),
                MenuItem::dashboard(Content::class)->canSee(self::allowedTo('manage-content')),
            ])->icon('chart-bar')->collapsable(),

            MenuSection::make('Students & Premium', [
                MenuItem::resource(Resources\PaymentRequest::class)
                    ->withBadge(fn (): string|false => self::count(PaymentRequest::query()->where('status', PaymentRequestStatus::Waiting->value)->count()), 'warning'),
                MenuItem::resource(Resources\Student::class),
                MenuItem::resource(Resources\PremiumGrant::class),
                MenuItem::resource(Resources\Referral::class),
            ])->icon('users')->collapsable()->canSee(self::allowedTo('manage-premium')),

            MenuSection::make('Content', [
                MenuItem::resource(Resources\QuestionReport::class)
                    ->withBadge(fn (): string|false => self::count(QuestionReport::query()->where('status', ReportStatus::Open->value)->count()), 'warning'),
                MenuItem::resource(Resources\Question::class),
                MenuItem::resource(Resources\Chapter::class),
                MenuItem::resource(Resources\Mock::class),
                MenuItem::resource(Resources\Unit::class),
                MenuItem::resource(Resources\Subject::class),
                MenuItem::resource(Resources\Summary::class),
                MenuItem::resource(Resources\SolvedExercise::class),
                MenuItem::resource(Resources\Flashcard::class),
                MenuItem::resource(Resources\Passage::class),
                MenuItem::resource(Resources\QuestionOption::class),
            ])->icon('book-open')->collapsable()->canSee(self::allowedTo('manage-content')),

            MenuSection::make('Community', [
                MenuItem::resource(Resources\NicknameReport::class)
                    ->withBadge(fn (): string|false => self::count(NicknameReport::query()->where('status', NicknameReportStatus::Open->value)->count()), 'warning'),
                MenuItem::resource(Resources\Battle::class),
            ])->icon('user-group')->collapsable()->canSee(self::allowedTo('moderate')),

            MenuSection::make('Settings', [
                MenuItem::resource(Resources\AppSetting::class),
                MenuItem::resource(Resources\FeatureSwitch::class),
                MenuItem::resource(Resources\LandingReview::class),
                MenuItem::resource(Resources\Staff::class),
                MenuItem::link('Branding', '/branding'),
            ])->icon('cog')->collapsable()->canSee(self::allowedTo('manage-settings')),

            MenuSection::make('System', [
                MenuItem::resource(Resources\AuditLog::class),
            ])->icon('server')->collapsable()->canSee(self::allowedTo('manage-settings')),
        ];
    }

    /** A number for a menu badge, or false to show no badge. */
    private static function count(int $n): string|false
    {
        return $n > 0 ? (string) $n : false;
    }

    /** @return \Closure(Request): bool */
    private static function allowedTo(string $ability): \Closure
    {
        return fn (Request $request): bool => $request->user() instanceof User && $request->user()->can($ability);
    }
}
