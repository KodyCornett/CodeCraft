<?php

namespace Tests\Feature;

use App\Models\ChassisTemplate;
use App\Models\CyberDoc;
use App\Models\Node;
use App\Models\Player;
use App\Models\PlayerArcProgress;
use App\Models\PlayerRig;
use App\Models\PlayerStageProgress;
use App\Models\QuestArc;
use App\Models\QuestStage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * TutorialQuestFlowTest
 *
 * Covers the server-side half of the tutorial → Knuckle → dialogue flow:
 *
 *   1. POST /api/tutorial/complete
 *      - Initialises the entry arc so Knuckle appears in the quest log
 *      - Is idempotent (safe to call again on reload)
 *
 *   2. GET /api/quests
 *      - Dialogue is exposed only for active stages (not locked/complete)
 *      - met flag reflects whether the player has been to a doc
 *
 *   3. POST /api/quests/stage/{id}/complete
 *      - Advances to the next stage
 *      - Marks the arc complete when the final stage is done
 *      - Grants rep and wallet creds
 *      - Initialises a referred doc's arc on stage completion
 *
 *   4. Tutorial state persistence (GET / PATCH /api/tutorial/state)
 *      - State round-trips cleanly; hydration detects completed tutorial
 */
class TutorialQuestFlowTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Scaffold helpers
    // =========================================================================

    /**
     * Create a fully wired player ready to make authenticated API requests.
     *
     * Returns [$user, $player].
     */
    private function scaffoldPlayer(array $playerOverrides = []): array
    {
        $user    = User::factory()->create();
        $chassis = ChassisTemplate::factory()->create();
        $player  = Player::factory()->create(array_merge(
            ['user_id' => $user->id, 'wallet_creds' => 0, 'pocket_creds' => 0],
            $playerOverrides,
        ));
        PlayerRig::factory()->create([
            'player_id'           => $player->id,
            'chassis_template_id' => $chassis->id,
            'current_ss'          => 100,
        ]);
        return [$user, $player];
    }

    /**
     * Build a CyberDoc with one QuestArc (entry arc by default) and the given
     * array of stage definitions.
     *
     * Each stage definition is an array of QuestStage field overrides; sensible
     * defaults are supplied for every required field.
     *
     * Returns [$doc, $arc, $stages[]] where stages are in stage_number order.
     */
    private function scaffoldDoc(
        string $canvasId    = 'BA-hub',
        string $district    = "Browne's Addition",
        bool   $isEntryArc  = true,
        array  $stageDefs   = [],
    ): array {
        $node = Node::create([
            'canvas_id' => $canvasId,
            'x'         => 0,
            'y'         => 0,
            'type'      => 'cyberdoc',
            'ice'       => 0,
        ]);

        $doc = CyberDoc::create([
            'node_id'  => $node->id,
            'district' => $district,
            'name'     => "Knuckle's Med-Wagon",
        ]);

        $arc = QuestArc::create([
            'cyber_doc_id'   => $doc->id,
            'sequence_order' => 1,
            'title'          => 'The Climate Override',
            'rep_required'   => 0,
            'is_entry_arc'   => $isEntryArc,
        ]);

        // Default to two stages if none provided
        if (empty($stageDefs)) {
            $stageDefs = [
                [
                    'stage_number'   => 1,
                    'title'          => 'Find Knuckle',
                    'objective_text' => 'Get to the BA-Hub.',
                    'dialogue'       => [
                        ['speaker' => 'KNUCKLE', 'text' => 'Close the door.'],
                        ['speaker' => 'PLAYER_CHOICE', 'options' => [
                            ['tone' => 'COLD', 'text' => 'Is it going to kill me?'],
                        ]],
                    ],
                    'rep_reward'     => 0,
                    'reward_creds'   => 0,
                    'node_canvas_id' => $canvasId,
                ],
                [
                    'stage_number'   => 2,
                    'title'          => 'Deploy DISCONNECT_LAYER',
                    'objective_text' => 'Get to BA-v14.',
                    'dialogue'       => null,
                    'rep_reward'     => 0,
                    'reward_creds'   => 0,
                    'node_canvas_id' => 'BA-v14',
                ],
            ];
        }

        $stages = [];
        foreach ($stageDefs as $def) {
            $stages[] = QuestStage::create(array_merge(
                [
                    'quest_arc_id'   => $arc->id,
                    'stage_number'   => 1,
                    'title'          => 'Stage',
                    'objective_text' => 'Objective.',
                    'dialogue'       => null,
                    'rep_reward'     => 0,
                    'is_branch'      => false,
                    'reward_creds'   => 0,
                    'reward_tech_points' => 0,
                ],
                $def,
                ['quest_arc_id' => $arc->id],
            ));
        }

        return [$doc, $arc, $stages];
    }

    // =========================================================================
    // 1 — POST /api/tutorial/complete
    // =========================================================================

    public function test_complete_requires_auth(): void
    {
        $this->postJson('/api/tutorial/complete')->assertUnauthorized();
    }

    public function test_complete_initialises_entry_arc_and_stage_1(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        [$doc, $arc, $stages] = $this->scaffoldDoc(isEntryArc: true);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/tutorial/complete')
             ->assertOk()
             ->assertJson(['ok' => true]);

        // Entry arc must now be active
        $this->assertDatabaseHas('player_arc_progress', [
            'player_id'    => $player->id,
            'quest_arc_id' => $arc->id,
            'status'       => 'active',
        ]);

        // Stage 1 must be active
        $this->assertDatabaseHas('player_stage_progress', [
            'player_id'      => $player->id,
            'quest_stage_id' => $stages[0]->id,
            'status'         => 'active',
        ]);

        // Stage 2 must be locked
        $this->assertDatabaseHas('player_stage_progress', [
            'player_id'      => $player->id,
            'quest_stage_id' => $stages[1]->id,
            'status'         => 'locked',
        ]);
    }

    public function test_complete_non_entry_arc_stays_locked(): void
    {
        [$user, $player] = $this->scaffoldPlayer();

        // Entry arc (BA-hub)
        [$entryDoc, $entryArc] = $this->scaffoldDoc(canvasId: 'BA-hub', isEntryArc: true);

        // A second arc on the same doc that is NOT the entry arc
        $lockedArc = QuestArc::create([
            'cyber_doc_id'   => $entryDoc->id,
            'sequence_order' => 2,
            'title'          => 'Arc 2 (locked)',
            'rep_required'   => 50,
            'is_entry_arc'   => false,
        ]);
        QuestStage::create([
            'quest_arc_id'   => $lockedArc->id,
            'stage_number'   => 1,
            'title'          => 'Locked stage',
            'objective_text' => 'You should not see this.',
            'rep_reward'     => 0,
            'reward_creds'   => 0,
        ]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/tutorial/complete')
             ->assertOk();

        // Non-entry arc must be initialised as locked, NOT active
        $this->assertDatabaseHas('player_arc_progress', [
            'player_id'    => $player->id,
            'quest_arc_id' => $lockedArc->id,
            'status'       => 'locked',
        ]);
    }

    public function test_complete_is_idempotent(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        [$doc, $arc, $stages] = $this->scaffoldDoc(isEntryArc: true);

        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete')->assertOk();
        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete')->assertOk();

        // Exactly one arc progress row — no duplicates
        $this->assertDatabaseCount('player_arc_progress', 1);
        // Exactly two stage progress rows (one per stage) — no duplicates
        $this->assertDatabaseCount('player_stage_progress', 2);
    }

    public function test_complete_when_no_entry_arc_configured_returns_ok(): void
    {
        [$user] = $this->scaffoldPlayer();
        // No CyberDoc / arc created — endpoint must not 500

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/tutorial/complete')
             ->assertOk()
             ->assertJson(['ok' => true]);
    }

    // =========================================================================
    // 2 — GET /api/quests — dialogue visibility and met flag
    // =========================================================================

    public function test_quests_requires_auth(): void
    {
        $this->getJson('/api/quests')->assertUnauthorized();
    }

    public function test_quests_returns_doc_list(): void
    {
        [$user] = $this->scaffoldPlayer();
        $this->scaffoldDoc();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/quests')
             ->assertOk()
             ->assertJsonStructure(['docs' => [['cyber_doc_id', 'name', 'district', 'met', 'arcs']]]);
    }

    public function test_active_stage_returns_dialogue(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        [$doc, $arc, $stages] = $this->scaffoldDoc(isEntryArc: true);

        // Initialise the arc so stage 1 is active
        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete');

        $res = $this->actingAs($user, 'sanctum')
                    ->getJson('/api/quests')
                    ->assertOk();

        $stage1 = $res->json('docs.0.arcs.0.stages.0');
        $this->assertEquals('active', $stage1['status']);
        $this->assertNotNull($stage1['dialogue'], 'Active stage must return dialogue data');
        $this->assertIsArray($stage1['dialogue']);
    }

    public function test_locked_stage_returns_null_dialogue(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        [$doc, $arc, $stages] = $this->scaffoldDoc(isEntryArc: true);

        // Initialise the arc — stage 2 starts locked
        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete');

        $res = $this->actingAs($user, 'sanctum')
                    ->getJson('/api/quests')
                    ->assertOk();

        $stage2 = $res->json('docs.0.arcs.0.stages.1');
        $this->assertEquals('locked', $stage2['status']);
        $this->assertNull($stage2['dialogue'], 'Locked stage must not leak dialogue');
    }

    public function test_locked_stage_hides_objective_text(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        [$doc, $arc, $stages] = $this->scaffoldDoc(isEntryArc: true);

        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete');

        $res = $this->actingAs($user, 'sanctum')->getJson('/api/quests')->assertOk();

        $stage2 = $res->json('docs.0.arcs.0.stages.1');
        $this->assertNull($stage2['objective_text'], 'Locked stage must not expose objective text');
    }

    public function test_met_is_false_before_arc_initialised(): void
    {
        [$user] = $this->scaffoldPlayer();
        $this->scaffoldDoc();

        $res = $this->actingAs($user, 'sanctum')->getJson('/api/quests')->assertOk();
        $this->assertFalse($res->json('docs.0.met'));
    }

    public function test_met_is_true_after_arc_initialised(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        [$doc, $arc] = $this->scaffoldDoc(isEntryArc: true);

        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete');

        $res = $this->actingAs($user, 'sanctum')->getJson('/api/quests')->assertOk();
        $this->assertTrue($res->json('docs.0.met'));
    }

    public function test_dialogue_is_null_for_completed_stages(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        [$doc, $arc, $stages] = $this->scaffoldDoc(isEntryArc: true, stageDefs: [
            [
                'stage_number'   => 1,
                'title'          => 'Find Knuckle',
                'objective_text' => 'Go to BA-hub.',
                'dialogue'       => [['speaker' => 'KNUCKLE', 'text' => 'Hello.']],
                'rep_reward'     => 0,
                'reward_creds'   => 0,
            ],
        ]);

        // Initialise entry arc
        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete');
        // Complete the stage
        $this->actingAs($user, 'sanctum')
             ->postJson("/api/quests/stage/{$stages[0]->id}/complete")
             ->assertOk();

        $res = $this->actingAs($user, 'sanctum')->getJson('/api/quests')->assertOk();
        $stage1 = $res->json('docs.0.arcs.0.stages.0');
        $this->assertEquals('complete', $stage1['status']);
        $this->assertNull($stage1['dialogue'], 'Completed stage must not return dialogue');
    }

    // =========================================================================
    // 3 — POST /api/quests/stage/{id}/complete
    // =========================================================================

    public function test_stage_complete_requires_auth(): void
    {
        $stageId = \Illuminate\Support\Str::uuid()->toString();
        $this->postJson("/api/quests/stage/{$stageId}/complete")->assertUnauthorized();
    }

    public function test_stage_complete_returns_404_for_unknown_stage(): void
    {
        [$user] = $this->scaffoldPlayer();
        $stageId = \Illuminate\Support\Str::uuid()->toString();

        $this->actingAs($user, 'sanctum')
             ->postJson("/api/quests/stage/{$stageId}/complete")
             ->assertNotFound();
    }

    public function test_completing_stage_1_activates_stage_2(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        [$doc, $arc, $stages] = $this->scaffoldDoc(isEntryArc: true);

        // Initialise arc (stage 1 = active, stage 2 = locked)
        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete');

        // Complete stage 1
        $this->actingAs($user, 'sanctum')
             ->postJson("/api/quests/stage/{$stages[0]->id}/complete")
             ->assertOk();

        // Stage 1 must be complete
        $this->assertDatabaseHas('player_stage_progress', [
            'player_id'      => $player->id,
            'quest_stage_id' => $stages[0]->id,
            'status'         => 'complete',
        ]);

        // Stage 2 must now be active
        $this->assertDatabaseHas('player_stage_progress', [
            'player_id'      => $player->id,
            'quest_stage_id' => $stages[1]->id,
            'status'         => 'active',
        ]);
    }

    public function test_completing_last_stage_marks_arc_complete(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        [$doc, $arc, $stages] = $this->scaffoldDoc(isEntryArc: true, stageDefs: [
            [
                'stage_number'   => 1,
                'title'          => 'Only stage',
                'objective_text' => 'Do the thing.',
                'rep_reward'     => 0,
                'reward_creds'   => 0,
            ],
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete');
        $this->actingAs($user, 'sanctum')
             ->postJson("/api/quests/stage/{$stages[0]->id}/complete")
             ->assertOk();

        $this->assertDatabaseHas('player_arc_progress', [
            'player_id'    => $player->id,
            'quest_arc_id' => $arc->id,
            'status'       => 'complete',
        ]);
    }

    public function test_completing_stage_grants_rep(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        [$doc, $arc, $stages] = $this->scaffoldDoc(isEntryArc: true, stageDefs: [
            [
                'stage_number'   => 1,
                'title'          => 'Rep stage',
                'objective_text' => 'Earn rep.',
                'rep_reward'     => 25,
                'reward_creds'   => 0,
            ],
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete');
        $this->actingAs($user, 'sanctum')
             ->postJson("/api/quests/stage/{$stages[0]->id}/complete")
             ->assertOk();

        $this->assertDatabaseHas('player_reputation', [
            'player_id'    => $player->id,
            'cyber_doc_id' => $doc->id,
        ]);

        $rep = \App\Models\PlayerReputation::where('player_id', $player->id)
                                            ->where('cyber_doc_id', $doc->id)
                                            ->first();
        $this->assertGreaterThanOrEqual(25, $rep->score);
    }

    public function test_completing_stage_grants_wallet_creds(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        [$doc, $arc, $stages] = $this->scaffoldDoc(isEntryArc: true, stageDefs: [
            [
                'stage_number'   => 1,
                'title'          => 'Cred stage',
                'objective_text' => 'Earn creds.',
                'rep_reward'     => 0,
                'reward_creds'   => 100,
            ],
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete');
        $this->actingAs($user, 'sanctum')
             ->postJson("/api/quests/stage/{$stages[0]->id}/complete")
             ->assertOk();

        $this->assertEquals(100, $player->fresh()->wallet_creds);
    }

    public function test_completing_stage_with_referral_exposes_referral_in_quest_log(): void
    {
        // Referrals work in two phases:
        //   1. Completing the stage records referral_text on the referred doc in the
        //      quest log response (so the player sees the intro blurb).
        //   2. The referred doc's arc is only initialised when the player visits that
        //      doc via POST /api/cyberdoc/visit — not automatically on stage completion.
        [$user, $player] = $this->scaffoldPlayer();

        // Knuckle's doc (entry arc)
        [$knuckleDoc, $knuckleArc, $knuckleStages] = $this->scaffoldDoc(
            canvasId:   'BA-hub',
            district:   "Browne's Addition",
            isEntryArc: true,
            stageDefs: [
                [
                    'stage_number'   => 1,
                    'title'          => 'Find Knuckle',
                    'objective_text' => 'Go to BA-hub.',
                    'rep_reward'     => 0,
                    'reward_creds'   => 0,
                ],
            ],
        );

        // Veil's doc (will be referred from Knuckle stage 1)
        [$veilDoc, $veilArc, $veilStages] = $this->scaffoldDoc(
            canvasId:   'DT-hub',
            district:   'Downtown',
            isEntryArc: false,
            stageDefs: [
                [
                    'stage_number'   => 1,
                    'title'          => 'Find Veil',
                    'objective_text' => 'Go Downtown.',
                    'rep_reward'     => 0,
                    'reward_creds'   => 0,
                ],
            ],
        );

        // Wire the referral onto Knuckle's stage 1
        $knuckleStages[0]->update([
            'referral_doc_id' => $veilDoc->id,
            'referral_text'   => "Knuckle: Go see Veil.",
        ]);

        // Initialise Knuckle's arc and complete stage 1
        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete');
        $this->actingAs($user, 'sanctum')
             ->postJson("/api/quests/stage/{$knuckleStages[0]->id}/complete")
             ->assertOk()
             ->assertJsonPath('referral_issued', true);

        // Veil's doc must appear in the quest log with a referral blurb
        $docs = $this->actingAs($user, 'sanctum')
                     ->getJson('/api/quests')
                     ->assertOk()
                     ->json('docs');

        $veilEntry = collect($docs)->firstWhere('cyber_doc_id', $veilDoc->id);
        $this->assertNotNull($veilEntry, 'Veil doc must appear in quest log');
        $this->assertNotNull($veilEntry['referral'], 'Referral text must be set after stage completion');

        // Veil's arc must NOT be initialised yet — player hasn't visited the doc
        $this->assertDatabaseMissing('player_arc_progress', [
            'player_id'    => $player->id,
            'quest_arc_id' => $veilArc->id,
        ]);
    }

    public function test_visiting_cyberdoc_initialises_arc(): void
    {
        // POST /api/cyberdoc/visit triggers initArcForDoc.
        // The endpoint resolves which doc to initialise from the player's
        // current_node_id — place the player there before posting.
        [$user, $player] = $this->scaffoldPlayer();

        [$doc, $arc, $stages] = $this->scaffoldDoc(
            canvasId:   'BA-hub',
            district:   "Browne's Addition",
            isEntryArc: true,
            stageDefs: [
                [
                    'stage_number'   => 1,
                    'title'          => 'Find Knuckle',
                    'objective_text' => 'Go to BA-hub.',
                    'rep_reward'     => 0,
                    'reward_creds'   => 0,
                ],
            ],
        );

        // Move the player onto the cyberdoc node
        $node = Node::where('canvas_id', 'BA-hub')->first();
        $player->update(['current_node_id' => $node->id]);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/cyberdoc/visit')
             ->assertOk();

        // Arc must now be initialised as active (it's the entry arc)
        $this->assertDatabaseHas('player_arc_progress', [
            'player_id'    => $player->id,
            'quest_arc_id' => $arc->id,
            'status'       => 'active',
        ]);
    }

    // =========================================================================
    // 4 — Tutorial state persistence (GET / PATCH /api/tutorial/state)
    // =========================================================================

    public function test_tutorial_state_requires_auth(): void
    {
        $this->getJson('/api/tutorial/state')->assertUnauthorized();
        $this->patchJson('/api/tutorial/state', [])->assertUnauthorized();
    }

    public function test_tutorial_state_returns_null_by_default(): void
    {
        [$user] = $this->scaffoldPlayer();

        $res = $this->actingAs($user, 'sanctum')
                    ->getJson('/api/tutorial/state')
                    ->assertOk();

        $this->assertNull($res->json('tutorial_state'));
    }

    public function test_tutorial_state_round_trips(): void
    {
        [$user] = $this->scaffoldPlayer();

        $state = [
            'tutorialSeen'    => true,
            'tutorialSkipped' => false,
            'stepsDone'       => ['inspect' => true, 'move' => true],
            'questsRewarded'  => ['q1_movement', 'q2_manual'],
            'hasBadge'        => false,
        ];

        $this->actingAs($user, 'sanctum')
             ->patchJson('/api/tutorial/state', ['tutorial_state' => $state])
             ->assertOk()
             ->assertJsonPath('tutorial_state.tutorialSeen', true)
             ->assertJsonPath('tutorial_state.questsRewarded.0', 'q1_movement');

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/tutorial/state')
             ->assertOk()
             ->assertJsonPath('tutorial_state.tutorialSeen', true)
             ->assertJsonPath('tutorial_state.stepsDone.inspect', true);
    }

    public function test_tutorial_reward_credits_wallet(): void
    {
        [$user, $player] = $this->scaffoldPlayer();

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/tutorial/reward', [
                 'quest_id' => 'q1_movement',
                 'amount'   => 50,
             ])
             ->assertOk()
             ->assertJsonPath('wallet_creds', 50);

        $this->assertEquals(50, $player->fresh()->wallet_creds);
    }

    public function test_tutorial_reward_accumulates(): void
    {
        [$user, $player] = $this->scaffoldPlayer();

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/tutorial/reward', ['quest_id' => 'q1_movement', 'amount' => 50])
             ->assertOk();

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/tutorial/reward', ['quest_id' => 'q3_hack', 'amount' => 100])
             ->assertOk()
             ->assertJsonPath('wallet_creds', 150);
    }
}
