<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BountyController;
use App\Http\Controllers\TutorialController;
use App\Http\Controllers\CombatChallengeController;
use App\Http\Controllers\CombatController;
use App\Http\Controllers\PacketHijackController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\RigController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\CyberDocController;
use Illuminate\Support\Facades\Route;

// ---------------------------------------------------------------------------
// Auth
// ---------------------------------------------------------------------------
// Token issuance is intentionally unauthenticated — it's how the engine
// obtains its bearer token in the first place.
// Throttled to 10 attempts per minute to prevent brute-force credential attacks.

Route::post('/auth/token', [AuthController::class, 'token'])
    ->middleware('throttle:10,1');

// Map read data is public — no auth required for world data
// Returns all 228 canvas nodes in one shot; useMapData.js calls this once on boot.
Route::get('/nodes', [NodeController::class, 'all']);

Route::middleware('auth:sanctum')->group(function () {

// ---------------------------------------------------------------------------
// Rig Management
// ---------------------------------------------------------------------------

    Route::get('/rig',                  [RigController::class, 'show']);
    Route::post('/rig/damage',          [RigController::class, 'damage']);
    Route::post('/rig/upgrade',         [RigController::class, 'upgrade']);
    Route::post('/rig/chassis-upgrade', [RigController::class, 'chassisUpgrade']);
    // NOTE: /rig/repair intentionally removed — free repair with no cost check.
    // All player-facing SS restoration goes through POST /api/cyberdoc/repair.

// ---------------------------------------------------------------------------
// Player Status & Actions
// ---------------------------------------------------------------------------

    Route::get('/player/me',                      [PlayerController::class, 'me']);
    Route::get('/player/commands',                [PlayerController::class, 'commands']);
    Route::get('/player/inventory',               [PlayerController::class, 'inventory']);
    Route::post('/player/activate-command',       [PlayerController::class, 'activateCommand']);
    Route::get('/player/{player_id}/status',      [PlayerController::class, 'status']);
    Route::post('/player/{player_id}/extract',    [BountyController::class, 'extract']);

// ---------------------------------------------------------------------------
// Map Data
// ---------------------------------------------------------------------------

    Route::get('/nodes/{canvasId}/players',  [NodeController::class, 'players']);
    Route::get('/nodes/{canvasId}/traces',   [NodeController::class, 'traces']);
    Route::post('/nodes/{nodeId}/trace',     [NodeController::class, 'storeTrace']);
    // deplete: 60/min — generous enough for normal play, blocks scripted hack loops
    Route::post('/nodes/{nodeId}/deplete',   [NodeController::class, 'deplete'])
        ->middleware('throttle:60,1');

// ---------------------------------------------------------------------------
// Player Position
// ---------------------------------------------------------------------------

    // position: 120/min — one per 0.5s; covers fast movement without allowing floods
    Route::post('/player/position', [PlayerController::class, 'position'])
        ->middleware('throttle:120,1');

    // heartbeat: 10/min — one every 45s is the expected rate; burst allowed for sendBeacon
    Route::post('/player/heartbeat', [PlayerController::class, 'heartbeat'])
        ->middleware('throttle:10,1');

// ---------------------------------------------------------------------------
// Combat
// ---------------------------------------------------------------------------

    Route::post('/combat/result',                         [CombatController::class, 'result']);
    Route::get('/combat/result/{id}',                     [CombatController::class, 'getResult']);
    Route::post('/combat/challenge',                      [CombatChallengeController::class, 'challenge']);
    Route::get('/combat/pending',                         [CombatChallengeController::class, 'pending']);
    Route::get('/combat/challenge/{id}/status',           [CombatChallengeController::class, 'status']);
    Route::post('/combat/challenge/{id}/accept',          [CombatChallengeController::class, 'accept']);
    Route::post('/combat/challenge/{id}/decline',         [CombatChallengeController::class, 'decline']);

// ---------------------------------------------------------------------------
// Packet Hijack
// ---------------------------------------------------------------------------

    // command: 120/min — generous to cover fast typing; blocks scripted flooding
    Route::post('/packet-hijack/{match}/command',  [PacketHijackController::class, 'command'])
        ->middleware('throttle:120,1');
    Route::post('/packet-hijack/{match}/transfer', [PacketHijackController::class, 'transfer']);
    Route::get('/packet-hijack/{match}/state',     [PacketHijackController::class, 'state']);

// ---------------------------------------------------------------------------
// Leaderboards
// ---------------------------------------------------------------------------

    Route::get('/leaderboard/bounty',      [BountyController::class, 'bountyLeaderboard']);
    Route::get('/leaderboard/open-season', [BountyController::class, 'openSeasonHallOfFame']);

// ---------------------------------------------------------------------------
// CyberDoc
// ---------------------------------------------------------------------------

    Route::post('/cyberdoc/visit',           [CyberDocController::class, 'visit']);
    Route::post('/cyberdoc/bank',            [CyberDocController::class, 'bank']);
    Route::post('/cyberdoc/repair',          [CyberDocController::class, 'repair']);
    Route::post('/cyberdoc/install',         [CyberDocController::class, 'install']);
    Route::post('/cyberdoc/loadout',         [CyberDocController::class, 'loadout']);
    Route::post('/cyberdoc/reallocate',      [CyberDocController::class, 'reallocate']);
    Route::post('/cyberdoc/upgrade-command', [CyberDocController::class, 'upgradeCommand']);

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

    Route::get('/store/catalog',                  [StoreController::class, 'catalog']);
    Route::post('/store/purchase-peripheral',     [StoreController::class, 'purchasePeripheral']);
    Route::post('/store/purchase-consumable',     [StoreController::class, 'purchaseConsumable']);
    Route::post('/store/purchase-command',        [StoreController::class, 'purchaseCommand']);

// ---------------------------------------------------------------------------
// Tutorial
// ---------------------------------------------------------------------------

    // Credits quest rewards directly to wallet_creds (safe — not stealable in PvP).
    // Throttled to prevent reward farming; a new player completes at most 3 quests.
    Route::post('/tutorial/reward', [TutorialController::class, 'reward'])
        ->middleware('throttle:10,1');

// ---------------------------------------------------------------------------
// Inventory
// ---------------------------------------------------------------------------

    Route::get('/inventory',      [InventoryController::class, 'index']);
    Route::post('/inventory/use', [InventoryController::class, 'use']);

});
