<?php

namespace Tests\Feature;

use App\Models\ChassisTemplate;
use App\Models\CyberDoc;
use App\Models\Node;
use App\Models\Player;
use App\Models\PlayerCodexActivation;
use App\Models\PlayerDocumentKey;
use App\Models\PlayerRig;
use App\Models\PlayerSpliceUnlock;
use App\Models\QuestArc;
use App\Models\QuestStage;
use App\Models\SplicePage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * CodexSystemTest
 *
 * Covers the Codex investigative side system end to end:
 *
 *   1. Thread activation — via a quest stage's codex_thread_key, and its
 *      idempotency.
 *   2. GET  /api/codex/state       — active-thread flag, unresolved keys, history.
 *   3. POST /api/codex/archive-win — key-drop gating + probability sanity.
 *   4. POST /api/codex/resolve     — key -> document resolution, pool
 *      exhaustion ("nothing_left"), cross-player isolation.
 *   5. GET  /api/codex/page/{slug} — flavor auto-unlock, codex access gating,
 *      identical 404 message for nonexistent vs. unearned slugs.
 *   6. POST /api/codex/page/{id}/solve — credential matching, idempotency,
 *      reward granting, validation.
 *
 * No PHP runtime is available in the authoring sandbox this suite was
 * written in — it has been checked for balanced braces/parens and
 * cross-referenced against the live schema and service code, but has not
 * been executed. Run `php artisan test --filter=CodexSystemTest` before
 * relying on it.
 */
class CodexSystemTest extends TestCase
{
    use RefreshDatabase;

    // =========================================================================
    // Scaffold helpers
    // =========================================================================

    /** Returns [$user, $player]. */
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

    private function activateThread(Player $player, string $threadKey = 'test-thread'): PlayerCodexActivation
    {
        return PlayerCodexActivation::create([
            'player_id'  => $player->id,
            'thread_key' => $threadKey,
        ]);
    }

    private function scaffoldFlavorPage(array $overrides = []): SplicePage
    {
        return SplicePage::create(array_merge([
            'slug'       => 'flavor-' . Str::random(8),
            'type'       => 'flavor',
            'title'      => 'A Flavor Page',
            'body'       => 'Just some background reading.',
            'thread_key' => 'test-thread',
        ], $overrides));
    }

    private function scaffoldCodexPage(array $overrides = []): SplicePage
    {
        return SplicePage::create(array_merge([
            'slug'               => 'codex-' . Str::random(8),
            'type'               => 'codex',
            'title'              => 'A Codex Page',
            'body'               => 'A login prompt sits here.',
            'unlocked_body'      => 'The decrypted payload.',
            'thread_key'         => 'test-thread',
            'login_username'     => 'admin',
            'credentials'        => [['label' => 'password', 'answer' => 'Sw0rdfish']],
            'lead_slugs'         => [],
            'reward_creds'       => 100,
            'reward_tech_points' => 2.0,
        ], $overrides));
    }

    /**
     * A minimal, standalone QuestStage (own arc/doc/node) with the given
     * field overrides — no QuestStage factory exists in this codebase, so
     * this builds the same chain TutorialQuestFlowTest does by hand.
     */
    private function scaffoldMinimalStage(array $overrides = []): QuestStage
    {
        $node = Node::create([
            'canvas_id' => 'stage-' . Str::random(8),
            'x' => 0, 'y' => 0, 'type' => 'cyberdoc', 'ice' => 0,
        ]);
        $doc = CyberDoc::create(['node_id' => $node->id, 'district' => 'Test District', 'name' => 'Test Doc']);
        $arc = QuestArc::create([
            'cyber_doc_id'   => $doc->id,
            'sequence_order' => 1,
            'title'          => 'Test Arc',
            'rep_required'   => 0,
            'is_entry_arc'   => true,
        ]);

        return QuestStage::create(array_merge([
            'quest_arc_id'   => $arc->id,
            'stage_number'   => 1,
            'title'          => 'Test Stage',
            'objective_text' => 'Do the thing.',
            'rep_reward'     => 0,
            'reward_creds'   => 0,
        ], $overrides, ['quest_arc_id' => $arc->id]));
    }

