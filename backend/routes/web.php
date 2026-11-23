<?php

use App\Http\Controllers\LandingController;
use Illuminate\Support\Facades\Route;

// Public website (Blade, prices live from the admin settings).
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
