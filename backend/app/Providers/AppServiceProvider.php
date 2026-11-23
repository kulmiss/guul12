<?php

namespace App\Providers;

use App\Enums\UserRole;
use App\Models\User;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\ExplanationProvider;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Services\Auth\JwksGoogleIdTokenVerifier;
use App\Services\Ops\BackupService;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(GoogleIdTokenVerifier::class, function (): GoogleIdTokenVerifier {
            /** @var list<string> $audiences */
            $audiences = config('services.google.client_ids', []);

            return new JwksGoogleIdTokenVerifier($audiences);
        });

        // Reads storage/app/backups settings from config/guul.php each time (tests change them).
        $this->app->bind(BackupService::class, fn (): BackupService => BackupService::fromConfig());

        $this->app->bind(ExplanationProvider::class, fn (): ExplanationProvider => new AnthropicProvider(
            is_string(config('services.anthropic.key')) ? (string) config('services.anthropic.key') : null,
            (string) config('services.anthropic.model'),
            (float) config('services.anthropic.price_in_per_mtok'),
            (float) config('services.anthropic.price_out_per_mtok'),
        ));
    }

    public function boot(): void
    {
        Model::shouldBeStrict(! $this->app->isProduction());

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));

        // Staff roles. Every action they take is written to audit_logs.
        Gate::define('manage-premium', fn (User $user): bool => $user->hasRole(UserRole::SuperAdmin, UserRole::PaymentsHelper));
        Gate::define('manage-content', fn (User $user): bool => $user->hasRole(UserRole::SuperAdmin, UserRole::ContentChecker));
        Gate::define('moderate', fn (User $user): bool => $user->hasRole(UserRole::SuperAdmin, UserRole::ContentChecker));
        Gate::define('manage-settings', fn (User $user): bool => $user->hasRole(UserRole::SuperAdmin));
        Gate::define('view-students', fn (User $user): bool => $user->hasRole(UserRole::SuperAdmin, UserRole::PaymentsHelper));
    }
}
