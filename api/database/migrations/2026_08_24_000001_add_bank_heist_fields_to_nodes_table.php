<?php

use App\Models\Node;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Bank Heist — schema for the mechanic locked in BANK_HEIST_BUILD_PLAN.md.
 *
 * Flags the 19 fixed canvas_ids from BANK_TARGET_ROSTER.md as bank nodes on
 * `nodes`, storing each one's tier and Bank ICE, plus a cooldown timestamp
 * for Gate 1 failure's node-level lockout. This is NOT a generic "any node
 * can be flagged as a bank" system — the trigger set is fixed and closed
 * (see BANK_HEIST_BUILD_PLAN.md's "Concept" section) and matches the same
 * canvas_ids already driving BANK_TARGET_NODES (composables/businessNodes.js)
 * and BANK_TARGET_CONFIG (constants/bankTargetConfig.js) on the frontend.
 *
 * Bank ICE is independent of the node's normal `ice` column — that column
 * drives ordinary map hacking; bank_ice drives Gate 1/Gate 2 difficulty and
 * is picked from GridBreach's locked tier bands (3-4 / 5-6 / 7-8 / 9-10)
 * rather than inherited from district defaults, since e.g. Downtown's
 * normal ICE (5-7) doesn't reach the 9-10 a Tier 4 bank needs.
 *
 * No new match/run table: Bank Heist follows GridBreach's precedent (a
 * client-trusted PvE minigame — the client computes its own timer/puzzle
 * from server-provided stats and reports only final outcomes), not Packet
 * Hijack's precedent (server-authoritative PvP state machine). There's no
 * opponent to cheat against here, so a bank_heist_runs table would be
 * unnecessary state for what BankHeistController resolves as one-shot
 * result calls, mirroring NodeController::deplete()/breach().
 */
return new class extends Migration
{
    /**
     * canvas_id => [tier, bank_ice]. One row per BANK_TARGET_ROSTER.md target.
     * Tier bands reused from GridBreach (Still Open #4 in the build plan,
     * resolved): Tier 1 = ICE 3-4, Tier 2 = 5-6, Tier 3 = 7-8, Tier 4 = 9-10.
     */
    private const BANK_TARGETS = [
        // Tier 1 — Retail & Community
        'BA-v14' => [1, 3],  // First Metro Federal Union
        'G11-v2' => [1, 4],  // Solis Micro-Lending
        'F9-v2'  => [1, 3],  // Vantage Point Securities

        // Tier 2 — Neo-Tech & Fast-Yield
        'NS-v9' => [2, 5],  // Aether Neobank
        'SV-v9' => [2, 5],  // BlueSky Index Funds
        'UD-v7' => [2, 6],  // Hyperion Venture Capital
        'I5-v2' => [2, 6],  // Pension Direct Assurance

        // Tier 3 — Institutional & High-Net-Worth
        'DT-v3'  => [3, 7],  // Ironclad Vault & Trust
        'DT-v7'  => [3, 7],  // Aegis Wealth Management
        'DT-v11' => [3, 8],  // Kurogane Fleet Bank
        'DT-v15' => [3, 8],  // Zenjin Asset Management
        'DT-v17' => [3, 7],  // Horizon Mutual Insurance

        // Tier 4 — High-Risk Apex & Specialized
        'DT-v19'  => [4, 9],   // Apex Capital Partners
        'DT-v21'  => [4, 9],   // Chronos Quantitative Management
        'H7-v3'   => [4, 10],  // Horizon Sovereign Holdings
        'I3-v4'   => [4, 10],  // Veritas Crypto-Custody
        'I5-v4'   => [4, 9],   // Nova Exchange
        'N10-v3'  => [4, 10],  // Starlight Sovereign Wealth
        'wp_-5_5' => [4, 10],  // Black-Tide Liquidity — capstone, max ICE
    ];

    public function up(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->boolean('is_bank_target')->default(false)->index()->after('splice_address');
            $table->unsignedTinyInteger('bank_tier')->nullable()->after('is_bank_target');
            $table->unsignedTinyInteger('bank_ice')->nullable()->after('bank_tier');
            // Gate 1 failure lockout — nobody (not just the failing player) can
            // re-attempt this bank until this clears. Null = no active cooldown.
            $table->timestamp('bank_cooldown_until')->nullable()->after('bank_ice');
        });

        foreach (self::BANK_TARGETS as $canvasId => [$tier, $ice]) {
            Node::where('canvas_id', $canvasId)->update([
                'is_bank_target' => true,
                'bank_tier'      => $tier,
                'bank_ice'       => $ice,
            ]);
        }
    }

    public function down(): void
    {
        Schema::table('nodes', function (Blueprint $table) {
            $table->dropColumn(['is_bank_target', 'bank_tier', 'bank_ice', 'bank_cooldown_until']);
        });
    }
};
