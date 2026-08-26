<?php

namespace App\Services;

use App\Models\Node;
use App\Models\Player;
use Illuminate\Support\Facades\Cache;

/**
 * BankHeistService — all game logic for the Bank Heist PvE mini-game.
 *
 * Every formula/constant here is locked in BANK_HEIST_BUILD_PLAN.md — see
 * that doc for the reasoning behind each number. Nothing in this class is
 * a placeholder; the numbers pass is complete.
 *
 * Architecture note: unlike PacketHijackService (server-authoritative PvP,
 * backed by a persisted match table), Bank Heist follows GridBreach's
 * precedent — a client-trusted PvE minigame. There's no opponent to cheat
 * against, so the countertrace/Anomaly Countdown timers and the puzzle
 * itself run client-side (computed once from server-provided stats, same
 * as GridBreach's `difficulty` computed()); this service only resolves
 * discrete OUTCOME events (a gate failed, a transaction injected, a run
 * extracted) and applies their server-authoritative consequences — SS
 * damage, bounty, rewards, node cooldown. Phase 2 rewards are always
 * rolled here from a locked range, never trusted from the client — see
 * the Gate 2 Phase 2 section below for why that's a cache-backed buffer
 * rather than an immediate credit, unlike the old Ledger's instant payout.
 *
 * All stat reads go through RigService::effectiveStats(). All SS damage
 * goes through RigService::applyDamage() with source 'pve', mitigated by
 * effective Firewall exactly like every other PvE hack failure (see
 * RigController::damage()'s `max(1, ice - effectiveFirewall)` formula —
 * Bank Heist does not get a special unmitigated or multiplied damage rule).
 * All bounty increments reuse BountyService::recordNodeHack() — Bank Heist
 * does not invent a parallel bounty/point system; "bounty spike" always
 * means N calls to the same hack-count mechanic every other hack already
 * uses.
 *
 * Brute Force (the no-puzzle "survive the timer" Gate 1 approach), the
 * original probe/decrypt/slot "Spoofed Handshake" Gate 1 screen, and the
 * Ledger (the per-account crack list that used to follow Gate 2 Phase 1)
 * have all been removed. Gate 1 is now the Authentication Handshake — a
 * static Gateway Entry screen, then a Terminal Workspace/SYN Calculator
 * (SYN intercept -> SEQ+1 cipher-chunk math -> ACK), then a separate
 * Session Token Binding step — below; Gate 2 is the Token Reconstruction &
 * Risk Harvest loop, which replaces the Ledger + old Payload Tampering
 * screen entirely.
 */
class BankHeistService
{
    // ── Gate 1 — Countertrace Timer (Spoofed Handshake) ─────────────────────────

    /** Base timer flat component. Higher than GridBreach's 30s: Spoofed Handshake needs three full probe→decrypt→slot cycles, not one hexakey sequence. */
    private const TIMER_BASE = 45;

    /** RAM's multiplier on the base timer — picked up from GridBreach's precedent since nothing else in Gate 1 claims RAM. Lower than GridBreach's ×5 so a high-RAM rig doesn't blow the bigger base out further. */
    private const TIMER_RAM_MULT = 3;

    /** CPU-vs-BankICE asymmetric modifier multipliers — bonus small, penalty compounds. Same shape as GridBreach. */
    private const TIMER_BONUS_MULT   = 3;
    private const TIMER_PENALTY_MULT = 2;

    /** Timer floor. Higher than GridBreach's 8s: Gate 1 failure carries a bank-wide node cooldown, not just a wasted attempt. */
    private const TIMER_FLOOR = 15;

    /** Flat wrong-action time penalty before OS mitigation. */
    private const WRONG_ACTION_PENALTY_BASE = 5;

    /** OS's mitigation rate on the wrong-action penalty. */
    private const WRONG_ACTION_OS_MULT = 0.4;

    /** Node cooldown (minutes) on Gate 1 failure, by bank tier 1-4. */
    private const TIER_COOLDOWN_MINUTES = [1 => 2, 2 => 4, 3 => 6, 4 => 8];

