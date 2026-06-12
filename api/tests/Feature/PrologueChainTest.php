<?php

namespace Tests\Feature;

use App\Models\CyberDoc;
use App\Models\Node;
use App\Models\Player;
use App\Models\PlayerArcProgress;
use App\Models\PlayerRig;
use App\Models\PlayerStageProgress;
use App\Models\QuestArc;
use App\Models\QuestStage;
use App\Models\User;
use Database\Seeders\ChassisTemplateSeeder;
use Database\Seeders\CyberDocSeeder;
use Database\Seeders\NodeSeeder;
use Database\Seeders\QuestArcSeeder;
use Database\Seeders\QuestStageSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * PrologueChainTest
 *
 * Traces every call in the 5-doc prologue chain against real seeded data:
 *
 *   tutorial/complete  →  Knuckle arc live, dialogue on stage 1
 *   Knuckle s1 → s2 → s3  →  Veil referral issued
 *   visit DT-hub           →  Veil arc initialised, dialogue on stage 1
 *   Veil s1 → s2 → s3      →  Float referral issued
 *   visit SV-hub           →  Float arc initialised, dialogue on stage 1
 *   Float s1 → s2 → s3     →  Axiom referral issued
 *   visit UD-hub           →  Axiom arc initialised, dialogue on stage 1
 *   Axiom s1 → s2 → s3     →  Patch referral issued
 *   visit NS-hub           →  Patch arc initialised, dialogue on stage 1
 *   Patch s1 → s2 → s3     →  prologue_complete lore key on final stage
 *
 * Every assertion corresponds to a real server-side call that must fire for
 * the player to progress — if any step in the chain breaks, the test that
 * owns that step fails and tells you exactly where.
 */
class PrologueChainTest extends TestCase
{
    use RefreshDatabase;

    // ── Hub canvas IDs → doc order in the prologue ────────────────────────────
    private const CHAIN = [
        'BA-hub' => 'Knuckle',
        'DT-hub' => 'Veil',
        'SV-hub' => 'Float',
        'UD-hub' => 'Axiom',
        'NS-hub' => 'Patch',
    ];

    // ── Test player ───────────────────────────────────────────────────────────
    private User   $user;
    private Player $player;

    protected function setUp(): void
    {
        parent::setUp();

        // Seed the prologue world — real nodes, docs, arcs, stages
        $this->seed(ChassisTemplateSeeder::class);
        $this->seed(NodeSeeder::class);
        $this->seed(CyberDocSeeder::class);
        $this->seed(QuestArcSeeder::class);
        $this->seed(QuestStageSeeder::class);

        // Create a fresh test player attached to a seeded chassis
        $this->user   = User::factory()->create();
        $chassis      = \App\Models\ChassisTemplate::first();
        $this->player = Player::factory()->create([
            'user_id'       => $this->user->id,
            'wallet_creds'  => 0,
            'pocket_creds'  => 0,
        ]);
        PlayerRig::factory()->create([
            'player_id'           => $this->player->id,
            'chassis_template_id' => $chassis->id,
            'current_ss'          => 100,
        ]);
    }

    // =========================================================================
    // Helpers
    // =========================================================================

    /** Authenticated JSON request shorthand. */
    private function as(): \Illuminate\Testing\PendingCommand|\Illuminate\Foundation\Testing\TestCase
    {
        return $this->actingAs($this->user, 'sanctum');
    }

    /** Move the test player to a node by canvas_id. */
    private function moveTo(string $canvasId): Node
    {
        $node = Node::where('canvas_id', $canvasId)->firstOrFail();
        $this->player->update(['current_node_id' => $node->id]);
        return $node;
    }

    /** Return the CyberDoc for a hub canvas_id. */
    private function doc(string $canvasId): CyberDoc
    {
        $node = Node::where('canvas_id', $canvasId)->firstOrFail();
        return CyberDoc::where('node_id', $node->id)->firstOrFail();
    }

    /** Return the first (and only) arc for a hub canvas_id. */
    private function arc(string $canvasId): QuestArc
    {
        return $this->doc($canvasId)->questArcs()->orderBy('sequence_order')->first();
    }

    /** Return all stages for a hub, in stage_number order. */
    private function stages(string $canvasId): \Illuminate\Database\Eloquent\Collection
    {
        return QuestStage::where('quest_arc_id', $this->arc($canvasId)->id)
            ->orderBy('stage_number')
            ->get();
    }

