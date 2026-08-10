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
    private const MAX_BODY_LENGTH = 240;
    private const HISTORY_LIMIT   = 50;

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
     * Most recent messages for one hub's room, oldest first.
     */
    public function recentMessages(string $hubCanvasId): array
    {
        return DocChatMessage::where('hub_canvas_id', $hubCanvasId)
            ->orderBy('created_at')
            ->limit(self::HISTORY_LIMIT)
            ->get()
            ->map(fn (DocChatMessage $message) => $this->present($message))
            ->all();
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
        if (mb_strlen($trimmed) > self::MAX_BODY_LENGTH) {
            $trimmed = mb_substr($trimmed, 0, self::MAX_BODY_LENGTH);
        }

        $message = DocChatMessage::create([
            'hub_canvas_id' => $hubCanvasId,
            'player_id'     => $player->id,
            'handle'        => $player->handle,
            'body'          => $trimmed,
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