    // ── Master Timer — one shared clock for the ENTIRE Bank Heist run ───────────
    // Replaces every phase-local timer/risk system that came before it,
    // including this class's old Gate 1 "Countertrace Timer" above (dead —
    // kept only for Brute Force/legacy reference) and the Gate 2 Global
    // Trace Meter that used to live in the Phase 2 section below. A single
    // flat MASTER_TIMER_TOTAL clock starts the moment the player presses
    // ENTER into the Terminal Workspace and runs continuously through BOTH
    // phases — Terminal Workspace, Session Token Binding, the Gate 2 queue,
    // and Token Builder all draw down the SAME clock; nothing resets it
    // phase-locally. Every miss anywhere in the run (a wrong SYN-ACK guess,
    // or a bad/expired token injection) docks a flat MISS_PENALTY off
    // whatever's left. The clock reaching 0 at any point is a full failure,
    // resolved identically through resolveGate1Failure() below (mitigated
    // SS damage + bounty + node cooldown, plus discarding any staged Gate 2
    // buffer via the 'phase2_overrun' approach) — a miss alone costs time
    // only, never stats. A successful EXTRACT before the clock runs out
    // banks whatever's staged.

    /** The whole-run clock, seconds (4 minutes) — not CPU/RAM/ICE-scaled, and not reset by moving between phases or sub-steps. */
    private const MASTER_TIMER_TOTAL = 240.0;

    /** Seconds docked off the running MASTER_TIMER_TOTAL clock for one miss, in either phase. A Gate 1 miss also rolls a full session reroll (new SYN + new cipher pool); a Gate 2 miss also drops the player back to the live queue. */
    private const MISS_PENALTY = 10.0;

    /** Cipher chunk pool size / required combo size — both step up at BankICE 7+, same threshold-band shape as the rest of Bank Heist's tier-scaled difficulty. */
    private const HANDSHAKE_ICE_THRESHOLD        = 7;
    private const HANDSHAKE_CHUNK_COUNT_LOW_ICE  = 4;
    private const HANDSHAKE_CHUNK_COUNT_HIGH_ICE = 6;
    private const HANDSHAKE_COMBO_SIZE_LOW_ICE   = 2;
    private const HANDSHAKE_COMBO_SIZE_HIGH_ICE  = 3;

    public function __construct(
        private readonly RigService   $rigService,
        private readonly BountyService $bountyService,
    ) {}

    // =========================================================================
    // Gate 1 — Countertrace Timer (Spoofed Handshake)
    // =========================================================================

    /**
     * Base countertrace/Anomaly-Countdown timer in seconds, given effective
     * CPU/RAM and the ICE being challenged (Bank ICE for Gate 1, Account ICE
     * for a Gate 2 crack — same formula shape, different ICE input).
     */
    public function baseTimer(int $cpu, int $ram, int $ice): float
    {
        $base = self::TIMER_BASE + ($ram * self::TIMER_RAM_MULT);
        $diff = $cpu - $ice;
        $mod  = $diff >= 0
            ? $diff * self::TIMER_BONUS_MULT
            : -($diff ** 2) * self::TIMER_PENALTY_MULT;

        return max(self::TIMER_FLOOR, $base + $mod);
    }

    /** Seconds docked off the countertrace/Anomaly clock for one wrong probe/decrypt/key attempt. */
    public function wrongActionPenalty(int $os): float
    {
        return max(1, self::WRONG_ACTION_PENALTY_BASE - round($os * self::WRONG_ACTION_OS_MULT));
    }

    /** Decoy count for the Spoofed Handshake readout — decoyCount = BankICE. */
    public function decoyCount(int $bankIce): int
    {
        return $bankIce;
    }

