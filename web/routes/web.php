<?php

use App\Http\Controllers\DesktopController;
use App\Http\Controllers\Api\TerminalController;
use App\Http\Controllers\Api\FilesystemController;
use App\Http\Controllers\Api\BrowserController;
use App\Http\Controllers\Api\MessagesController;
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

    // Messages
    Route::get('/messages', [MessagesController::class, 'index']);
    Route::get('/messages/unread-count', [MessagesController::class, 'unreadCount']);
    Route::get('/messages/contacts', [MessagesController::class, 'contacts']);
    Route::get('/messages/{id}', [MessagesController::class, 'show']);
    Route::post('/messages/{id}/read', [MessagesController::class, 'markRead']);
    Route::post('/messages/send', [MessagesController::class, 'send']);
});
