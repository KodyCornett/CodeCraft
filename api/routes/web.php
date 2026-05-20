<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\RegisterController;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

// ── Auth ──────────────────────────────────────────────────────────────────────

Route::middleware('guest')->group(function () {
    Route::get('/login',  [LoginController::class, 'show'])->name('login');
    Route::get('/register', [RegisterController::class, 'show'])->name('register');
});

// POST auth routes are throttled separately so the GET (show) routes remain fast.
// Login: 10 attempts per minute — prevents brute-force credential attacks.
// Register: 5 new accounts per hour per IP — prevents mass account creation.
Route::middleware(['guest', 'throttle:10,1'])->group(function () {
    Route::post('/login', [LoginController::class, 'store']);
});

Route::middleware(['guest', 'throttle:5,60'])->group(function () {
    Route::post('/register', [RegisterController::class, 'store']);
});

Route::post('/logout', [LoginController::class, 'destroy'])
    ->name('logout')
    ->middleware('auth');

// ── Game — requires authentication ───────────────────────────────────────────

Route::middleware('auth')->group(function () {
    Route::get('/', fn () => Inertia::render('Game'));
});
