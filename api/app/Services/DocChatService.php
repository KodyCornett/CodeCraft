<?php

namespace App\Services;

use App\Events\DocChatMessageSent;
use App\Models\DocChatMessage;
use App\Models\Node;
use App\Models\Player;

/**
 * DocChatService
 *
 * Owns the DOC hub chat rooms — one isolated room per CyberDoc, keyed by the
 * hub's node canvas_id (e.g. 'BA-hub' for Knuckle). A player can only read or
 * post in a room while physically standing on that hub's node; there is no
 * cross-room visibility and no remote access.
 */
class DocChatService
{
    private const MAX_BODY_LENGTH    = 240;
    private const HISTORY_LIMIT      = 50;
    // Short TTL — this is meant to feel like live proximity chatter, not a
    // permanent transcript. Mirrors the expires_at pattern node_traces already
    // uses; expired rows are filtered at read time and opportunistically
    // deleted (see pruneExpired()) rather than via a scheduled job.
    private const RETENTION_MINUTES  = 45;

    public function __construct(
        private readonly ProfanityFilterService $profanityFilter,
    ) {}

    /**
     * True if the given hub_canvas_id is a real CyberDoc node and the player's
     * current_node_id points at it right now. Used to gate both the REST
     * endpoints and the Reverb channel subscription itself.
     */
    public function playerIsAtHub(Player $player, string $hubCanvasId): bool
    {
        $node = Node::where('canvas_id', $hubCanvasId)
            ->where('type', 'cyberdoc')
            ->first();

        if ($node === null) {
            return false;
        }

        return (string) $player->current_node_id === (string) $node->id;
    }

    /**
     * Most recent, unexpired messages for one hub's room, oldest first.
     */
    public function recentMessages(string $hubCanvasId): array
    {
        $this->pruneExpired($hubCanvasId);

        return DocChatMessage::where('hub_canvas_id', $hubCanvasId)
            ->where('expires_at', '>', now())
            ->orderBy('created_at')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->map(fn (DocChatMessage $message) => $this->present($message))
            ->all();
    }

    /**
     * Delete this hub's expired messages. Called on every history fetch
     * instead of a scheduled job — cheap, no extra infra, and it keeps the
     * table from accumulating a permanent log of hub conversations.
     */
    private function pruneExpired(string $hubCanvasId): void
    {
        DocChatMessage::where('hub_canvas_id', $hubCanvasId)
            ->where('expires_at', '<=', now())
            ->delete();
    }

    /**
     * Store and broadcast a new message. Caller (controller) is responsible
     * for confirming playerIsAtHub() first — this method assumes access is
     * already validated.
     */
    public function postMessage(Player $player, string $hubCanvasId, string $body): DocChatMessage
    {
        $trimmed = trim($body);
        if ($trimmed === '') {
            throw new \InvalidArgumentException('Message cannot be empty.');
        }
        if ($this->profanityFilter->containsProfanity($trimmed)) {
            throw new \InvalidArgumentException('Message blocked — contains restricted language.');
        }
        if (mb_strlen($trimmed) > self::MAX_BODY_LENGTH) {
            $trimmed = mb_substr($trimmed, 0, self::MAX_BODY_LENGTH);
        }

        $message = DocChatMessage::create([
            'hub_canvas_id' => $hubCanvasId,
            'player_id'     => $player->id,
            'handle'        => $player->handle,
            'body'          => $trimmed,
            'expires_at'    => now()->addMinutes(self::RETENTION_MINUTES),
        ]);

        broadcast(new DocChatMessageSent($message));

        return $message;
    }

    public function present(DocChatMessage $message): array
    {
        return [
            'id'         => $message->id,
            'player_id'  => $message->player_id,
            'handle'     => $message->handle,
            'body'       => $message->body,
            'created_at' => $message->created_at->toIso8601String(),
        ];
    }
}