    // =========================================================================
    // 1 — Thread activation
    // =========================================================================

    public function test_activate_thread_creates_activation_row(): void
    {
        [, $player] = $this->scaffoldPlayer();
        $stage = $this->scaffoldMinimalStage(['codex_thread_key' => 'monroe-street-signal']);

        app(\App\Services\CodexService::class)->activateThreadForStage($player, $stage);

        $this->assertDatabaseHas('player_codex_activations', [
            'player_id'  => $player->id,
            'thread_key' => 'monroe-street-signal',
        ]);
    }

    public function test_activate_thread_is_idempotent(): void
    {
        [, $player] = $this->scaffoldPlayer();
        $stage = $this->scaffoldMinimalStage(['codex_thread_key' => 'monroe-street-signal']);

        $service = app(\App\Services\CodexService::class);
        $service->activateThreadForStage($player, $stage);
        $service->activateThreadForStage($player, $stage);

        $this->assertDatabaseCount('player_codex_activations', 1);
    }

    public function test_stage_without_codex_thread_key_does_not_activate(): void
    {
        [, $player] = $this->scaffoldPlayer();
        $stage = $this->scaffoldMinimalStage(['codex_thread_key' => null]);

        $result = app(\App\Services\CodexService::class)->activateThreadForStage($player, $stage);

        $this->assertNull($result);
        $this->assertDatabaseCount('player_codex_activations', 0);
    }

    public function test_completing_a_real_stage_activates_its_codex_thread(): void
    {
        // Full integration: QuestService::completeStage() -> CodexService,
        // exercised the way the client actually triggers it.
        [$user, $player] = $this->scaffoldPlayer();

        $node = Node::create(['canvas_id' => 'DT-hub', 'x' => 0, 'y' => 0, 'type' => 'cyberdoc', 'ice' => 0]);
        $doc  = CyberDoc::create(['node_id' => $node->id, 'district' => 'Downtown', 'name' => 'Veil']);
        $arc  = QuestArc::create([
            'cyber_doc_id'   => $doc->id,
            'sequence_order' => 1,
            'title'          => 'Report Back',
            'rep_required'   => 0,
            'is_entry_arc'   => true,
        ]);
        $stage = QuestStage::create([
            'quest_arc_id'      => $arc->id,
            'stage_number'      => 1,
            'title'             => 'Report Back',
            'objective_text'    => 'Tell Veil what you found.',
            'rep_reward'        => 0,
            'reward_creds'      => 0,
            'codex_thread_key'  => 'monroe-street-signal',
        ]);

        $this->actingAs($user, 'sanctum')->postJson('/api/tutorial/complete');

        $res = $this->actingAs($user, 'sanctum')
                    ->postJson("/api/quests/stage/{$stage->id}/complete")
                    ->assertOk();

        $this->assertEquals('monroe-street-signal', $res->json('codex_thread_activated'));
        $this->assertDatabaseHas('player_codex_activations', [
            'player_id'  => $player->id,
            'thread_key' => 'monroe-street-signal',
        ]);
    }

    // =========================================================================
    // 2 — GET /api/codex/state
    // =========================================================================

    public function test_state_requires_auth(): void
    {
        $this->getJson('/api/codex/state')->assertUnauthorized();
    }

    public function test_state_false_with_no_active_thread(): void
    {
        [$user] = $this->scaffoldPlayer();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/codex/state')
             ->assertOk()
             ->assertJsonPath('has_active_codex', false)
             ->assertJsonPath('unresolved_keys', [])
             ->assertJsonPath('history', []);
    }

    public function test_state_true_after_activation(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $this->activateThread($player);

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/codex/state')
             ->assertOk()
             ->assertJsonPath('has_active_codex', true);
    }

