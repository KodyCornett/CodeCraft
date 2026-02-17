<?php

use App\Http\Controllers\DesktopController;
use App\Http\Controllers\Api\TerminalController;
use App\Http\Controllers\Api\FilesystemController;
use App\Http\Controllers\Api\BrowserController;
use App\Http\Controllers\Api\MessagesController;
use App\Http\Controllers\Api\SentinelController;
use App\Http\Controllers\Api\FirewallController;
use App\Http\Controllers\Api\MissionController;
use Illuminate\Support\Facades\Route;

// Desktop UI
Route::get('/', [DesktopController::class, 'index'])->name('desktop');

// API Routes (using web middleware for CSRF protection)
Route::prefix('api')->group(function () {
    Route::post('/terminal', [TerminalController::class, 'execute']);
    Route::get('/network-state', [TerminalController::class, 'networkState']);
    Route::post('/filesystem', [FilesystemController::class, 'handle']);

    // Browser
    Route::get('/browser/bookmarks', [BrowserController::class, 'bookmarks']);
    Route::post('/browser/navigate', [BrowserController::class, 'navigate']);

    // Messages
    Route::get('/messages', [MessagesController::class, 'index']);
    Route::get('/messages/unread-count', [MessagesController::class, 'unreadCount']);
    Route::get('/messages/contacts', [MessagesController::class, 'contacts']);
    Route::get('/messages/{id}', [MessagesController::class, 'show'])->where('id', '[0-9]+');
    Route::post('/messages/{id}/read', [MessagesController::class, 'markRead'])->where('id', '[0-9]+');
    Route::post('/messages/{id}/delete', [MessagesController::class, 'delete'])->where('id', '[0-9]+');
    Route::post('/messages/{id}/restore', [MessagesController::class, 'restore'])->where('id', '[0-9]+');
    Route::post('/messages/{id}/decrypt', [MessagesController::class, 'decrypt'])->where('id', '[0-9]+');
    Route::post('/messages/send', [MessagesController::class, 'send']);

    // Jobs (for secure channel)
    Route::get('/jobs/{id}', [MessagesController::class, 'getJob']);
    Route::post('/jobs/{id}/negotiate', [MessagesController::class, 'negotiateJob']);
    Route::post('/jobs/{id}/accept', [MessagesController::class, 'acceptJob']);
    Route::post('/jobs/{id}/verify-puzzle', [MessagesController::class, 'verifyPuzzle']);

    // Sentinel (Security Monitor)
    Route::get('/sentinel/status', [SentinelController::class, 'status']);
    Route::get('/sentinel/events', [SentinelController::class, 'events']);
    Route::post('/sentinel/block', [SentinelController::class, 'block']);
    Route::post('/sentinel/trace', [SentinelController::class, 'trace']);
    Route::post('/sentinel/counter-hack', [SentinelController::class, 'counterHack']);

    // Firewall
    Route::get('/firewall/status', [FirewallController::class, 'status']);
    Route::post('/firewall/repair', [FirewallController::class, 'repair']);
    Route::post('/firewall/activate', [FirewallController::class, 'activate']);

    // Missions
    Route::get('/mission/active', [MissionController::class, 'getActiveMission']);
    Route::post('/mission/complete', [MissionController::class, 'completeMission']);
    Route::post('/mission/abandon', [MissionController::class, 'abandonMission']);
    Route::post('/mission/{missionId}/accept', [MissionController::class, 'acceptMission']);
    Route::post('/mission/{missionId}/decrypt', [MissionController::class, 'decrypt']);
    Route::get('/missions/available', [MissionController::class, 'getAvailableMissions']);
    Route::get('/jobs/offers', [MissionController::class, 'getJobOffers']);
});
