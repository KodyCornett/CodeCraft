<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Fired after any node hack that changes the bounty board state.
 * The frontend listens on the 'bounty-board' channel and re-fetches
 * GET /api/leaderboard/bounty when this arrives — no payload needed.
 */
class BountyBoardUpdated implements ShouldBroadcastNow
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public function broadcastOn(): Channel
    {
        return new Channel('bounty-board');
    }

    public function broadcastAs(): string
    {
        return 'board.updated';
    }
}