    // =========================================================================
    // 3 — POST /api/codex/archive-win
    // =========================================================================

    public function test_archive_win_requires_auth(): void
    {
        $this->postJson('/api/codex/archive-win')->assertUnauthorized();
    }

    public function test_archive_win_fails_without_active_thread(): void
    {
        [$user] = $this->scaffoldPlayer();

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/codex/archive-win')
             ->assertStatus(422);

        $this->assertDatabaseCount('player_document_keys', 0);
    }

    public function test_archive_win_response_shape_matches_outcome(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $this->activateThread($player);

        $res = $this->actingAs($user, 'sanctum')
                    ->postJson('/api/codex/archive-win')
                    ->assertOk()
                    ->assertJsonStructure(['dropped', 'key_id']);

        if ($res->json('dropped')) {
            $this->assertNotNull($res->json('key_id'));
            $this->assertDatabaseHas('player_document_keys', [
                'id'        => $res->json('key_id'),
                'player_id' => $player->id,
                'status'    => 'unresolved',
            ]);
        } else {
            $this->assertNull($res->json('key_id'));
            $this->assertDatabaseCount('player_document_keys', 0);
        }
    }

    public function test_archive_win_key_drop_rate_is_within_expected_band(): void
    {
        // KEY_DROP_CHANCE is a private 0.35 constant with no injected RNG, so
        // this can't be forced deterministic without changing production
        // code. Instead: run enough trials that a false failure is
        // vanishingly unlikely (P(all-hit or all-miss) at n=300 is ~0), and
        // assert the observed rate falls in a generous band around 35%.
        [$user, $player] = $this->scaffoldPlayer();
        $this->activateThread($player);

        // This route is throttled at 10/min in production (routes/api.php);
        // 300 trials in one test would otherwise start returning 429s well
        // before the sample is large enough to be statistically meaningful.
        $this->withoutMiddleware(\Illuminate\Routing\Middleware\ThrottleRequests::class);

        $trials = 300;
        $drops  = 0;
        for ($i = 0; $i < $trials; $i++) {
            $res = $this->actingAs($user, 'sanctum')->postJson('/api/codex/archive-win')->assertOk();
            if ($res->json('dropped')) {
                $drops++;
            }
        }

        $rate = $drops / $trials;
        $this->assertGreaterThan(0.15, $rate, "Observed drop rate {$rate} implausibly low for a 35% chance over {$trials} trials.");
        $this->assertLessThan(0.55, $rate, "Observed drop rate {$rate} implausibly high for a 35% chance over {$trials} trials.");
    }

    // =========================================================================
    // 4 — POST /api/codex/resolve
    // =========================================================================

    public function test_resolve_requires_auth(): void
    {
        $this->postJson('/api/codex/resolve', ['key_id' => Str::uuid()->toString()])->assertUnauthorized();
    }

    public function test_resolve_requires_key_id(): void
    {
        [$user] = $this->scaffoldPlayer();

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/codex/resolve', [])
             ->assertStatus(422);
    }

    public function test_resolve_fails_for_unknown_key(): void
    {
        [$user] = $this->scaffoldPlayer();

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/codex/resolve', ['key_id' => Str::uuid()->toString()])
             ->assertStatus(422);
    }

    public function test_resolve_fails_for_another_players_key(): void
    {
        [, $ownerPlayer]  = $this->scaffoldPlayer();
        [$user]           = $this->scaffoldPlayer();

        $key = PlayerDocumentKey::create(['player_id' => $ownerPlayer->id, 'status' => 'unresolved']);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/codex/resolve', ['key_id' => $key->id])
             ->assertStatus(422);

        $this->assertDatabaseHas('player_document_keys', ['id' => $key->id, 'status' => 'unresolved']);
    }

    public function test_resolve_fails_for_already_resolved_key(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $key = PlayerDocumentKey::create(['player_id' => $player->id, 'status' => 'resolved']);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/codex/resolve', ['key_id' => $key->id])
             ->assertStatus(422);
    }