    /** Complete a stage and assert the call returns 200. */
    private function completeStage(string $stageId): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($this->user, 'sanctum')
                    ->postJson("/api/quests/stage/{$stageId}/complete")
                    ->assertOk();
    }

    /** Visit the cyberdoc at $canvasId (moves player there first). */
    private function visitDoc(string $canvasId): \Illuminate\Testing\TestResponse
    {
        $this->moveTo($canvasId);
        return $this->actingAs($this->user, 'sanctum')
                    ->postJson('/api/cyberdoc/visit')
                    ->assertOk();
    }

    /** Fetch GET /api/quests and return the doc entry for $canvasId. */
    private function questDoc(string $canvasId): array
    {
        $docId = $this->doc($canvasId)->id;
        $docs  = $this->actingAs($this->user, 'sanctum')
                      ->getJson('/api/quests')
                      ->assertOk()
                      ->json('docs');
        return collect($docs)->firstWhere('cyber_doc_id', $docId);
    }

    // =========================================================================
    // Step 0 — Seeder sanity: all 5 docs and arcs are in the DB
    // =========================================================================

    public function test_all_five_prologue_docs_are_seeded(): void
    {
        foreach (self::CHAIN as $canvasId => $handle) {
            $this->assertNotNull(
                $this->doc($canvasId),
                "CyberDoc for {$handle} ({$canvasId}) missing from seed"
            );
            $this->assertNotNull(
                $this->arc($canvasId),
                "QuestArc for {$handle} ({$canvasId}) missing from seed"
            );
            $this->assertCount(
                3,
                $this->stages($canvasId),
                "{$handle} must have exactly 3 stages"
            );
        }
    }

    public function test_only_knuckle_arc_is_entry_arc(): void
    {
        foreach (self::CHAIN as $canvasId => $handle) {
            $arc = $this->arc($canvasId);
            $expected = ($canvasId === 'BA-hub');
            $this->assertEquals(
                $expected,
                (bool) $arc->is_entry_arc,
                $expected
                    ? "Knuckle arc must be is_entry_arc=true"
                    : "{$handle} arc must NOT be is_entry_arc"
            );
        }
    }

    public function test_stages_1_and_3_have_dialogue_stage_2_does_not(): void
    {
        // The seeder puts dialogue on stages 1 and 3 of every doc; stage 2
        // is the field-work step and has no dialogue.
        foreach (self::CHAIN as $canvasId => $handle) {
            $stages = $this->stages($canvasId);
            $this->assertNotNull($stages[0]->dialogue, "{$handle} stage 1 must have dialogue in seeder");
            $this->assertNull($stages[1]->dialogue,    "{$handle} stage 2 must have null dialogue");
            $this->assertNotNull($stages[2]->dialogue, "{$handle} stage 3 must have dialogue in seeder");
        }
    }

    // =========================================================================
    // Step 1 — tutorial/complete fires → only Knuckle's arc initialised
    // =========================================================================

    public function test_tutorial_complete_initialises_knuckle_only(): void
    {
        $this->actingAs($this->user, 'sanctum')
             ->postJson('/api/tutorial/complete')
             ->assertOk()
             ->assertJson(['ok' => true]);

        // Knuckle's arc must be active
        $this->assertDatabaseHas('player_arc_progress', [
            'player_id'    => $this->player->id,
            'quest_arc_id' => $this->arc('BA-hub')->id,
            'status'       => 'active',
        ]);

        // No other doc's arc should be initialised yet
        foreach (['DT-hub', 'SV-hub', 'UD-hub', 'NS-hub'] as $canvasId) {
            $this->assertDatabaseMissing('player_arc_progress', [
                'player_id'    => $this->player->id,
                'quest_arc_id' => $this->arc($canvasId)->id,
            ]);
        }
    }

    public function test_knuckle_stage_1_is_active_after_tutorial_complete(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/tutorial/complete');

        $stages = $this->stages('BA-hub');
        $this->assertDatabaseHas('player_stage_progress', [
            'player_id'      => $this->player->id,
            'quest_stage_id' => $stages[0]->id,
            'status'         => 'active',
        ]);
        $this->assertDatabaseHas('player_stage_progress', [
            'player_id'      => $this->player->id,
            'quest_stage_id' => $stages[1]->id,
            'status'         => 'locked',
        ]);
        $this->assertDatabaseHas('player_stage_progress', [
            'player_id'      => $this->player->id,
            'quest_stage_id' => $stages[2]->id,
            'status'         => 'locked',
        ]);
    }

    public function test_quest_log_shows_knuckle_dialogue_after_tutorial_complete(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/tutorial/complete');

        $entry = $this->questDoc('BA-hub');
        $stage1 = $entry['arcs'][0]['stages'][0];

        $this->assertEquals('active', $stage1['status']);
        $this->assertNotNull($stage1['dialogue'], 'Knuckle stage 1 must expose dialogue for the player');
        // Dialogue is always returned as a decoded array from the service.
        $this->assertIsArray($stage1['dialogue'], 'Dialogue must be a decoded array, not a raw JSON string');
        $this->assertNotEmpty($stage1['dialogue']);

        // Stage 2 must still be locked and leak nothing
        $stage2 = $entry['arcs'][0]['stages'][1];
        $this->assertEquals('locked', $stage2['status']);
        $this->assertNull($stage2['dialogue']);
        $this->assertNull($stage2['objective_text']);
    }

    // =========================================================================
    // Step 2 — Knuckle arc: complete all 3 stages, Veil referral fires
    // =========================================================================

    public function test_knuckle_stage_1_completion_advances_to_stage_2(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/tutorial/complete');
        $stages = $this->stages('BA-hub');

        $this->completeStage($stages[0]->id);

        $this->assertDatabaseHas('player_stage_progress', [
            'player_id'      => $this->player->id,
            'quest_stage_id' => $stages[0]->id,
            'status'         => 'complete',
        ]);
        $this->assertDatabaseHas('player_stage_progress', [
            'player_id'      => $this->player->id,
            'quest_stage_id' => $stages[1]->id,
            'status'         => 'active',
        ]);
    }

    public function test_knuckle_stage_3_completion_issues_veil_referral(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/tutorial/complete');
        $stages = $this->stages('BA-hub');

        $this->completeStage($stages[0]->id);
        $this->completeStage($stages[1]->id);

        $res = $this->completeStage($stages[2]->id);

        // API must report a referral was issued
        $res->assertJsonPath('referral_issued', true);
        $this->assertNotNull($res->json('referral_doc_id'));

        // Veil's arc must NOT be initialised yet — player hasn't visited
        $this->assertDatabaseMissing('player_arc_progress', [
            'player_id'    => $this->player->id,
            'quest_arc_id' => $this->arc('DT-hub')->id,
        ]);

        // But Veil must appear in the quest log with a referral blurb
        $veilEntry = $this->questDoc('DT-hub');
        $this->assertNotNull($veilEntry['referral'], 'Veil referral text must appear after Knuckle arc complete');
    }

    public function test_knuckle_arc_marked_complete_after_stage_3(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/tutorial/complete');
        $stages = $this->stages('BA-hub');

        foreach ($stages as $stage) {
            $this->completeStage($stage->id);
        }

        $this->assertDatabaseHas('player_arc_progress', [
            'player_id'    => $this->player->id,
            'quest_arc_id' => $this->arc('BA-hub')->id,
            'status'       => 'complete',
        ]);
    }

    public function test_knuckle_stage_3_grants_wallet_creds(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/tutorial/complete');
        $stages = $this->stages('BA-hub');

        $this->completeStage($stages[0]->id);
        $this->completeStage($stages[1]->id);
        $res = $this->completeStage($stages[2]->id);

        $res->assertJsonPath('creds_granted', 100);
        $this->assertEquals(100, $this->player->fresh()->wallet_creds);
    }

    // =========================================================================
    // Step 3 — Visit Veil → arc initialised → dialogue on stage 1
    // =========================================================================

    public function test_visiting_veil_initialises_arc_after_referral(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/tutorial/complete');
        foreach ($this->stages('BA-hub') as $stage) {
            $this->completeStage($stage->id);
        }

        $this->visitDoc('DT-hub');

        $this->assertDatabaseHas('player_arc_progress', [
            'player_id'    => $this->player->id,
            'quest_arc_id' => $this->arc('DT-hub')->id,
            'status'       => 'active',
        ]);
    }

    public function test_veil_stage_1_has_dialogue_after_visit(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/tutorial/complete');
        foreach ($this->stages('BA-hub') as $stage) {
            $this->completeStage($stage->id);
        }
        $this->visitDoc('DT-hub');

        $entry  = $this->questDoc('DT-hub');
        $stage1 = $entry['arcs'][0]['stages'][0];

        $this->assertEquals('active', $stage1['status']);
        $this->assertNotNull($stage1['dialogue'], 'Veil stage 1 must expose dialogue after arc initialised');
    }

    // =========================================================================
    // Step 4–7 — Float, Axiom, Patch each follow the same pattern
    // =========================================================================

    /**
     * Helper: complete all 3 stages for a doc (assumes arc is already active).
     */
    private function completeArc(string $canvasId): void
    {
        foreach ($this->stages($canvasId) as $stage) {
            $this->completeStage($stage->id);
        }
    }

    public function test_full_prologue_chain_each_referral_fires_in_order(): void
    {
        // tutorial complete → Knuckle
        $this->actingAs($this->user, 'sanctum')->postJson('/api/tutorial/complete');

        // Walk the chain: complete doc → visit next → complete → ...
        $chain = [
            ['complete' => 'BA-hub', 'next' => 'DT-hub'],  // Knuckle → Veil
            ['complete' => 'DT-hub', 'next' => 'SV-hub'],  // Veil    → Float
            ['complete' => 'SV-hub', 'next' => 'UD-hub'],  // Float   → Axiom
            ['complete' => 'UD-hub', 'next' => 'NS-hub'],  // Axiom   → Patch
        ];

        foreach ($chain as ['complete' => $current, 'next' => $next]) {
            // Complete all 3 stages for the current doc
            $this->completeArc($current);

            // The referral should appear in the quest log for the next doc
            $nextEntry = $this->questDoc($next);
            $this->assertNotNull(
                $nextEntry['referral'],
                "Referral to {$next} must appear in quest log after completing {$current}"
            );

            // Next doc's arc must NOT be initialised until the player visits
            $this->assertDatabaseMissing('player_arc_progress', [
                'player_id'    => $this->player->id,
                'quest_arc_id' => $this->arc($next)->id,
            ]);

            // Visit the next doc — this fires initArcForDoc
            $this->visitDoc($next);

            // Arc must now be active and stage 1 must have dialogue
            $this->assertDatabaseHas('player_arc_progress', [
                'player_id'    => $this->player->id,
                'quest_arc_id' => $this->arc($next)->id,
                'status'       => 'active',
            ]);

            $entry  = $this->questDoc($next);
            $stage1 = $entry['arcs'][0]['stages'][0];
            $this->assertEquals('active', $stage1['status']);
            $this->assertNotNull(
                $stage1['dialogue'],
                "Stage 1 of {$next} must expose dialogue after arc initialised"
            );
        }
    }

    public function test_completing_patch_arc_ends_prologue(): void
    {
        // Set up full chain through Axiom
        $this->actingAs($this->user, 'sanctum')->postJson('/api/tutorial/complete');
        $this->completeArc('BA-hub');
        $this->visitDoc('DT-hub');
        $this->completeArc('DT-hub');
        $this->visitDoc('SV-hub');
        $this->completeArc('SV-hub');
        $this->visitDoc('UD-hub');
        $this->completeArc('UD-hub');
        $this->visitDoc('NS-hub');

        // Complete Patch stages 1 and 2
        $stages = $this->stages('NS-hub');
        $this->completeStage($stages[0]->id);
        $this->completeStage($stages[1]->id);

        // Complete Patch stage 3 — the final stage of the prologue
        $res = $this->completeStage($stages[2]->id);

        // Patch's arc must be complete
        $this->assertDatabaseHas('player_arc_progress', [
            'player_id'    => $this->player->id,
            'quest_arc_id' => $this->arc('NS-hub')->id,
            'status'       => 'complete',
        ]);

        // Stage 3 carries the prologue_complete lore key
        $this->assertEquals(
            'prologue_complete',
            $stages[2]->reward_lore_key,
            'Patch stage 3 must carry the prologue_complete lore key'
        );

        // 100 creds paid out for the final stage
        $res->assertJsonPath('creds_granted', 100);

        // All 5 arcs are complete
        foreach (self::CHAIN as $canvasId => $handle) {
            $this->assertDatabaseHas('player_arc_progress', [
                'player_id'    => $this->player->id,
                'quest_arc_id' => $this->arc($canvasId)->id,
                'status'       => 'complete',
            ]);
        }
    }

    // =========================================================================
    // Guard: completed stages cannot be re-completed
    // =========================================================================

    public function test_re_completing_a_stage_is_a_no_op(): void
    {
        $this->actingAs($this->user, 'sanctum')->postJson('/api/tutorial/complete');
        $stages = $this->stages('BA-hub');

        $this->completeStage($stages[0]->id);
        $this->completeStage($stages[0]->id); // second call

        // Still only one completion record
        $this->assertEquals(
            1,
            PlayerStageProgress::where('player_