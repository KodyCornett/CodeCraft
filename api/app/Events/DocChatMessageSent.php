<?php

namespace App\Events;

use App\Models\DocChatMessage;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class DocChatMessageSent implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function __construct(public readonly DocChatMessage $message) {}

    public function broadcastOn(): PrivateChannel
    {
        return new PrivateChannel('doc-chat.' . $this->message->hub_canvas_id);
    }

    public function broadcastAs(): string
    {
        return 'doc-chat.message';
    }

    public function broadcastWith(): array
    {
        return [
            'id'         => $this->message->id,
            'player_id'  => $this->message->player_id,
            'handle'     => $this->message->handle,
            'body'       => $this->message->body,
            'created_at' => $this->message->created_at->toIso8601String(),
        ];
    }
}