    public function test_resolve_returns_nothing_left_when_pool_empty(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $this->activateThread($player, 'empty-thread');
        $key = PlayerDocumentKey::create(['player_id' => $player->id, 'status' => 'unresolved']);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/codex/resolve', ['key_id' => $key->id])
             ->assertOk()
             ->assertJsonPath('outcome', 'nothing_left');

        $this->assertDatabaseHas('player_document_keys', ['id' => $key->id, 'status' => 'empty']);
    }

    public function test_resolve_flavor_page_creates_completed_unlock(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $this->activateThread($player);
        $page = $this->scaffoldFlavorPage();
        $key  = PlayerDocumentKey::create(['player_id' => $player->id, 'status' => 'unresolved']);

        $res = $this->actingAs($user, 'sanctum')
                    ->postJson('/api/codex/resolve', ['key_id' => $key->id])
                    ->assertOk();

        $this->assertEquals('document', $res->json('outcome'));
        $this->assertEquals($page->id, $res->json('page.id'));
        $this->assertDatabaseHas('player_splice_unlocks', [
            'player_id'      => $player->id,
            'splice_page_id' => $page->id,
            'status'         => 'completed',
        ]);
        $this->assertDatabaseHas('player_document_keys', [
            'id'                       => $key->id,
            'status'                   => 'resolved',
            'resolved_splice_page_id'  => $page->id,
        ]);
    }

    public function test_resolve_codex_page_creates_unresolved_unlock(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $this->activateThread($player);
        $page = $this->scaffoldCodexPage();
        $key  = PlayerDocumentKey::create(['player_id' => $player->id, 'status' => 'unresolved']);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/codex/resolve', ['key_id' => $key->id])
             ->assertOk()
             ->assertJsonPath('outcome', 'document');

        $this->assertDatabaseHas('player_splice_unlocks', [
            'player_id'      => $player->id,
            'splice_page_id' => $page->id,
            'status'         => 'unresolved',
        ]);
    }

    public function test_resolving_pool_exhausts_to_nothing_left(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $this->activateThread($player, 'small-pool');
        $pageA = $this->scaffoldFlavorPage(['thread_key' => 'small-pool']);
        $pageB = $this->scaffoldFlavorPage(['thread_key' => 'small-pool']);

        $keyIds = [];
        foreach (range(1, 3) as $i) {
            $keyIds[] = PlayerDocumentKey::create(['player_id' => $player->id, 'status' => 'unresolved'])->id;
        }

        $outcomes = [];
        foreach ($keyIds as $keyId) {
            $outcomes[] = $this->actingAs($user, 'sanctum')
                ->postJson('/api/codex/resolve', ['key_id' => $keyId])
                ->assertOk()
                ->json('outcome');
        }

        // Exactly 2 pages exist in the pool -> first two resolves must be
        // 'document', the third (nothing left to find) must be 'nothing_left'.
        $this->assertEquals(['document', 'document', 'nothing_left'], $outcomes);
        $this->assertDatabaseCount('player_splice_unlocks', 2);

        // Both pages must have been reached (order is randomised, so check
        // membership rather than sequence).
        $unlockedIds = PlayerSpliceUnlock::where('player_id', $player->id)->pluck('splice_page_id')->all();
        $this->assertContains($pageA->id, $unlockedIds);
        $this->assertContains($pageB->id, $unlockedIds);
    }

    public function test_resolve_only_considers_active_thread_pages(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $this->activateThread($player, 'active-thread');
        $this->scaffoldFlavorPage(['thread_key' => 'other-thread']); // not active for this player
        $key = PlayerDocumentKey::create(['player_id' => $player->id, 'status' => 'unresolved']);

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/codex/resolve', ['key_id' => $key->id])
             ->assertOk()
             ->assertJsonPath('outcome', 'nothing_left');
    }

    // =========================================================================
    // 5 — GET /api/codex/page/{slug}
    // =========================================================================

