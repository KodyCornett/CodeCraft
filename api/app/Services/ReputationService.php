<?php

namespace App\Services;

use App\Models\CyberDoc;
use App\Models\Player;
use App\Models\PlayerReputation;

class ReputationService
{
    // ── Tier definitions ──────────────────────────────────────────────────────
    // Each tier: ['label' => string, 'min' => int]
    // Ordered lowest → highest. Used by getRepTier() and getRepLabel().

    public const TIERS = [
        ['label' => 'NULL',      'min' => 0],
        ['label' => 'RESOLVED',  'min' => 250],
        ['label' => 'ROUTED',    'min' => 600],
        ['label' => 'ENCRYPTED', 'min' => 1200],
        ['label' => 'ROOT',      'min' => 2000],
    ];

    // ── Rep event weights ─────────────────────────────────────────────────────
    // How many rep points each event type grants.
    // Amounts are intentionally conservative — adjust as game balance requires.

    public const REP_EVENTS = [
        'quest_stage_complete' => 0,   // set per-stage in quest_stages.rep_reward — not a flat value
        'node_hack_in_district' => 5,
        'pvp_win_in_district'   => 25,
        'packet_hijack_win_in_district' => 20,
        'store_purchase'        => 3,
        // Bounty-on-extract: rep = bounty_level * BOUNTY_EXTRACT_MULTIPLIER
        'bounty_extract'        => 40,  // per bounty star level (multiplied by star count)
    ];

    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Grant rep to a player for a specific CyberDoc.
     * Rep only goes up — never deducted.
     *
     * @param  Player   $player
     * @param  string   $cyberDocId  UUID of the CyberDoc
     * @param  int      $amount      Points to add
     * @return PlayerReputation      Updated record
     */
    public function grantRep(Player $player, string $cyberDocId, int $amount): PlayerReputation
    {
        $rep = PlayerReputation::firstOrCreate(
            ['player_id' => $player->id, 'cyber_doc_id' => $cyberDocId],
            ['score' => 0],
        );

        $rep->increment('score', max(0, $amount));
        $rep->refresh();

        return $rep;
    }

    /**
     * Grant rep for a bounty extract event.
     * $bountyLevel must be captured before bankCreds resets it.
     * Amount = bountyLevel * REP_EVENTS['bounty_extract'].
     */
    public function grantBountyExtractRep(Player $player, string $cyberDocId, int $bountyLevel): PlayerReputation
    {
        $amount = $bountyLevel * self::REP_EVENTS['bounty_extract'];
        return $this->grantRep($player, $cyberDocId, $amount);
    }

    /**
     * Get the current rep score for a player + doc pair.
     * Returns 0 if no record exists yet.
     */
    public function getScore(Player $player, string $cyberDocId): int
    {
        return PlayerReputation::where('player_id', $player->id)
            ->where('cyber_doc_id', $cyberDocId)
            ->value('score') ?? 0;
    }

    /**
     * Return the tier index (0–4) for a given score.
     */
    public function getTierIndex(int $score): int
    {
        $index = 0;
        foreach (self::TIERS as $i => $tier) {
            if ($score >= $tier['min']) {
                $index = $i;
            }
        }
        return $index;
    }

    /**
     * Return the tier label string for a given score.
     * e.g. getRepLabel(300) → 'RESOLVED'
     */
    public function getRepLabel(int $score): string
    {
        return self::TIERS[$this->getTierIndex($score)]['label'];
    }

    /**
     * Return the next tier threshold for a given score, or null if already ROOT.
     */
    public function getNextThreshold(int $score): ?int
    {
        $index = $this->getTierIndex($score);
        return self::TIERS[$index + 1]['min'] ?? null;
    }

    /**
     * Build the full rep state for a player across all docs.
     * Returns an array keyed by cyber_doc_id.
     *
     * Shape per entry:
     * [
     *   'score'          => int,
     *   'label'          => string,
     *   'tier_index'     => int,
     *   'next_threshold' => int|null,
     *   'bar_pct'        => float,  // 0.0–1.0 for the current tier's progress bar
     * ]
     */
    public function getRepStateForPlayer(Player $player): array
    {
        $recs = PlayerReputation::where('player_id', $player->id)
            ->get()
            ->keyBy('cyber_doc_id');

        $docs  = CyberDoc::all();
        $state = [];

        foreach ($docs as $doc) {
            $score     = $recs[$doc->id]->score ?? 0;
            $tierIdx   = $this->getTierIndex($score);
            $currentMin = self::TIERS[$tierIdx]['min'];
            $nextMin    = self::TIERS[$tierIdx + 1]['min'] ?? null;

            $barPct = $nextMin
                ? min(1.0, ($score - $currentMin) / ($nextMin - $currentMin))
                : 1.0;

            $state[$doc->id] = [
                'score'          => $score,
                'label'          => self::TIERS[$tierIdx]['label'],
                'tier_index'     => $tierIdx,
                'next_threshold' => $nextMin,
                'bar_pct'        => round($barPct, 4),
            ];
        }

        return $state;
    }
}
