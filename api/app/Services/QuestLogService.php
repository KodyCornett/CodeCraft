<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerQuestLog;
use Illuminate\Support\Carbon;

/**
 * Writes chronological events to the player_quest_log table.
 * This is the feed the Archive Splice page renders.
 *
 * Event types:
 *   stage_complete  — payload: stage_id, stage_title, arc_title, doc_name
 *   branch_choice   — payload: stage_id, stage_title, arc_title, doc_name, chosen_doc_name
 *   watcher_signal  — payload: signal_text (already token-resolved)
 *   arc_unlocked    — payload: arc_id, arc_title, doc_name
 *   referral        — payload: referral_doc_name, referral_text
 */
class QuestLogService
{
    public function logStageComplete(
        Player $player,
        string $stageId,
        string $stageTitle,
        string $arcTitle,
        string $docName,
    ): void {
        $this->write($player, 'stage_complete', [
            'stage_id'    => $stageId,
            'stage_title' => $stageTitle,
            'arc_title'   => $arcTitle,
            'doc_name'    => $docName,
        ]);
    }

    public function logBranchChoice(
        Player $player,
        string $stageId,
        string $stageTitle,
        string $arcTitle,
        string $docName,
        string $chosenDocName,
    ): void {
        $this->write($player, 'branch_choice', [
            'stage_id'        => $stageId,
            'stage_title'     => $stageTitle,
            'arc_title'       => $arcTitle,
            'doc_name'        => $docName,
            'chosen_doc_name' => $chosenDocName,
        ]);
    }

    public function logWatcherSignal(Player $player, string $signalText): void
    {
        $this->write($player, 'watcher_signal', [
            'signal_text' => $signalText,
        ]);
    }

    public function logArcUnlocked(
        Player $player,
        string $arcId,
        string $arcTitle,
        string $docName,
    ): void {
        $this->write($player, 'arc_unlocked', [
            'arc_id'    => $arcId,
            'arc_title' => $arcTitle,
            'doc_name'  => $docName,
        ]);
    }

    public function logReferral(
        Player $player,
        string $referralDocName,
        string $referralText,
    ): void {
        $this->write($player, 'referral', [
            'referral_doc_name' => $referralDocName,
            'referral_text'     => $referralText,
        ]);
    }

    public function getForPlayer(Player $player): array
    {
        return PlayerQuestLog::where('player_id', $player->id)
            ->orderBy('occurred_at')
            ->get()
            ->map(fn ($e) => [
                'id'          => $e->id,
                'event_type'  => $e->event_type,
                'payload'     => $e->payload,
                'occurred_at' => $e->occurred_at,
            ])
            ->toArray();
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function write(Player $player, string $eventType, array $payload): void
    {
        PlayerQuestLog::create([
            'player_id'   => $player->id,
            'event_type'  => $eventType,
            'payload'     => $payload,
            'occurred_at' => Carbon::now(),
        ]);
    }
}
