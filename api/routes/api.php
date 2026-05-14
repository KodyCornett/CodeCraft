<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BountyController;
use App\Http\Controllers\CombatController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\RigController;
use App\Http\Controllers\StreetDocController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------
// Token issuance is intentionally unauthenticated — it's how the engine
// obtains its bearer token in the first place.

Route::post('/auth/token', [AuthController::class, 'token']);

// Map read data is public — no auth required for world data
Route::get('/nodes/{district}', [NodeController::class, 'byDistrict']);

Route::middleware('auth:sanctum')->group(function () {

// ---------------------------------------------------------------------------
// Rig Management
// ---------------------------------------------------------------------------

    Route::get('/rig',           [RigController::class, 'show']);
    Route::post('/rig/damage',   [RigController::class, 'damage']);
    Route::post('/rig/upgrade',  [RigController::class, 'upgrade']);
    Route::post('/rig/repair',   [RigController::class, 'repair']);

// ---------------------------------------------------------------------------
// Player Status & Actions
// ---------------------------------------------------------------------------

    Route::get('/player/{player_id}/status', [PlayerController::class, 'status']);
    Route::post('/player/{player_id}/extract', [BountyController::class, 'extract']);

// ---------------------------------------------------------------------------
// Map Data
// ---------------------------------------------------------------------------

    Route::post('/nodes/{nodeId}/deplete',   [NodeController::class, 'deplete']);

// ---------------------------------------------------------------------------
// Combat
// ---------------------------------------------------------------------------

    Route::post('/combat/result', [CombatController::class, 'result']);

// ---------------------------------------------------------------------------
// Leaderboards
// ---------------------------------------------------------------------------

    Route::get('/leaderboard/bounty',      [BountyController::class, 'bountyLeaderboard']);
    Route::get('/leaderboard/open-season', [BountyController::class, 'openSeasonHallOfFame']);

// ---------------------------------------------------------------------------
// Street Doc
// ---------------------------------------------------------------------------

    Route::post('/street-doc/visit',      [StreetDocController::class, 'visit']);
    Route::post('/street-doc/repair',     [StreetDocController::class, 'repair']);
    Route::post('/street-doc/install',    [StreetDocController::class, 'install']);
    Route::post('/street-doc/loadout',    [StreetDocController::class, 'loadout']);
    Route::post('/street-doc/reallocate', [StreetDocController::class, 'reallocate']);

});