    /**
     * "Denied at the door" cost stack — shared by every way a player can be
     * turned away before ever banking their run: originally Gate 1's
     * Spoofed Handshake timing out and Gate 2 Phase 1's MitM Handshake
     * Spoof timing out; today, simply the shared Master Timer (see above)
     * reaching 0 anywhere in the run, whether that's during Gate 1's
     * Authentication Handshake or Gate 2's queue/Token Builder. The name is
     * historical (this predates all of those redesigns) but the consequence
     * is deliberately identical across all of them — being denied is being
     * denied, regardless of where in the run it happened.
     *
     * Applies: SS damage = max(1, BankICE - effectiveFirewall) — the same
     * Firewall-mitigated formula every other PvE hack failure uses (see
     * RigController::damage()), not a Bank-Heist-special unmitigated or
     * multiplied rule — bounty spike = +tier to the existing hack-count
     * (a separate calculation from SS damage, on a full failure only, never
     * on a wrong-guess session reroll), and puts the node on a tiered
     * cooldown that blocks EVERY player, not just this one.
     * A 'phase2_overrun' approach additionally discards this player's
     * staged (unbanked) Phase 2 harvest buffer — that's the entire point of
     * the overrun consequence, and it's cheaper to do it here (the one
     * place every failure funnels through) than to duplicate it at the
     * call site.
     *
     * @param 'mitm_handshake'|'phase2_overrun' $approach informational except for the phase2_overrun buffer wipe above
     */
    public function resolveGate1Failure(Player $player, Node $node, string $approach = 'mitm_handshake'): array
    {
        if ($approach === 'phase2_overrun') {
            Cache::forget($this->phase2BufferKey($player, $node));
        }

        $rig = $this->rigService->getRigForPlayer($player);
        $bankIce = (int) $node->bank_ice;
        $tier    = (int) $node->bank_tier;

        // Same Firewall-mitigated formula as every other PvE hack failure —
        // see RigController::damage()'s max(1, ice - effectiveFirewall).
        // applyDamage() itself does not mitigate; that's on the caller.
        $amount = $bankIce;
        if ($rig) {
            $effectiveFw = $this->rigService->effectiveStats($rig, $player)['firewall']['effective'] ?? 1;
            $amount      = max(1, $bankIce - $effectiveFw);
        }

        $damageResult = $rig
            ? $this->rigService->applyDamage($rig, $amount, 'pve', $player)
            : null;

        // Bounty is a separate calculation from SS damage — a full failure
        // spikes both, a wrong-guess session reroll spikes neither.
        for ($i = 0; $i < $tier; $i++) {
            $this->bountyService->recordNodeHack($player);
        }

        $node->bank_cooldown_until = now()->addMinutes(self::TIER_COOLDOWN_MINUTES[$tier] ?? 2);
        $node->save();

        return [
            'ss_damage'           => $amount,
            'bounty_hacks_added'  => $tier,
            'cooldown_minutes'    => self::TIER_COOLDOWN_MINUTES[$tier] ?? 2,
            'cooldown_until'      => $node->bank_cooldown_until->toIso8601String(),
            'rig'                 => $damageResult['rig'] ?? $rig,
            'event'               => $damageResult['event'] ?? null,
        ];
    }

    // =========================================================================
    // Gate 1 — Authentication Handshake (cipher-pool sizing only)
    // =========================================================================
    // Timing is entirely the shared Master Timer above (MASTER_TIMER_TOTAL /
    // MISS_PENALTY, mirrored in useBankHeist.js) — no server-side timer math
    // needed. Only the ICE-scaled cipher pool sizing below has real logic
    // worth mirroring here for documentation parity with useBankHeist.js.

    /** Cipher chunk pool size for the SYN-ACK puzzle — 6 chunks at BankICE 7+, 4 below that. */
    public function handshakeChunkCount(int $bankIce): int
    {
        return $bankIce >= self::HANDSHAKE_ICE_THRESHOLD
            ? self::HANDSHAKE_CHUNK_COUNT_HIGH_ICE
            : self::HANDSHAKE_CHUNK_COUNT_LOW_ICE;
    }