    public function test_show_page_requires_auth(): void
    {
        $this->getJson('/api/codex/page/some-slug')->assertUnauthorized();
    }

    public function test_show_page_404_for_nonexistent_slug(): void
    {
        [$user] = $this->scaffoldPlayer();

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/codex/page/does-not-exist')
             ->assertNotFound()
             ->assertJsonPath('message', 'Page not found.');
    }

    public function test_show_page_404_for_unearned_codex_page_with_identical_message(): void
    {
        // Regression test for the slug-enumeration fix: an unearned codex
        // page must return byte-identical 404 copy to a nonexistent slug.
        [$user] = $this->scaffoldPlayer();
        $page = $this->scaffoldCodexPage(['slug' => 'unearned-codex']);

        $res = $this->actingAs($user, 'sanctum')
                    ->getJson('/api/codex/page/unearned-codex')
                    ->assertNotFound()
                    ->assertJsonPath('message', 'Page not found.');

        $unknownRes = $this->actingAs($user, 'sanctum')
                           ->getJson('/api/codex/page/totally-made-up-slug')
                           ->assertNotFound();

        $this->assertEquals($unknownRes->json('message'), $res->json('message'));
    }

    public function test_show_page_auto_unlocks_flavor_page(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $page = $this->scaffoldFlavorPage(['slug' => 'auto-unlock-me']);

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/codex/page/auto-unlock-me')
             ->assertOk()
             ->assertJsonPath('status', 'completed');

        $this->assertDatabaseHas('player_splice_unlocks', [
            'player_id'      => $player->id,
            'splice_page_id' => $page->id,
            'status'         => 'completed',
        ]);
    }

    public function test_show_page_exposes_login_fields_once_unlocked(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $page = $this->scaffoldCodexPage(['slug' => 'login-page']);
        PlayerSpliceUnlock::create([
            'player_id'      => $player->id,
            'splice_page_id' => $page->id,
            'status'         => 'unresolved',
            'unlocked_at'    => now(),
        ]);

        $this->actingAs($user, 'sanctum')
             ->getJson('/api/codex/page/login-page')
             ->assertOk()
             ->assertJsonPath('login_username', 'admin')
             ->assertJsonPath('solved', false)
             ->assertJsonPath('credential_labels.0', 'password')
             ->assertJsonPath('unlocked_body', null);
    }

    // =========================================================================
    // 6 — POST /api/codex/page/{id}/solve
    // =========================================================================

    public function test_solve_page_requires_auth(): void
    {
        $this->postJson('/api/codex/page/' . Str::uuid() . '/solve', ['answers' => []])
             ->assertUnauthorized();
    }

    public function test_solve_page_requires_answers_array(): void
    {
        [$user] = $this->scaffoldPlayer();

        $this->actingAs($user, 'sanctum')
             ->postJson('/api/codex/page/' . Str::uuid() . '/solve', [])
             ->assertStatus(422);
    }

