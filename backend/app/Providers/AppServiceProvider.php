<?php

namespace App\Providers;

use App\Contracts\StaffNotifier;
use App\Enums\UserRole;
use App\Models\User;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\ExplanationProvider;
use App\Services\Auth\GoogleIdTokenVerifier;
use App\Services\Auth\JwksGoogleIdTokenVerifier;
use App\Services\Ops\BackupService;
use App\Services\Ops\LogStaffNotifier;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Middleware\TrustProxies;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Str;
use Laravel\Fortify\Fortify;
use Laravel\Passkeys\Passkeys;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Fortify and Passkeys are only used by Nova (staff sign-in, 2FA). Their own public pages
        // (sign up, password reset, passkey login) must not exist.
        Fortify::ignoreRoutes();
        Passkeys::ignoreRoutes();
        // Nova's service provider swaps this for bell notifications when Nova is installed.
        $this->app->singletonIf(StaffNotifier::class, LogStaffNotifier::class);
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

        self::trustConfiguredProxies();

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(120)->by($request->user()?->id ?: $request->ip()));
        RateLimiter::for('auth', fn (Request $request) => Limit::perMinute(10)->by($request->ip()));
        // Staff sign-in (Nova, through config/fortify.php): 5 tries a minute per email and address,
        // and 20 a minute per address across all emails.
        RateLimiter::for('login', fn (Request $request) => [
            Limit::perMinute(5)->by(Str::lower(trim((string) $request->input('email'))).'|'.$request->ip()),
            Limit::perMinute(20)->by((string) $request->ip()),
        ]);
        // The two-step code after the password: 5 tries a minute per sign-in.
        RateLimiter::for('two-factor', fn (Request $request) => Limit::perMinute(5)->by(
            (string) ($request->hasSession() ? $request->session()->get('login.id') : null).'|'.$request->ip()));

        // Staff roles. Every action they take is written to audit_logs.
        Gate::define('manage-premium', fn (User $user): bool => $user->hasRole(UserRole::SuperAdmin, UserRole::PaymentsHelper));
        Gate::define('manage-content', fn (User $user): bool => $user->hasRole(UserRole::SuperAdmin, UserRole::ContentChecker));
        Gate::define('moderate', fn (User $user): bool => $user->hasRole(UserRole::SuperAdmin, UserRole::ContentChecker));
        Gate::define('manage-settings', fn (User $user): bool => $user->hasRole(UserRole::SuperAdmin));
        Gate::define('view-students', fn (User $user): bool => $user->hasRole(UserRole::SuperAdmin, UserRole::PaymentsHelper));
    }

    /** Behind a proxy or Cloudflare: TRUSTED_PROXIES in .env (config guul.trusted_proxies). */
    public static function trustConfiguredProxies(): void
    {
        $proxies = trim((string) config('guul.trusted_proxies'));
        if ($proxies !== '') {
            TrustProxies::at($proxies === '*' ? '*' : array_values(array_filter(array_map('trim', explode(',', $proxies)))));
        }
    }
}