    /** Required combo size to hit the target ACK — 3 chunks at BankICE 7+, 2 below that. */
    public function handshakeComboSize(int $bankIce): int
    {
        return $bankIce >= self::HANDSHAKE_ICE_THRESHOLD
            ? self::HANDSHAKE_COMBO_SIZE_HIGH_ICE
            : self::HANDSHAKE_COMBO_SIZE_LOW_ICE;
    }

    // =========================================================================
    // Gate 2 Phase 2 — Ledger Spoof & Risk Harvest
    // =========================================================================
    // Replaces the Ledger + Payload Tampering screen entirely. A live
    // transaction queue: intercept a TX, reconstruct its forged token from
    // fragment candidates before its own timer expires, then EXTRACT to bank
    // the running total or CONTINUE to push your luck. There is no
    // Phase-2-local risk meter anymore — the shared Master Timer (see above)
    // is the only clock, running continuously from Gate 1 through here. A
    // failed/expired injection is a miss (flat MISS_PENALTY off that shared
    // clock, drop back to the queue); the clock reaching 0 wipes anything
    // not yet extracted and costs the same "denied at the door" stack as
    // every other Bank Heist failure — see resolveGate1Failure(), called
    // with approach 'phase2_overrun'.
    //
    // Trust model: the queue itself (timers, fragment pool, which candidates
    // are decoys) is fully client-computed like every other Bank Heist
    // puzzle — there's no opponent to cheat against. But UNLIKE the old
    // Ledger (whose reward came from a server-known account ICE), a queue
    // transaction's yield is invented client-side, so it can't be
    // recomputed from anything the server already knows. Instead, every
    // successful INJECT reports only its difficulty band + currency
    // (resolvePhase2Inject()) — the server rolls the real reward from its
    // own range and accumulates it in a short-lived cache-backed buffer
    // keyed to this player+bank; only EXTRACT (resolvePhase2Extract())
    // moves that buffer into the player's permanent balance, and an
    // overrun discards it unbanked. A modified client can misreport its
    // band, but it can never inflate the amount a given band pays out.

    private const PHASE2_ICE_THRESHOLD = 7;

    /** Required fragment count for a HARD transaction — steps up at ICE 7+. EASY is always 4. */
    private const PHASE2_EASY_FRAGMENTS      = 4;
    private const PHASE2_HARD_FRAGMENTS_LOW  = 6;
    private const PHASE2_HARD_FRAGMENTS_HIGH = 8;

    /** Decoy fragments added on top of the required count, either difficulty. */
    private const PHASE2_DECOY_COUNT = 2;

    /** Flat TX expiration timer (seconds) — not ICE-scaled, same for both bands now. Was 8.0/4.0, then 16.0/8.0; still closed before the player could even read the intercepted transaction, so both bands are now a flat 30s. */
    private const PHASE2_EASY_TIMER = 30.0;
    private const PHASE2_HARD_TIMER = 30.0;

    /** Reward ranges rolled server-side per successful inject, by band/currency. */
    private const PHASE2_EASY_CRED_RANGE = [75, 150];
    private const PHASE2_EASY_TECH_RANGE = [15, 40];
    private const PHASE2_HARD_CRED_RANGE = [350, 550];
    private const PHASE2_HARD_TECH_RANGE = [70, 130];

    /** How long a run's staged (unbanked) buffer survives in cache before it's abandoned. */
    private const PHASE2_BUFFER_TTL_MINUTES = 30;

    /** Required fragment count for the token puzzle. @param 'easy'|'hard' $band */
    public function phase2RequiredFragments(string $band, int $bankIce): int
    {
        if ($band === 'easy') return self::PHASE2_EASY_FRAGMENTS;
        return $bankIce >= self::PHASE2_ICE_THRESHOLD
            ? self::PHASE2_HARD_FRAGMENTS_HIGH
            : self::PHASE2_HARD_FRAGMENTS_LOW;
    }

