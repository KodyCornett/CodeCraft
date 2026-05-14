<?php

namespace App\Services;

use App\Models\Node;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class NodeService
{
    /** Minutes a depleted cred resource takes to replenish. */
    private const CRED_REPLENISH_MINUTES = 60;

    /** Minutes a depleted movement resource takes to replenish. */
    private const MOVEMENT_REPLENISH_MINUTES = 30;

    /**
     * Minutes at which the cred time-multiplier reaches its maximum.
     * Value scales linearly from 1.0× at 0 minutes to MAX_CRED_MULTIPLIER× here.
     */
    private const CRED_SCALE_MINUTES = 240;

    /** Maximum time-based multiplier applied to the base cred value. */
    private const MAX_CRED_MULTIPLIER = 3.0;

    // -------------------------------------------------------------------------

    /**
     * Returns all nodes directly connected to the given node.
     * Connections are stored bidirectionally so this is a single-direction lookup.
     */
    public function getAdjacentNodes(string $nodeId): Collection
    {
        $node = Node::find($nodeId);

        if ($node === null) {
            return collect();
        }

        return $node->adjacentNodes()->get();
    }

    /**
     * Current cred payout for a node.
     *
     * If the node has never been hacked, it returns the base value.
     * After a hack the value recovers linearly, reaching MAX_CRED_MULTIPLIER × base
     * after CRED_SCALE_MINUTES minutes untouched.
     *
     * Formula: base × min(MAX, 1.0 + minutes_untouched / CRED_SCALE_MINUTES × (MAX - 1))
     */
    public function currentCredValue(Node $node): int
    {
        if ($node->cred_last_hacked_at === null) {
            return $node->cred_value_base;
        }

        $minutesUntouched = $node->cred_last_hacked_at->diffInMinutes(now());

        $multiplier = min(
            self::MAX_CRED_MULTIPLIER,
            1.0 + ($minutesUntouched / self::CRED_SCALE_MINUTES) * (self::MAX_CRED_MULTIPLIER - 1.0),
        );

        return (int) round($node->cred_value_base * $multiplier);
    }

    /**
     * Mark a node resource as depleted and record the timestamp.
     *
     * @param  string  $resource  'creds' or 'movement'
     * @throws InvalidArgumentException
     */
    public function depleteResource(Node $node, string $resource): void
    {
        match ($resource) {
            'creds'    => $this->depleteCreds($node),
            'movement' => $this->depleteMovement($node),
            default    => throw new InvalidArgumentException(
                "Invalid resource '{$resource}'. Expected 'creds' or 'movement'."
            ),
        };

        $node->save();
    }

    /**
     * Restore depleted resources on a node if enough time has elapsed.
     * Call this whenever a player accesses a node.
     */
    public function replenishCheck(Node $node): void
    {
        $dirty = false;

        if (
            $node->cred_resource_depleted
            && $node->cred_last_hacked_at !== null
            && $node->cred_last_hacked_at->diffInMinutes(now()) >= self::CRED_REPLENISH_MINUTES
        ) {
            $node->cred_resource_depleted = false;
            $dirty = true;
        }

        if (
            $node->movement_resource_depleted
            && $node->movement_last_hacked_at !== null
            && $node->movement_last_hacked_at->diffInMinutes(now()) >= self::MOVEMENT_REPLENISH_MINUTES
        ) {
            $node->movement_resource_depleted = false;
            $dirty = true;
        }

        if ($dirty) {
            $node->save();
        }
    }

    // -------------------------------------------------------------------------

    private function depleteCreds(Node $node): void
    {
        $node->cred_resource_depleted = true;
        $node->cred_last_hacked_at    = now();
    }

    private function depleteMovement(Node $node): void
    {
        $node->movement_resource_depleted = true;
        $node->movement_last_hacked_at    = now();
    }
}
