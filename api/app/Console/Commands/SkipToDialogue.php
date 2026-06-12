<?php

namespace App\Console\Commands;

use App\Models\CyberDoc;
use App\Models\Node;
use App\Models\Player;
use App\Models\PlayerArcProgress;
use App\Models\PlayerStageProgress;
use App\Models\QuestArc;
use App\Models\QuestStage;
use App\Models\User;
use Illuminate\Console\Command;

/**
 * Fast-forwards a player's quest state so a specific stage is active.
 * Used for testing dialogue audio without replaying the full tutorial.
 *
 * Usage:
 *   php artisan player:skip-to knuckle          # activates Knuckle Stage 1
 *   php artisan player:skip-to knuckle 3        # activates Knuckle Stage 3
 *   php artisan player:skip-to veil             # activates Veil Stage 1
 *   php artisan player:skip-to --email=other@test.com knuckle
 *
 * Doc slugs → canvas_id:
 *   knuckle → BA-hub
 *   veil    → DT-hub
 *   float   → SV-hub
 *   axiom   → UD-hub
 *   patch   → NS-hub
 */
class SkipToDialogue extends Command
{
    protected $signature = 'player:skip-to
                            {doc : Doc slug (knuckle, veil, float, axiom, patch)}
                            {stage=1 : Stage number to activate (default: 1)}
                            {--email=test@example.com : Player email}';

    protected $description = 'Activate a specific quest stage for dialogue testing (dev only)';

    private const DOC_NODES = [
        'knuckle' => 'BA-hub',
        'veil'    => 'DT-hub',
        'float'   => 'SV-hub',
        'axiom'   => 'UD-hub',
        'patch'   => 'NS-hub',
    ];

    public function handle(): int
    {
        $docSlug     = strtolower($this->argument('doc'));
        $stageNumber = (int) $this->argument('stage');
        $email       = $this->option('email');

        // ── Resolve canvas_id ──────────────────────────────────────────────────
        $canvasId = self::DOC_NODES[$docSlug] ?? null;
        if (!$canvasId) {
            $this->error("Unknown doc slug '{$docSlug}'. Use: knuckle, veil, float, axiom, patch");
            return 1;
        }

        // ── Resolve player ─────────────────────────────────────────────────────
        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("No user found for email: {$email}");
            return 1;
        }

        $player = Player::where('user_id', $user->id)->first();
        if (!$player) {
            $this->error("No player found for user: {$email}");
            return 1;
        }

        // ── Resolve arc + stage ────────────────────────────────────────────────
        $node = Node::where('canvas_id', $canvasId)->first();
        if (!$node) {
            $this->error("Node '{$canvasId}' not found in database.");
            return 1;
        }

        $doc = CyberDoc::where('node_id', $node->id)->first();
        if (!$doc) {
            $this->error("No CyberDoc found at node '{$canvasId}'.");
            return 1;
        }

        $arc = QuestArc::where('cyber_doc_id', $doc->id)
            ->where('sequence_order', 1)
            ->first();
        if (!$arc) {
            $this->error("No arc found for {$docSlug}. Run QuestArcSeeder first.");
            return 1;
        }

        $stage = QuestStage::where('quest_arc_id', $arc->id)
            ->where('stage_number', $stageNumber)
            ->first();
        if (!$stage) {
            $this->error("Stage {$stageNumber} not found for {$docSlug}.");
            return 1;
        }

        // ── Ensure arc is active ───────────────────────────────────────────────
        PlayerArcProgress::updateOrCreate(
            ['player_id' => $player->id, 'quest_arc_id' => $arc->id],
            ['status' => 'active', 'unlocked_at' => now(), 'completed_at' => null],
        );

        // ── Mark all earlier stages in this arc as completed ───────────────────
        if ($stageNumber > 1) {
            $earlier = QuestStage::where('quest_arc_id', $arc->id)
                ->where('stage_number', '<', $stageNumber)
                ->get();

            foreach ($earlier as $prev) {
                PlayerStageProgress::updateOrCreate(
                    ['player_id' => $player->id, 'quest_stage_id' => $prev->id],
                    ['status' => 'completed', 'completed_at' => now()],
                );
            }
        }

        // ── Activate the target stage ──────────────────────────────────────────
        PlayerStageProgress::updateOrCreate(
            ['player_id' => $player->id, 'quest_stage_id' => $stage->id],
            ['status' => 'active', 'completed_at' => null],
        );

        // ── Mark later stages in this arc as locked ────────────────────────────
        $later = QuestStage::where('quest_arc_id', $arc->id)
            ->where('stage_number', '>', $stageNumber)
            ->get();

        foreach ($later as $next) {
            PlayerStageProgress::where('player_id', $player->id)
                ->where('quest_stage_id', $next->id)
                ->delete();
        }

        $this->info("✓ {$player->handle} → {$docSlug} Stage {$stageNumber} is now active.");
        $this->line("  Navigate to splice://dialogue/{$docSlug} to test.");

        return 0;
    }
}
