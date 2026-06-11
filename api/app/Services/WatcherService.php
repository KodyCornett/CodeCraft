<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerWatcherMessage;
use App\Models\WatcherMessage;
use Illuminate\Support\Carbon;

class WatcherService
{
    public function __construct(
        private readonly QuestLogService $questLogService,
    ) {}

    /**
     * Deliver any Watcher message attached to a completed stage.
     * Called by QuestService::completeStage() after stage completion.
     * Idempotent — if the message was already delivered to this player, skips.
     */
    public function deliverForStage(Player $player, string $stageId): ?PlayerWatcherMessage
    {
        $message = WatcherMessage::where('trigger_stage_id', $stageId)->first();
        if ($message === null) return null;

        // Don't deliver twice
        $existing = PlayerWatcherMessage::where('player_id', $player->id)
            ->where('watcher_message_id', $message->id)
            ->first();
        if ($existing) return null;

        $delivery = PlayerWatcherMessage::create([
            'player_id'          => $player->id,
            'watcher_message_id' => $message->id,
            'delivered_at'       => Carbon::now(),
            'read_at'            => null,
        ]);

        // Write to the archive log
        $resolvedText = $this->resolveTokens($message->signal_text, $player);
        $this->questLogService->logWatcherSignal($player, $resolvedText);

        return $delivery;
    }

    /**
     * Returns all unread Watcher messages for a player, with signal text.
     * Token replacement ({persona}, {persona_desc}) applied here server-side
     * so the frontend receives ready-to-render text.
     */
    public function getUnread(Player $player): array
    {
        return PlayerWatcherMessage::where('player_id', $player->id)
            ->whereNull('read_at')
            ->with('watcherMessage')
            ->orderBy('delivered_at')
            ->get()
            ->map(fn ($pwm) => [
                'id'           => $pwm->id,
                'message_id'   => $pwm->watcher_message_id,
                'signal_text'  => $this->resolveTokens($pwm->watcherMessage->signal_text, $player),
                'delivered_at' => $pwm->delivered_at,
            ])
            ->toArray();
    }

    /**
     * Returns all Watcher messages for a player (read + unread), for the archive channel.
     */
    public function getAll(Player $player): array
    {
        return PlayerWatcherMessage::where('player_id', $player->id)
            ->with('watcherMessage')
            ->orderBy('delivered_at')
            ->get()
            ->map(fn ($pwm) => [
                'id'           => $pwm->id,
                'message_id'   => $pwm->watcher_message_id,
                'signal_text'  => $this->resolveTokens($pwm->watcherMessage->signal_text, $player),
                'delivered_at' => $pwm->delivered_at,
                'read_at'      => $pwm->read_at,
            ])
            ->toArray();
    }

    /**
     * Mark a specific delivered message as read.
     */
    public function markRead(Player $player, string $playerWatcherMessageId): void
    {
        PlayerWatcherMessage::where('id', $playerWatcherMessageId)
            ->where('player_id', $player->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    /**
     * Mark all unread messages as read — called when player opens the Watcher channel.
     */
    public function markAllRead(Player $player): void
    {
        PlayerWatcherMessage::where('player_id', $player->id)
            ->whereNull('read_at')
            ->update(['read_at' => Carbon::now()]);
    }

    /**
     * Returns true if the player has any unread Watcher messages.
     * Used to drive the glitch indicator on the TERMINAL nav button.
     */
    public function hasUnread(Player $player): bool
    {
        return PlayerWatcherMessage::where('player_id', $player->id)
            ->whereNull('read_at')
            ->exists();
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function resolveTokens(string $text, Player $player): string
    {
        return str_replace(
            ['{handle}',       '{persona}',      '{persona_desc}'],
            [$player->handle,  $player->persona, $player->persona_desc],
            $text,
        );
    }
}
