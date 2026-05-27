<?php

use App\Models\CombatChallenge;
use App\Models\Player;
use App\Services\RigService;
use Illuminate\Support\Facades\Broadcast;

/*
|--------------------------------------------------------------------------
| Broadcast Channels
|--------------------------------------------------------------------------
*/

// Default Laravel user channel
Broadcast::channel('App.Models.User.{id}', function ($user, $id) {
    return (int) $user->id === (int) $id;
});

// Private channel for per-player push events (combat challenges, etc.)
// Auth returns true/false only — no metadata needed.
Broadcast::channel('player.{playerId}', function ($user, string $playerId) {
    $player = Player::where('user_id', $user->id)->value('id');
    return $player === $playerId;
});

// Presence channel for node co-location — who is standing on this node right now.
// The returned array becomes the member object visible to all channel subscribers
// via here(), joining(), and leaving() on the frontend.
Broadcast::channel('node.{canvasId}', function ($user, string $canvasId) {
    $player = Player::where('user_id', $user->id)
        ->with(['rig.chassis', 'playerPeripherals.peripheral'])
        ->first();

    if ($player === null) return false;

    $effectiveFirewall = 1;
    if ($player->rig) {
        $effectiveFirewall = app(RigService::class)
            ->effectiveStats($player->rig, $player)['firewall']['effective'];
    }

    return [
        'id'                 => $player->id,
        'handle'             => $player->handle,
        'bounty_level'       => (int)   $player->bounty_level,
        'is_open_season'     => (bool)  $player->is_open_season,
        'pocket_creds'       => (int)   ($player->pocket_creds   ?? 0),
        'bounty_multiplier'  => (float) ($player->bounty_multiplier ?? 1.0),
        'in_combat'          => CombatChallenge::where(function ($q) use ($player) {
            $q->where('challenger_id', $player->id)
              ->orWhere('target_id', $player->id);
        })->whereIn('status', ['pending', 'accepted'])->exists(),
        'effective_firewall' => $effectiveFirewall,
    ];
});
