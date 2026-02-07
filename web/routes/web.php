<?php

use App\Http\Controllers\DesktopController;
use App\Http\Controllers\Api\TerminalController;
use App\Http\Controllers\Api\FilesystemController;
use App\Http\Controllers\Api\BrowserController;
use Illuminate\Support\Facades\Route;

// Desktop UI
Route::get('/', [DesktopController::class, 'index'])->name('desktop');

// API Routes (using web middleware for CSRF protection)
Route::prefix('api')->group(function () {
    Route::post('/terminal', [TerminalController::class, 'execute']);
    Route::post('/filesystem', [FilesystemController::class, 'handle']);

    // Browser
    Route::get('/browser/bookmarks', [BrowserController::class, 'bookmarks']);
    Route::post('/browser/navigate', [BrowserController::class, 'navigate']);
});
