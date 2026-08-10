<?php

use App\Http\Controllers\AuthController;
use App\Http\Controllers\BountyController;
use App\Http\Controllers\TutorialController;
use App\Http\Controllers\CombatChallengeController;
use App\Http\Controllers\PacketHijackController;
use App\Http\Controllers\InventoryController;
use App\Http\Controllers\NodeController;
use App\Http\Controllers\PlayerController;
use App\Http\Controllers\PersonaController;
use App\Http\Controllers\QuestController;
use App\Http\Controllers\WatcherController;
use App\Http\Controllers\RigController;
use App\Http\Controllers\StoreController;
use App\Http\Controllers\CyberDocController;
use App\Http\Controllers\DocChatController;
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
    // upgrade/chassis-upgrade: 20/min — a player can invest several points in a session
    // but bursting faster than once per 3s is never legitimate
    Route::post('/rig/upgrade',         [RigController::class, 'upgrade'])
        ->middleware('throttle:20,1');
    Route::post('/rig/chassis-upgrade', [RigController::class, 'chassisUpgrade'])
        ->middleware('throttle:5,1');
    Route::post('/rig/repair',          [RigController::class, 'repair']);

// ---------------------------------------------------------------------------
// Player Status & Actions
// ---------------------------------------------------------------------------

    Route::get('/player/me',                      [PlayerController::class, 'me']);
    Route::post('/player/persona',                [PersonaController::class, 'store']);
    Route::get('/player/commands',                [PlayerController::class, 'commands']);
    Route::get('/player/inventory',               [PlayerController::class, 'inventory']);
    Route::post('/player/activate-command',       [PlayerController::class, 'activateCommand']);
    Route::get('/player/{player_id}/status',      [PlayerController::class, 'status']);
    Route::post('/player/{player_id}/extract',    [BountyController::class, 'extract']);

// ---------------------------------------------------------------------------
// Map Data
// ---------------------------------------------------------------------------

    Route::get('/nodes/{canvasId}/players',     [NodeController::class, 'players']);
    Route::get('/nodes/{canvasId}/traces',      [NodeController::class, 'traces']);
    Route::post('/nodes/{nodeId}/trace',        [NodeController::class, 'storeTrace']);
    Route::post('/nodes/{canvasId}/place-trap',  [NodeController::class, 'placeTrap']);
    Route::post('/nodes/{canvasId}/place-decoy', [NodeController::class, 'placeDecoy']);
    Route::get('/player/traps',                 [NodeController::class, 'myTraps']);
    // deplete: 60/min — generous enough for normal play, blocks scripted hack loops
    Route::post('/nodes/{nodeId}/deplete',   [NodeController::class, 'deplete'])
        ->middleware('throttle:60,1');

// ---------------------------------------------------------------------------
// Player Position
// ---------------------------------------------------------------------------

    // position: 120/min — one per 0.5s; covers fast movement without allowing floods
    Route::post('/player/position', [PlayerController::class, 'position'])
        ->middleware('throttle:120,1');

    // heartbeat: 30/min — one every 45s is the expected rate; generous burst for sendBeacon
    //            and rapid page reloads during dev/testing without false throttling
    Route::post('/player/heartbeat', [PlayerController::class, 'heartbeat'])
        ->middleware('throttle:30,1');

// ---------------------------------------------------------------------------
// Combat
// ---------------------------------------------------------------------------

    // challenge: 10/min — one challenge per node encounter; burst headroom for fast play
    Route::post('/combat/challenge',                      [CombatChallengeController::class, 'challenge'])
        ->middleware('throttle:10,1');
    // pending: 60/min — polled every 2s; 60 gives 30s of headroom without 429s
    Route::get('/combat/pending',                         [CombatChallengeController::class, 'pending'])
        ->middleware('throttle:60,1');
    // status: 60/min — same cadence as pending poll
    Route::get('/combat/challenge/{id}/status',           [CombatChallengeController::class, 'status'])
        ->middleware('throttle:60,1');
    // accept/decline: 10/min — one action per challenge; any faster is a UI bug or flood
    Route::post('/combat/challenge/{id}/accept',          [CombatChallengeController::class, 'accept'])
        ->middleware('throttle:10,1');
    Route::post('/combat/challenge/{id}/decline',         [CombatChallengeController::class, 'decline'])
        ->middleware('throttle:10,1');

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

    // visit/bank/repair: 10/min — once per node arrival; any faster is a UI bug or replay
    Route::post('/cyberdoc/visit',           [CyberDocController::class, 'visit'])
        ->middleware('throttle:10,1');
    Route::post('/cyberdoc/bank',            [CyberDocController::class, 'bank'])
        ->middleware('throttle:10,1');
    Route::post('/cyberdoc/repair',          [CyberDocController::class, 'repair'])
        ->middleware('throttle:10,1');
    // install: 20/min — players may slot multiple peripherals in one visit
    Route::post('/cyberdoc/install',         [CyberDocController::class, 'install'])
        ->middleware('throttle:20,1');
    Route::post('/cyberdoc/loadout',         [CyberDocController::class, 'loadout']);
    Route::post('/cyberdoc/reallocate',      [CyberDocController::class, 'reallocate'])
        ->middleware('throttle:20,1');
    // upgrade-command: 20/min — same cadence as stat upgrades
    Route::post('/cyberdoc/upgrade-command', [CyberDocController::class, 'upgradeCommand'])
        ->middleware('throttle:20,1');