    /** Decoy fragment count, both difficulties. */
    public function phase2DecoyCount(): int
    {
        return self::PHASE2_DECOY_COUNT;
    }

    /** Flat seconds a transaction of this band lives before expiring — not ICE-scaled. @param 'easy'|'hard' $band */
    public function phase2TxTimer(string $band): float
    {
        return $band === 'easy' ? self::PHASE2_EASY_TIMER : self::PHASE2_HARD_TIMER;
    }

    /**
     * Rolls the real reward for one successful inject, from this
     * band/currency's locked range — never trusted from the client.
     *
     * @param 'easy'|'hard' $band
     * @param 'CRED'|'TECH_PT' $currency
     * @return array{creds: int, tech: float}
     */
    public function phase2Reward(string $band, string $currency, float $bountyMultiplier): array
    {
        $bountyMultiplier = max(1.0, $bountyMultiplier);
        $range = match (true) {
            $band === 'easy' && $currency === 'CRED' => self::PHASE2_EASY_CRED_RANGE,
            $band === 'easy'                          => self::PHASE2_EASY_TECH_RANGE,
            $currency === 'CRED'                       => self::PHASE2_HARD_CRED_RANGE,
            default                                     => self::PHASE2_HARD_TECH_RANGE,
        };
        $rolled = mt_rand($range[0], $range[1]);

        if ($currency === 'CRED') {
            return ['creds' => (int) round($rolled * $bountyMultiplier), 'tech' => 0.0];
        }
        return ['creds' => 0, 'tech' => round($rolled * $bountyMultiplier, 2)];
    }

    /** Cache key for a run's staged (unbanked) harvest buffer — one per player+bank, never persisted to a table. */
    private function phase2BufferKey(Player $player, Node $node): string
    {
        return "bank_heist_phase2_buffer:{$player->id}:{$node->id}";
    }

    /**
     * Resolve one successful token injection: rolls the real reward from
     * the reported band/currency and adds it to this run's staged buffer.
     * Nothing is credited to the player yet — only resolvePhase2Extract()
     * does that — so an overrun before extracting loses it cleanly.
     *
     * @param 'easy'|'hard' $band
     * @param 'CRED'|'TECH_PT' $currency
     */
    public function resolvePhase2Inject(Player $player, Node $node, string $band, string $currency): array
    {
        $bountyMultiplier = (float) ($player->bounty_multiplier ?? 1.0);
        $reward = $this->phase2Reward($band, $currency, $bountyMultiplier);

        $key    = $this->phase2BufferKey($player, $node);
        $buffer = Cache::get($key, ['creds' => 0, 'tech' => 0.0]);
        $buffer['creds'] += $reward['creds'];
        $buffer['tech']   = round($buffer['tech'] + $reward['tech'], 2);
        Cache::put($key, $buffer, now()->addMinutes(self::PHASE2_BUFFER_TTL_MINUTES));

        return [
            'reward'       => $reward,
            'staged_creds' => $buffer['creds'],
            'staged_tech'  => $buffer['tech'],
        ];
    }

    /**
     * EXTRACT — banks this run's entire staged buffer to the player's
     * permanent balance and clears it. Safe to call with an empty buffer
     * (extracting with nothing staged is a no-op, not an error).
     */
    public function resolvePhase2Extract(Player $player, Node $node): array
    {
        $key    = $this->phase2BufferKey($player, $node);
        $buffer = Cache::pull($key, ['creds' => 0, 'tech' => 0.0]);

        if ($buffer['creds'] > 0) {
            $player->pocket_creds = (int) ($player->pocket_creds ?? 0) + $buffer['creds'];
        }
        if ($buffer['tech'] > 0) {
            $player->tech_points = round((float) ($player->tech_points ?? 0) + $buffer['tech'], 2);
        }
        if ($buffer['creds'] > 0 || $buffer['tech'] > 0) {
            $player->save();
        }

        return [
            'creds_extracted' => $buffer['creds'],
            'tech_extracted'  => $buffer['tech'],
        ];
    }
}
