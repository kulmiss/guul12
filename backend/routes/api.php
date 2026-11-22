<?php

use App\Http\Controllers\Api\V1\AttemptController;
use App\Http\Controllers\Api\V1\AuthController;
use App\Http\Controllers\Api\V1\BookmarkController;
use App\Http\Controllers\Api\V1\ConfigController;
use App\Http\Controllers\Api\V1\ContentController;
use App\Http\Controllers\Api\V1\HistoryController;
use App\Http\Controllers\Api\V1\MeController;
use App\Http\Controllers\Api\V1\MockController;
use App\Http\Controllers\Api\V1\ReportController;
use Illuminate\Support\Facades\Route;

Route::prefix('v1')->name('api.v1.')->group(function (): void {
    Route::get('config', ConfigController::class)->middleware('throttle:api')->name('config');

    Route::post('auth/device', [AuthController::class, 'device'])->middleware('throttle:auth')->name('auth.device');

    Route::middleware(['auth:sanctum', 'throttle:api'])->group(function (): void {
        Route::post('auth/google', [AuthController::class, 'google'])->middleware('throttle:auth')->name('auth.google');
        Route::post('auth/logout', [AuthController::class, 'logout'])->name('auth.logout');

        Route::get('me', [MeController::class, 'show'])->name('me.show');
        Route::patch('me', [MeController::class, 'update'])->name('me.update');

        Route::get('catalog', [ContentController::class, 'catalog'])->name('catalog');
        Route::get('chapters/{chapter}', [ContentController::class, 'chapter'])->whereNumber('chapter')->name('chapters.show');
        Route::get('sync/changes', [ContentController::class, 'changes'])->name('sync.changes');
        Route::get('sync/pack', [ContentController::class, 'pack'])->name('sync.pack');

        Route::get('mocks', [MockController::class, 'index'])->name('mocks.index');
        Route::get('mocks/{mock}', [MockController::class, 'show'])->whereNumber('mock')->name('mocks.show');

        Route::post('attempts', [AttemptController::class, 'store'])->name('attempts.store');
        Route::get('attempts/{clientUuid}', [AttemptController::class, 'show'])->whereUuid('clientUuid')->name('attempts.show');
        Route::get('history', HistoryController::class)->name('history');

        Route::get('bookmarks', [BookmarkController::class, 'index'])->name('bookmarks.index');
        Route::put('bookmarks/{question}', [BookmarkController::class, 'store'])->whereNumber('question')->name('bookmarks.store');
        Route::delete('bookmarks/{question}', [BookmarkController::class, 'destroy'])->whereNumber('question')->name('bookmarks.destroy');

        Route::post('questions/{question}/reports', [ReportController::class, 'store'])->whereNumber('question')->name('questions.reports.store');
    });
});