// ---------------------------------------------------------------------------
// DOC Chat — per-hub player rooms. One isolated room per CyberDoc, gated to
// players physically standing on that hub's node. Enabled on Knuckle's page
// only for now (CyberDocKnuckle.vue); other docs opt in the same way later.
// ---------------------------------------------------------------------------

    // messages (read): 30/min — panel fetches history on open, not polled
    Route::get('/doc-chat/{hubCanvasId}/messages',  [DocChatController::class, 'index'])
        ->middleware('throttle:30,1');
    // messages (post): 20/min — real conversation cadence, still blocks flood/spam scripts
    Route::post('/doc-chat/{hubCanvasId}/messages', [DocChatController::class, 'store'])
        ->middleware('throttle:20,1');

// ---------------------------------------------------------------------------
// Store
// ---------------------------------------------------------------------------

    Route::get('/store/catalog',                  [StoreController::class, 'catalog']);
    // purchase-*: 10/min — one purchase every 6s is already fast for legitimate play;
    // blocks double-tap floods and scripted purchase loops
    Route::post('/store/purchase-peripheral',     [StoreController::class, 'purchasePeripheral'])
        ->middleware('throttle:10,1');
    Route::post('/store/purchase-consumable',     [StoreController::class, 'purchaseConsumable'])
        ->middleware('throttle:10,1');
    Route::post('/store/purchase-command',        [StoreController::class, 'purchaseCommand'])
        ->middleware('throttle:10,1');

// ---------------------------------------------------------------------------
// Tutorial
// ---------------------------------------------------------------------------

    // Tutorial state — persisted server-side so it survives browser clears and
    // can be reset via player:reset. Client is still source of truth for UI logic.
    Route::get('/tutorial/state',   [TutorialController::class, 'state']);
    Route::patch('/tutorial/state', [TutorialController::class, 'updateState']);

    // Credits quest rewards directly to wallet_creds (safe — not stealable in PvP).
    // 4 quests max per player; 30/min gives room for dev resets without 429s.
    Route::post('/tutorial/reward', [TutorialController::class, 'reward'])
        ->middleware('throttle:30,1');
    // Fired once when all quests are rewarded — unlocks the entry arc (Knuckle).
    // 20/min so dev resets don't throttle the completion flow.
    Route::post('/tutorial/complete', [TutorialController::class, 'complete'])
        ->middleware('throttle:20,1');

    // Creates a solo practice Packet Hijack match for the tutorial.
    Route::post('/tutorial/packet-hijack/start', [TutorialController::class, 'practiceStart'])
        ->middleware('throttle:10,1');

// ---------------------------------------------------------------------------
// Inventory
// ---------------------------------------------------------------------------

    Route::get('/inventory',      [InventoryController::class, 'index']);
    // inventory/use: 20/min — consumables can be chained quickly but not spammed
    Route::post('/inventory/use', [InventoryController::class, 'use'])
        ->middleware('throttle:20,1');

// ---------------------------------------------------------------------------
// Quests & Reputation
// ---------------------------------------------------------------------------

    Route::get('/quests',                               [QuestController::class, 'index']);
    Route::get('/quests/archive',                       [QuestController::class, 'archive']);
    Route::post('/quests/stage/{stageId}/complete',     [QuestController::class, 'completeStage']);

// ---------------------------------------------------------------------------
// Watcher
// ---------------------------------------------------------------------------

    Route::get('/watcher/unread',   [WatcherController::class, 'unread']);
    Route::get('/watcher/all',      [WatcherController::class, 'all']);
    Route::post('/watcher/read-all',[WatcherController::class, 'readAll']);

});
