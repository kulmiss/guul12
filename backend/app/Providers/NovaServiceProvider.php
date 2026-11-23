<?php

namespace App\Providers;

use App\Models\User;
use App\Nova\Dashboards\Main;
use App\Nova\NovaMenu;
use App\Nova\Resources;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Nova\Dashboard;
use Laravel\Nova\Nova;
use Laravel\Nova\NovaApplicationServiceProvider;

/**
 * Loaded only when Laravel Nova is installed (see bootstrap/providers.php and docs/nova-setup.md).
 */
class NovaServiceProvider extends NovaApplicationServiceProvider
{
    public function boot(): void
    {
        parent::boot();

        Nova::footer(fn (): string => 'Guul 12 admin');
        Nova::mainMenu(fn (Request $request): array => NovaMenu::sections($request));
    }

    protected function routes(): void
    {
        Nova::routes()
            ->withAuthenticationRoutes(default: true)
            ->withPasswordResetRoutes()
            ->register();
    }

    /**
     * Only staff (super admin, payments helper, content checker) can open Nova.
     */
    protected function gate(): void
    {
        Gate::define('viewNova', fn (User $user): bool => $user->isStaff());
    }

    /**
     * @return array<int, Dashboard>
     */
    protected function dashboards(): array
    {
        return [new Main];
    }

    protected function resources(): void
    {
        Nova::resources([
            Resources\Student::class,
            Resources\Staff::class,
            Resources\PremiumGrant::class,
            Resources\PaymentRequest::class,
            Resources\Subject::class,
            Resources\Unit::class,
            Resources\Chapter::class,
            Resources\Question::class,
            Resources\QuestionOption::class,
            Resources\QuestionReport::class,
            Resources\NicknameReport::class,
            Resources\Summary::class,
            Resources\SolvedExercise::class,
            Resources\Flashcard::class,
            Resources\Passage::class,
            Resources\AppSetting::class,
            Resources\FeatureSwitch::class,
            Resources\AuditLog::class,
        ]);
    }
}
