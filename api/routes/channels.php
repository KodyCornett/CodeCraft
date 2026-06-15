<?php

use App\Models\CombatChallenge;
use App\Models\PacketHijackMatch;
use App\Models\Player;
use App\Services\BountyService;
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

// Private channel for Packet Hijack match events.
// Both participants (challenger and defender) are authorised.
// All four PH event classes broadcast on this channel.
Broadcast::channel('packet-hijack.{matchId}', function ($user, string $matchId) {
    $player = Player::where('user_id', $user->id)->value('id');
    if ($player === null) return false;

    return PacketHijackMatch::where('id', $matchId)
        ->where(function ($q) use ($player) {
            $q->where('challenger_id', $player)
              ->orWhere('defender_id', $player);
        })
        ->exists();
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
        'bounty_level'       => app(BountyService::class)->hackCountToStarLevel((int) ($player->bounty_level ?? 0)),
        'is_open_season'     => (bool)  $player->is_open_season,
        'pocket_creds'       => (int)   ($player->pocket_creds   ?? 0),
        'bounty_multiplier'  => (float) ($player->bounty_multiplier ?? 1.0),
        'in_combat'          => CombatChallenge::where(function ($q) use ($player) {
            $q->where('challenger_id', $player->id)
              ->orWhere('target_id', $player->id);
        })->where(function ($q) {
            $q->where('status', 'accepted')
              ->orWhere(function ($q2) {
                  $q2->where('status', 'pending')
                     ->where('expires_at', '>', now());
              });
        })->exists(),
        'effective_firewall' => $effectiveFirewall,
    ];
});
