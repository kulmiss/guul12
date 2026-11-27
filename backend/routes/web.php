<?php

use App\Http\Controllers\HealthController;
use App\Http\Controllers\LandingController;
use App\Http\Controllers\Staff\PreviewController;
use Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse;
use Illuminate\Cookie\Middleware\EncryptCookies;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Session\Middleware\StartSession;
use Illuminate\Support\Facades\Route;
use Illuminate\View\Middleware\ShareErrorsFromSession;

// Public website (Blade, prices live from the admin settings).
// Staff only (checked in the controller): a mock as students see it, opened from Nova.
Route::get('/staff/preview/mocks/{mock}', [PreviewController::class, 'mock'])->name('staff.preview.mock');

// The public pages have no forms and no sign-in: no session and no cookies, so a visit stores
// nothing about the visitor (not even the address).
Route::withoutMiddleware([EncryptCookies::class, AddQueuedCookiesToResponse::class, StartSession::class, ShareErrorsFromSession::class, ValidateCsrfToken::class])
    ->group(function (): void {
        Route::get('/', [LandingController::class, 'home'])->name('landing.home');
        Route::get('/app', [LandingController::class, 'app'])->name('landing.app');
        Route::get('/premium', [LandingController::class, 'premium'])->name('landing.premium');

        // Draft translations (Somali, Amharic) of the same three pages.
        Route::prefix('{locale}')->whereIn('locale', LandingController::DRAFT_LOCALES)->group(function (): void {
            Route::get('/', [LandingController::class, 'home'])->name('landing.home.locale');
            Route::get('/app', [LandingController::class, 'app'])->name('landing.app.locale');
            Route::get('/premium', [LandingController::class, 'premium'])->name('landing.premium.locale');
        });

        // Legal pages (English). Linked from the app and the Play listing; no prices, no payment links.
        Route::get('/privacy', [LandingController::class, 'legal'])->defaults('page', 'privacy')->name('landing.privacy');
        Route::get('/terms', [LandingController::class, 'legal'])->defaults('page', 'terms')->name('landing.terms');
        Route::get('/delete-account', [LandingController::class, 'legal'])->defaults('page', 'delete')->name('landing.delete');

        Route::get('/sitemap.xml', [LandingController::class, 'sitemap']);

        // For an outside uptime monitor (database check; no details).
        Route::get('/health', HealthController::class)->name('health');
    });
