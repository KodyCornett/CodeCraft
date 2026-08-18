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
        private readonly QuestLogService $questLogService,
        private readonly CodexService    $codexService,
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
     *       'cyber_doc_id' => string,
     *       'name'         => string,
     *       'district'     => string,
     *       'splice_url'   => string,
     *       'met'          => bool,
     *       'referral'     => string|null,
     *       'arcs'         => [...],
     *     ],
     *     ...
     *   ]
     * ]
     */
    public function getPlayerQuestState(Player $player): array
    {
        $arcProgressMap = PlayerArcProgress::where('player_id', $player->id)
            ->get()
            ->keyBy('quest_arc_id');

        $stageProgressMap = PlayerStageProgress::where('player_id', $player->id)
            ->get()
            ->keyBy('quest_stage_id');

        $docs = CyberDoc::with(['questArcs.stages'])->get();

        $pendingReferrals = $this->getPendingReferrals($player, $stageProgressMap);

        $result = [];
        foreach ($docs as $doc) {
            $docArcs = [];
            $met     = false;

            foreach ($doc->questArcs as $arc) {
                $arcProg = $arcProgressMap[$arc->id] ?? null;

                if ($arcProg) {
                    $met = true;
                }

                $arcStatus = $arcProg?->status ?? 'locked';
                $stages    = [];

                foreach ($arc->stages as $stage) {
                    $stageProg   = $stageProgressMap[$stage->id] ?? null;
                    $stageStatus = $stageProg?->status ?? 'locked';

                    $rawDialogue = $stage->dialogue;
                    $dialogue    = $stageStatus === 'active'
                        ? (is_string($rawDialogue) ? json_decode($rawDialogue, true) : $rawDialogue)
                        : null;

                    // field_comms — in-field voice-call lines, same active-only
                    // visibility rule as dialogue so nothing is spoiled early.
                    $rawFieldComms = $stage->field_comms;
                    $fieldComms    = $stageStatus === 'active'
                        ? (is_string($rawFieldComms) ? json_decode($rawFieldComms, true) : $rawFieldComms)
                        : null;

                    $stages[] = [
                        'id'                 => $stage->id,
                        'stage_number'       => $stage->stage_number,
                        'title'              => $stage->title,
                        'objective_text'     => $stageStatus !== 'locked' ? $stage->objective_text : null,
                        'dialogue'           => $dialogue,
                        'field_comms'        => $fieldComms,
                        'status'             => $stageStatus,
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
                    'id'                    => $arc->id,
                    'sequence_order'        => $arc->sequence_order,
                    'title'                 => $arc->title,
                    'status'                => $arcStatus,
                    'unlocked_at'           => $arcProg?->unlocked_at,
                    'completed_at'          => $arcProg?->completed_at,
                    'watcher_signal_sent'   => $arcProg?->watcher_signal_sent_at !== null,
                    'stages'                => $stages,
                ];
            }

            $result[] = [
                'cyber_doc_id' => $doc->id,
                'name'         => $doc->name,
                'district'     => $doc->district,
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
     * Called when a player visits a CyberDoc for the first time (or after a
     * referral has been issued). Initialises all arcs for this doc.
     *
     * - Entry arcs (is_entry_arc = true) unlock immediately on first visit.
     * - Non-entry arcs whose sequence_order follows a completed arc are unlocked.
     * - All others initialise as 'locked'.
     */
    public function initArcForDoc(Player $player, CyberDoc $doc): void
    {
        $arcs = QuestArc::where('cyber_doc_id', $doc->id)->orderBy('sequence_order')->get();

        foreach ($arcs as $arc) {
            $existing = PlayerArcProgress::where('player_id', $player->id)
                ->where('quest_arc_id', $arc->id)
                ->first();

            if ($existing) {
                continue;
            }

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
     * After completing:
     *   - Activates the next stage if one exists in the same arc.
     *   - If no next stage: marks the arc complete, then unlocks the next arc
     *     in sequence_order for the same doc (sequence-based, no rep gate).
     *   - Issues referral log entry if stage has a referral_doc_id.
     *   - Delivers a Watcher signal if one is attached.
     *   - Grants wallet_creds reward if the stage defines one.
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
            return ['stage_id' => $stage->id, 'already_complete' => true];
        }

        // Guard: previous stage in this arc must be complete before this one can be.
        // Prevents skipping to later stages to collect rewards out of sequence.
        if ($stage->stage_number > 1) {
            $prevStage = QuestStage::where('quest_arc_id', $arc->id)
                ->where('stage_number', $stage->stage_number - 1)
                ->first();

            if ($prevStage !== null) {
                $prevComplete = PlayerStageProgress::where('player_id', $player->id)
                    ->where('quest_stage_id', $prevStage->id)
                    ->where('status', 'complete')
                    ->exists();

                if (!$prevComplete) {
                    return ['stage_id' => $stage->id, 'error' => 'Previous stage not complete.'];
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

        // Grant wallet_creds reward if the stage defines one
        if (($stage->reward_creds ?? 0) > 0) {
            $player->increment('wallet_creds', $stage->reward_creds);
        }

        // Activate next stage in this arc, or complete the arc and unlock next
        $nextStage = QuestStage::where('quest_arc_id', $arc->id)
            ->where('stage_number', $stage->stage_number + 1)
            ->first();

        $arcsUnlocked = [];

        if ($nextStage) {
            PlayerStageProgress::updateOrCreate(
                ['player_id' => $player->id, 'quest_stage_id' => $nextStage->id],
                ['status' => 'active'],
            );
        } else {
            // Last stage of arc — mark arc complete
            PlayerArcProgress::where('player_id', $player->id)
                ->where('quest_arc_id', $arc->id)
                ->update(['status' => 'complete', 'completed_at' => Carbon::now()]);

            // Unlock the next arc in sequence_order for this doc
            $arcsUnlocked = $this->unlockNextArc($player, $doc, $arc->sequence_order);
        }

        // Write archive log entry
        if ($stage->is_branch && $turnedIntoDocId) {
            $chosenDoc = CyberDoc::find($turnedIntoDocId);
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
            );
        }

        // Log referral if issued
        if ($stage->referral_doc_id) {
            $referralDoc = CyberDoc::find($stage->referral_doc_id);
            $this->questLogService->logReferral(
                $player,
                $referralDoc?->name ?? 'Unknown',
                $stage->referral_text ?? '',
            );
        }

        // Activate a codex thread if this stage grants one — separate
        // optional side system, see CodexService.
        $codexActivation = $this->codexService->activateThreadForStage($player, $stage);

        return [
            'stage_id'           => $stage->id,
            'already_complete'   => false,
            'creds_granted'      => $stage->reward_creds ?? 0,
            'next_stage_id'      => $nextStage?->id,
            'arcs_unlocked'      => $arcsUnlocked,
            'referral_issued'    => $stage->referral_doc_id !== null,
            'referral_doc_id'    => $stage->referral_doc_id,
            'codex_thread_activated' => $codexActivation?->thread_key,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Arc unlock — sequence order
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Unlock the next arc in sequence_order for the given doc, after the
     * arc at $completedOrder was just finished.
     *
     * Returns array of newly unlocked arc IDs.
     */
    public function unlockNextArc(Player $player, CyberDoc $doc, int $completedOrder): array
    {
        $nextArc = QuestArc::where('cyber_doc_id', $doc->id)
            ->where('sequence_order', '>', $completedOrder)
            ->orderBy('sequence_order')
            ->first();

        if (! $nextArc) {
            return [];
        }

        $prog = PlayerArcProgress::where('player_id', $player->id)
            ->where('quest_arc_id', $nextArc->id)
            ->first();

        if ($prog) {
            if ($prog->status !== 'locked') {
                return []; // already active or complete
            }
            $prog->update(['status' => 'active', 'unlocked_at' => Carbon::now()]);
        } else {
            PlayerArcProgress::create([
                'player_id'    => $player->id,
                'quest_arc_id' => $nextArc->id,
                'status'       => 'active',
                'unlocked_at'  => Carbon::now(),
            ]);
        }

        $this->initStagesForArc($player, $nextArc);

        $this->questLogService->logArcUnlocked(
            $player,
            $nextArc->id,
            $nextArc->title,
            $doc->name,
        );

        return [$nextArc->id];
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Watcher interrupt delivery
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Record that the client has displayed the Watcher interrupt cinematic
     * tied to this arc's completion (see WATCHER_TRANSITIONS on the frontend).
     * Idempotent. Re-checked on every quest-log load so a reload between arc
     * completion and leaving the hub node can't drop the interrupt.
     */
    public function markWatcherSignalSent(Player $player, string $arcId): void
    {
        PlayerArcProgress::where('player_id', $player->id)
            ->where('quest_arc_id', $arcId)
            ->whereNull('watcher_signal_sent_at')
            ->update(['watcher_signal_sent_at' => Carbon::now()]);
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
                ['status' => $i === 0 ? 'active' : 'locked'],
            );
        }
    }

    /**
     * Find completed stages that issued a referral to a doc the player
     * hasn't initialised yet. Returns ['cyber_doc_id' => referral_text].
     */
    private function getPendingReferrals(Player $player, $stageProgressMap): array
    {
        $completedStageIds = $stageProgressMap
            ->where('status', 'complete')
            ->keys()
            ->toArray();

        if (empty($completedStageIds)) {
            return [];
        }

        $referralStages = QuestStage::whereIn('id', $completedStageIds)
            ->whereNotNull('referral_doc_id')
            ->get();

        $initialisedDocIds = PlayerArcProgress::where('player_id', $player->id)
            ->with('arc')
            ->get()
            ->pluck('arc.cyber_doc_id')
            ->unique()
            ->toArray();

        $pending = [];
        foreach ($referralStages as $stage) {
            if (! in_array($stage->referral_doc_id, $initialisedDocIds)) {
                $pending[$stage->referral_doc_id] = $stage->referral_text;
            }
        }

        return $pending;
    }
}
