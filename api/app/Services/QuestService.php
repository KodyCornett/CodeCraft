<?php

namespace App\Services;

use App\Models\CyberDoc;
use App\Models\Player;
use App\Models\PlayerArcProgress;
use App\Models\PlayerStageProgress;
use App\Models\QuestArc;
use App\Models\QuestStage;
use Illuminate\Support\Carbon;

class QuestService
{
    public function __construct(
        private readonly ReputationService $reputationService,
        private readonly WatcherService    $watcherService,
        private readonly QuestLogService   $questLogService,
    ) {}

    // ─────────────────────────────────────────────────────────────────────────
    // State read
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Build the full quest state for a player.
     * This is the payload the Splice quest log terminal renders.
     *
     * Shape:
     * [
     *   'docs' => [
     *     [
     *       'cyber_doc_id'   => string,
     *       'name'           => string,
     *       'district'       => string,
     *       'splice_url'     => string,
     *       'rep'            => [...],  // from ReputationService::getRepStateForPlayer
     *       'met'            => bool,   // has the player initialised this doc's arc?
     *       'referral'       => string|null,  // pending referral text if not yet met
     *       'arcs'           => [...],
     *     ],
     *     ...
     *   ]
     * ]
     */
    public function getPlayerQuestState(Player $player): array
    {
        $repState = $this->reputationService->getRepStateForPlayer($player);

        $arcProgressMap = PlayerArcProgress::where('player_id', $player->id)
            ->get()
            ->keyBy('quest_arc_id');

        $stageProgressMap = PlayerStageProgress::where('player_id', $player->id)
            ->get()
            ->keyBy('quest_stage_id');

        $docs = CyberDoc::with(['questArcs.stages'])->get();

        // Collect any pending referrals — stages the player completed that
        // introduced a doc the player hasn't initialised yet
        $pendingReferrals = $this->getPendingReferrals($player, $stageProgressMap);

        $result = [];
        foreach ($docs as $doc) {
            $docArcs = [];
            $met     = false;

            foreach ($doc->questArcs as $arc) {
                $arcProg = $arcProgressMap[$arc->id] ?? null;

                // If the player has any progress record for this arc, they've met this doc
                if ($arcProg) {
                    $met = true;
                }

                $arcStatus = $arcProg?->status ?? 'locked';
                $stages    = [];

                foreach ($arc->stages as $stage) {
                    $stageProg   = $stageProgressMap[$stage->id] ?? null;
                    $stageStatus = $stageProg?->status ?? 'locked';

                    // Ensure dialogue is always returned as a decoded array, not a JSON string.
                    // The array cast on QuestStage should handle this, but defensively
                    // decode here in case the cast returns the raw DB string.
                    $rawDialogue = $stage->dialogue;
                    $dialogue    = $stageStatus === 'active'
                        ? (is_string($rawDialogue) ? json_decode($rawDialogue, true) : $rawDialogue)
                        : null;

                    $stages[] = [
                        'id'                 => $stage->id,
                        'stage_number'       => $stage->stage_number,
                        'title'              => $stage->title,
                        'objective_text'     => $stageStatus !== 'locked' ? $stage->objective_text : null,
                        'dialogue'           => $dialogue,
                        'status'             => $stageStatus,
                        'rep_reward'         => $stage->rep_reward,
                        'is_branch'          => $stage->is_branch,
                        'branch_options'     => $stage->branch_options,
                        'turned_into_doc_id' => $stageProg?->turned_into_doc_id,
                        'completed_at'       => $stageProg?->completed_at,
                        'referral_doc_id'    => $stage->referral_doc_id,
                        'referral_text'      => $stage->referral_text,
                        'node_canvas_id'     => $stage->node_canvas_id,
                        'minigame_type'      => $stage->minigame_type,
                    ];
                }

                $docArcs[] = [
                    'id'             => $arc->id,
                    'sequence_order' => $arc->sequence_order,
                    'title'          => $arc->title,
                    'rep_required'   => $arc->rep_required,
                    'status'         => $arcStatus,
                    'unlocked_at'    => $arcProg?->unlocked_at,
                    'completed_at'   => $arcProg?->completed_at,
                    'stages'         => $stages,
                ];
            }

            $result[] = [
                'cyber_doc_id' => $doc->id,
                'name'         => $doc->name,
                'district'     => $doc->district,
                'rep'          => $repState[$doc->id] ?? null,
                'met'          => $met,
                'referral'     => $pendingReferrals[$doc->id] ?? null,
                'arcs'         => $docArcs,
            ];
        }

        return ['docs' => $result];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Arc initialisation
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Called when a player visits a CyberDoc for the first time (or if a referral
     * has been issued). Initialises all arcs that should be active for this doc.
     *
     * - Entry arcs (is_entry_arc = true) unlock immediately on first visit.
     * - Non-entry arcs initialise as 'locked' so they appear in the log grayed out.
     * - Sets the first stage of unlocked arcs to 'active'.
     */
    public function initArcForDoc(Player $player, CyberDoc $doc): void
    {
        $arcs = QuestArc::where('cyber_doc_id', $doc->id)->orderBy('sequence_order')->get();

        foreach ($arcs as $arc) {
            $existing = PlayerArcProgress::where('player_id', $player->id)
                ->where('quest_arc_id', $arc->id)
                ->first();

            if ($existing) {
                continue; // already initialised
            }

            // Entry arcs unlock immediately on first visit.
            // Non-entry arcs unlock if the player holds a referral to this doc
            // (i.e. they completed a stage elsewhere whose referral_doc_id = $doc->id).
            // Without a referral they initialise as 'locked' and wait for a rep threshold
            // or another explicit trigger via checkAndUnlockArcs.
            $hasReferral = PlayerStageProgress::where('player_id', $player->id)
                ->where('status', 'complete')
                ->whereHas('stage', fn ($q) => $q->where('referral_doc_id', $doc->id))
                ->exists();

            $shouldUnlock = $arc->is_entry_arc || $hasReferral;
            $status       = $shouldUnlock ? 'active' : 'locked';

            PlayerArcProgress::create([
                'player_id'    => $player->id,
                'quest_arc_id' => $arc->id,
                'status'       => $status,
                'unlocked_at'  => $shouldUnlock ? Carbon::now() : null,
            ]);

            if ($shouldUnlock) {
                $this->initStagesForArc($player, $arc);
            }
        }
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Stage advancement
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Mark a stage as complete for a player.
     *
     * For branch stages, $turnedIntoDocId indicates which doc the job was turned into.
     * Rep is granted to that doc (or the owning doc if no branch choice was made).
     *
     * After completing:
     *   - Grants rep
     *   - Activates the next stage if one exists
     *   - Marks the arc complete if all stages are done
     *   - Issues referral log entry if stage has a referral_doc_id
     *   - Checks if any locked arcs are now unlockable (rep threshold crossed)
     *
     * Returns an array describing what changed (for the API response).
     */
    public function completeStage(Player $player, string $stageId, ?string $turnedIntoDocId = null): array
    {
        $stage = QuestStage::with('arc.cyberDoc')->findOrFail($stageId);
        $arc   = $stage->arc;
        $doc   = $arc->cyberDoc;

        // Guard: don't re-complete an already-complete stage
        $existing = PlayerStageProgress::where('player_id', $player->id)
            ->where('quest_stage_id', $stage->id)
            ->where('status', 'complete')
            ->first();
        if ($existing) {
            return [
                'stage_id'       => $stage->id,
                'already_complete' => true,
            ];
        }

        // Resolve which doc gets the rep and how much
        $repDocId  = $turnedIntoDocId ?? $doc->id;
        $repAmount = $stage->rep_reward;

        // For branch stages, rep amount comes from the chosen branch option
        if ($stage->is_branch && $turnedIntoDocId && $stage->branch_options) {
            foreach ($stage->branch_options as $opt) {
                if (($opt['cyber_doc_id'] ?? null) === $turnedIntoDocId) {
                    $repAmount = (int) ($opt['rep_reward'] ?? 0);
                    break;
                }
            }
        }

        // Upsert stage progress
        PlayerStageProgress::updateOrCreate(
            ['player_id' => $player->id, 'quest_stage_id' => $stage->id],
            [
                'status'             => 'complete',
                'turned_into_doc_id' => $turnedIntoDocId,
                'completed_at'       => Carbon::now(),
            ],
        );

        // Grant rep
        $rep = $this->reputationService->grantRep($player, $repDocId, $repAmount);

        // Grant wallet_creds reward if the stage defines one.
        // Goes directly to wallet (safe — not stealable in PvP).
        if (($stage->reward_creds ?? 0) > 0) {
            $player->increment('wallet_creds', $stage->reward_creds);
        }

        // Activate next stage
        $nextStage = QuestStage::where('quest_arc_id', $arc->id)
            ->where('stage_number', $stage->stage_number + 1)
            ->first();

        if ($nextStage) {
            PlayerStageProgress::updateOrCreate(
                ['player_id' => $player->id, 'quest_stage_id' => $nextStage->id],
                ['status' => 'active'],
            );
        } else {
            // No more stages — mark arc complete
            PlayerArcProgress::where('player_id', $player->id)
                ->where('quest_arc_id', $arc->id)
                ->update(['status' => 'complete', 'completed_at' => Carbon::now()]);
        }

        // Check if rep unlocks any other locked arcs for this player
        $unlocked = $this->checkAndUnlockArcs($player);

        // Write archive log entry
        if ($stage->is_branch && $turnedIntoDocId) {
            $chosenDoc = \App\Models\CyberDoc::find($turnedIntoDocId);
            $this->questLogService->logBranchChoice(
                $player,
                $stage->id,
                $stage->title,
                $arc->title,
                $doc->name,
                $chosenDoc?->name ?? 'Unknown',
            );
        } else {
            $this->questLogService->logStageComplete(
                $player,
                $stage->id,
                $stage->title,
                $arc->title,
                $doc->name,
                $repAmount,
            );
        }

        // Log referral if issued
        if ($stage->referral_doc_id) {
            $referralDoc = \App\Models\CyberDoc::find($stage->referral_doc_id);
            $this->questLogService->logReferral(
                $player,
                $referralDoc?->name ?? 'Unknown',
                $stage->referral_text ?? '',
            );
        }

        // Deliver Watcher signal if one is attached to this stage
        $watcherDelivery = $this->watcherService->deliverForStage($player, $stage->id);

        return [
            'stage_id'              => $stage->id,
            'already_complete'      => false,
            'rep_granted'           => $repAmount,
            'rep_doc_id'            => $repDocId,
            'rep_score'             => $rep->score,
            'rep_label'             => $this->reputationService->getRepLabel($rep->score),
            'creds_granted'         => $stage->reward_creds ?? 0,
            'next_stage_id'         => $nextStage?->id,
            'arcs_unlocked'         => $unlocked,
            'referral_issued'       => $stage->referral_doc_id !== null,
            'referral_doc_id'       => $stage->referral_doc_id,
            'watcher_signal'        => $watcherDelivery !== null,
            'watcher_message_id'    => $watcherDelivery?->watcher_message_id,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Arc unlock sweep
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Scan all locked arcs for this player and unlock any whose rep_required
     * threshold has been crossed. Initialises stages for newly unlocked arcs.
     *
     * Returns array of newly unlocked arc IDs.
     */
    public function checkAndUnlockArcs(Player $player): array
    {
        $lockedProgress = PlayerArcProgress::where('player_id', $player->id)
            ->where('status', 'locked')
            ->with('arc.cyberDoc')
            ->get();

        $unlocked = [];

        foreach ($lockedProgress as $prog) {
            $arc      = $prog->arc;
            $repScore = $this->reputationService->getScore($player, $arc->cyber_doc_id);

            // rep_required = 0 arcs are NOT auto-unlocked via rep sweep —
            // they need an explicit story trigger (referral, tutorial complete, etc).
            // Only arcs with a genuine positive threshold unlock here.
            if ($arc->rep_required > 0 && $repScore >= $arc->rep_required) {
                $prog->update([
                    'status'      => 'active',
                    'unlocked_at' => Carbon::now(),
                ]);
                $this->initStagesForArc($player, $arc);
                $unlocked[] = $arc->id;

                // Log the unlock so the archive and doc notification system pick it up
                $this->questLogService->logArcUnlocked(
                    $player,
                    $arc->id,
                    $arc->title,
                    $arc->cyberDoc->name,
                );
            }
        }

        return $unlocked;
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Create stage progress rows for all stages in an arc.
     * Stage 1 is set to 'active'; remaining stages are 'locked'.
     */
    private function initStagesForArc(Player $player, QuestArc $arc): void
    {
        $stages = QuestStage::where('quest_arc_id', $arc->id)
            ->orderBy('stage_number')
            ->get();

        foreach ($stages as $i => $stage) {
            PlayerStageProgress::firstOrCreate(
                ['player_id' => $player->id, 'quest_stage_id' => $stage->id],
                ['status' => $i === 0 ? 'activ