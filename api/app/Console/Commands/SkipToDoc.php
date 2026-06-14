<?php

namespace App\Console\Commands;

use App\Models\CyberDoc;
use App\Models\Node;
use App\Models\Player;
use App\Models\PlayerArcProgress;
use App\Models\PlayerStageProgress;
use App\Models\QuestArc;
use App\Models\User;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

/**
 * Skips the prologue chain to any doc for audio/dialogue testing.
 *
 * Resets the player, marks all prior docs' arcs complete, and drops
 * the player at the target doc's hub with stage 1 active and ready
 * to trigger dialogue.
 *
 * Usage:
 *   php artisan player:skip-to veil
 *   php artisan player:skip-to float --email=other@test.com
 *
 * Valid handles: knuckle | veil | float | axiom | patch
 */
class SkipToDoc extends Command
{
    protected $signature = 'player:skip-to
                            {doc : Doc handle to skip to (knuckle|veil|float|axiom|patch)}
                            {--stage=1 : Stage number to land on (default: 1)}
                            {--email=test@example.com : Player email}';

    protected $description = 'Skip prologue to a specific doc for dialogue/audio testing';

    // Ordered chain — each entry is completed before the target is unlocked
    private const CHAIN = [
        'knuckle' => ['canvas' => 'BA-hub', 'district' => 'BROWNE\'S ADDITION'],
        'veil'    => ['canvas' => 'DT-hub', 'district' => 'DOWNTOWN'],
        'float'   => ['canvas' => 'SV-hub', 'district' => 'SPOKANE VALLEY'],
        'axiom'   => ['canvas' => 'UD-hub', 'district' => 'UNIVERSITY DISTRICT'],
        'patch'   => ['canvas' => 'NS-hub', 'district' => 'NORTH SPOKANE'],
    ];

    public function handle(): int
    {
        $doc        = strtolower($this->argument('doc'));
        $email      = $this->option('email');
        $targetStage = (int) $this->option('stage');

        if (!array_key_exists($doc, self::CHAIN)) {
            $this->error("Unknown doc handle: '{$doc}'. Valid: " . implode(', ', array_keys(self::CHAIN)));
            return self::FAILURE;
        }

        $user = User::where('email', $email)->first();
        if (!$user) {
            $this->error("No user found for: {$email}");
            return self::FAILURE;
        }

        $player = Player::where('user_id', $user->id)->first();
        if (!$player) {
            $this->error("No player found for: {$email}");
            return self::FAILURE;
        }

        $this->info("Resetting {$player->handle} and skipping to: {$doc}");

        // ── Step 1: full reset ────────────────────────────────────────────────
        $this->call('player:reset', ['--email' => $email]);

        // ── Step 2: mark tutorial done + set persona so intros are skipped ───
        DB::table('players')->where('id', $player->id)->update([
            'persona'        => 'RUNNER',
            'persona_desc'   => 'Test skip — persona auto-set.',
            'tutorial_state' => json_encode([
                'tutorialSeen'    => true,
                'tutorialSkipped' => false,
                'stepsDone'       => [],
                'questsRewarded'  => [],
                'hasBadge'        => false,
            ]),
            'current_district' => self::CHAIN[$doc]['district'],
        ]);

        // ── Step 3: complete all prior arcs + unlock target ───────────────────
        $keys   = array_keys(self::CHAIN);
        $target = array_search($doc, $keys);

        DB::transaction(function () use ($player, $keys, $target) {
            foreach ($keys as $i => $handle) {
                $config   = self::CHAIN[$handle];
                $node     = Node::where('canvas_id', $config['canvas'])->first();
                $cyberDoc = $node ? CyberDoc::where('node_id', $node->id)->first() : null;

                if (!$cyberDoc) {
                    $this->warn("CyberDoc not found for {$handle} — skipped.");
                    continue;
                }

                $arc = QuestArc::where('cyber_doc_id', $cyberDoc->id)
                    ->where('sequence_order', 1)
                    ->first();

                if (!$arc) {
                    $this->warn("Arc not found for {$handle} — skipped.");
                    continue;
                }

                if ($i < $target) {
                    // Complete this arc and all its stages
                    PlayerArcProgress::updateOrCreate(
                        ['player_id' => $player->id, 'quest_arc_id' => $arc->id],
                        ['status' => 'completed', 'unlocked_at' => now(), 'completed_at' => now()]
                    );

                    foreach ($arc->stages as $stage) {
                        PlayerStageProgress::updateOrCreate(
                            ['player_id' => $player->id, 'quest_stage_id' => $stage->id],
                            ['status' => 'completed', 'completed_at' => now()]
                        );
                    }

                    $this->info("✓ {$handle} arc completed");

                } elseif ($i === $target) {
                    // Unlock this arc, complete prior stages, set target stage active
                    PlayerArcProgress::updateOrCreate(
                        ['player_id' => $player->id, 'quest_arc_id' => $arc->id],
                        ['status' => 'active', 'unlocked_at' => now(), 'completed_at' => null]
                    );

                    foreach ($arc->stages as $stage) {
                        if ($stage->stage_number < $targetStage) {
                            PlayerStageProgress::updateOrCreate(
                                ['player_id' => $player->id, 'quest_stage_id' => $stage->id],
                                ['status' => 'completed', 'completed_at' => now()]
                            );
                        } elseif ($stage->stage_number === $targetStage) {
                            PlayerStageProgress::updateOrCreate(
                                ['player_id' => $player->id, 'quest_stage_id' => $stage->id],
                                ['status' => 'active', 'completed_at' => null]
                            );
                        }
                    }

                    $this->info("✓ {$handle} arc unlocked — stage {$targetStage} active");
                }
                // Arcs after the target stay locked (reset already cleared them)
            }
        });

        $this->newLine();
        $this->info("Player '{$player->handle}' is ready. Log in and go to {$doc}'s hub to trigger dialogue.");

        return self::SUCCESS;
    }
}