    public function test_solve_page_rejects_non_string_answer_values(): void
    {
        // Regression test for the validation-gap fix: array/non-scalar
        // values under `answers.*` must be rejected before reaching the
        // service (would otherwise risk an "Array to string conversion").
        [$user, $player] = $this->scaffoldPlayer();
        $page = $this->scaffoldCodexPage();
        PlayerSpliceUnlock::create([
            'player_id' => $player->id, 'splice_page_id' => $page->id,
            'status' => 'unresolved', 'unlocked_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
             ->postJson("/api/codex/page/{$page->id}/solve", [
                 'answers' => ['password' => ['nested', 'array']],
             ])
             ->assertStatus(422);
    }

    public function test_solve_page_fails_for_non_codex_page(): void
    {
        [$user] = $this->scaffoldPlayer();
        $page = $this->scaffoldFlavorPage();

        $this->actingAs($user, 'sanctum')
             ->postJson("/api/codex/page/{$page->id}/solve", ['answers' => ['x' => 'y']])
             ->assertStatus(422);
    }

    public function test_solve_page_fails_when_not_unlocked(): void
    {
        [$user] = $this->scaffoldPlayer();
        $page = $this->scaffoldCodexPage();

        $this->actingAs($user, 'sanctum')
             ->postJson("/api/codex/page/{$page->id}/solve", ['answers' => ['password' => 'Sw0rdfish']])
             ->assertStatus(422);
    }

    public function test_solve_page_wrong_answer_does_not_solve_or_reward(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $page = $this->scaffoldCodexPage();
        PlayerSpliceUnlock::create([
            'player_id' => $player->id, 'splice_page_id' => $page->id,
            'status' => 'unresolved', 'unlocked_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
             ->postJson("/api/codex/page/{$page->id}/solve", ['answers' => ['password' => 'wrong']])
             ->assertOk()
             ->assertJsonPath('solved', false);

        $this->assertEquals(0, $player->fresh()->wallet_creds);
        $this->assertDatabaseHas('player_splice_unlocks', [
            'player_id' => $player->id, 'splice_page_id' => $page->id, 'status' => 'unresolved',
        ]);
    }

    public function test_solve_page_requires_every_credential_to_match(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $page = $this->scaffoldCodexPage([
            'credentials' => [
                ['label' => 'username', 'answer' => 'vance'],
                ['label' => 'password', 'answer' => 'Sw0rdfish'],
            ],
        ]);
        PlayerSpliceUnlock::create([
            'player_id' => $player->id, 'splice_page_id' => $page->id,
            'status' => 'unresolved', 'unlocked_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
             ->postJson("/api/codex/page/{$page->id}/solve", [
                 'answers' => ['username' => 'vance', 'password' => 'wrong'],
             ])
             ->assertOk()
             ->assertJsonPath('solved', false);
    }

    public function test_solve_page_correct_answer_is_case_and_whitespace_insensitive(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $page = $this->scaffoldCodexPage();
        PlayerSpliceUnlock::create([
            'player_id' => $player->id, 'splice_page_id' => $page->id,
            'status' => 'unresolved', 'unlocked_at' => now(),
        ]);

        $res = $this->actingAs($user, 'sanctum')
                    ->postJson("/api/codex/page/{$page->id}/solve", [
                        'answers' => ['password' => '  SW0RDFISH  '],
                    ])
                    ->assertOk();

        $this->assertTrue($res->json('solved'));
        $this->assertFalse($res->json('already_solved'));
        $this->assertEquals(100, $res->json('reward_creds'));
        $this->assertEquals('The decrypted payload.', $res->json('unlocked_body'));
        $this->assertEquals(100, $player->fresh()->wallet_creds);

        $this->assertDatabaseHas('player_splice_unlocks', [
            'player_id' => $player->id, 'splice_page_id' => $page->id, 'status' => 'completed',
        ]);
    }

    public function test_solve_page_is_idempotent_and_does_not_double_grant_reward(): void
    {
        [$user, $player] = $this->scaffoldPlayer();
        $page = $this->scaffoldCodexPage();
        PlayerSpliceUnlock::create([
            'player_id' => $player->id, 'splice_page_id' => $page->id,
            'status' => 'unresolved', 'unlocked_at' => now(),
        ]);

        $this->actingAs($user, 'sanctum')
             ->postJson("/api/codex/page/{$page->id}/solve", ['answers' => ['password' => 'Sw0rdfish']])
             ->assertOk();
        $this->assertEquals(100, $player->fresh()->wallet_creds);

        $res = $this->actingAs($user, 'sanctum')
                    ->postJson("/api/codex/page/{$page->id}/solve", ['answers' => ['password' => 'Sw0rdfish']])
                    ->assertOk();

        $this->assertTrue($res->json('already_solved'));
        // Reward must not be granted a second time.
        $this->assertEquals(100, $player->fresh()->wallet_creds);
    }
}
