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
 * goes through RigService::applyDamage() with source 'pve'. All bounty
 * increments reuse BountyService::recordNodeHack() — Bank Heist does not
 * invent a parallel bounty/point system; "bounty spike" always means N
 * calls to the same hack-count mechanic every other hack already uses.
 *
 * Brute Force (the no-puzzle "survive the timer" Gate 1 approach), the
 * original probe/decrypt/slot "Spoofed Handshake" Gate 1 screen, and the
 * Ledger (the per-account crack list that used to follow Gate 2 Phase 1)
 * have all been removed. Gate 1 is now the MitM Handshake Hijack (SYN
 * intercept -> SEQ+1 cipher-chunk math -> ACK token bind) below; Gate 2 is
 * the Token Reconstruction & Risk Harvest loop, which replaces the Ledger +
 * old Payload Tampering screen entirely.
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

    // ── Gate 2 Phase 1 — MitM Handshake Spoof ───────────────────────────────────
    // Three-step CLI puzzle (SYN → SYN-ACK → ACK) replacing the earlier
    // Traffic Interception concept. All timer/puzzle logic here is pure and
    // client-mirrored (useBankHeist.js), same trust model as every other
    // Bank Heist timer — only the eventual timeout failure hits the server,
    // via the shared "denied at the door" path on resolveGate1Failure().

    /** Per-step base timer (seconds), before RAM/CPU-vs-ICE modifiers. Deliberately snappy — single-digit seconds, not a multi-cycle puzzle timer like the countertrace/Anomaly Countdown. */
    private const HANDSHAKE_TIMER_BASE = ['syn' => 5.0, 'syn_ack' => 6.5, 'ack' => 3.5];

    /** Timer floor per step — never drops below this even against a very high-ICE bank. */
    private const HANDSHAKE_TIMER_FLOOR = ['syn' => 3.0, 'syn_ack' => 4.0, 'ack' => 2.0];

    /** Lighter RAM/CPU-vs-ICE modifiers than the countertrace formula — proportionate to these steps' much shorter base timers. */
    private const HANDSHAKE_TIMER_RAM_MULT     = 0.3;
    private const HANDSHAKE_TIMER_BONUS_MULT   = 0.5;
    private const HANDSHAKE_TIMER_PENALTY_MULT = 0.3;

    /** Each wrong SYN-ACK cipher-chunk sum ratchets the *next* attempt's timer down by this fraction (a fresh handshake, less time) — floored so a retry is never mathematically impossible. */
    private const HANDSHAKE_RETRY_RATCHET = 0.8;
    private const HANDSHAKE_RETRY_FLOOR   = 2.0;

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
     * turned away before ever banking their run: Gate 1's Spoofed Handshake
     * timing out, Gate 2 Phase 1's MitM Handshake Spoof timing out on any of
     * its three steps, and Gate 2 Phase 2's Global Trace Meter overrunning
     * to 100%. The name is historical (this predates both redesigns) but
     * the consequence is deliberately identical across all of them — being
     * denied is being denied, regardless of which gate or step it happened
     * on.
     *
     * Applies: SS damage = BankICE (unmitigated — Firewall has no role
     * here), bounty spike = +tier to the existing hack-count, and puts the
     * node on a tiered cooldown that blocks EVERY player, not just this one.
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

        $damageResult = $rig
            ? $this->rigService->applyDamage($rig, $bankIce, 'pve', $player)
            : null;

        for ($i = 0; $i < $tier; $i++) {
            $this->bountyService->recordNodeHack($player);
        }

        $node->bank_cooldown_until = now()->addMinutes(self::TIER_COOLDOWN_MINUTES[$tier] ?? 2);
        $node->save();

        return [
            'ss_damage'           => $bankIce,
            'bounty_hacks_added'  => $tier,
            'cooldown_minutes'    => self::TIER_COOLDOWN_MINUTES[$tier] ?? 2,
            'cooldown_until'      => $node->bank_cooldown_until->toIso8601String(),
            'rig'                 => $damageResult['rig'] ?? $rig,
            'event'               => $damageResult['event'] ?? null,
        ];
    }

    // =========================================================================
    // Gate 2 Phase 1 — MitM Handshake Spoof
    // =========================================================================

    /** Base timer (seconds) for one handshake step, given effective CPU/RAM and Bank ICE. Same asymmetric CPU-vs-ICE shape as baseTimer(), different (much smaller) constants — this is three quick steps, not one long puzzle. */
    public function handshakeStepTimer(string $step, int $cpu, int $ram, int $ice): float
    {
        $base = self::HANDSHAKE_TIMER_BASE[$step] + ($ram * self::HANDSHAKE_TIMER_RAM_MULT);
        $diff = $cpu - $ice;
        $mod  = $diff >= 0
            ? $diff * self::HANDSHAKE_TIMER_BONUS_MULT
            : -($diff ** 2) * self::HANDSHAKE_TIMER_PENALTY_MULT;

        return max(self::HANDSHAKE_TIMER_FLOOR[$step], $base + $mod);
    }

    /** Timer for the Nth SYN-ACK attempt (1-indexed) — ratchets 0.8x per prior wrong-sum failure, floored at 2s. Attempt 1 always gets the full handshakeStepTimer('syn_ack', ...) value. */
    public function handshakeRetryTimer(float $baseTimer, int $attemptNumber): float
    {
        $ratcheted = $baseTimer * (self::HANDSHAKE_RETRY_RATCHET ** max(0, $attemptNumber - 1));
        return max(self::HANDSHAKE_RETRY_FLOOR, $ratcheted);
    }

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
    // Gate 2 Phase 2 — Token Reconstruction & Risk Harvest
    // =========================================================================
    // Replaces the Ledger + Payload Tampering screen entirely. A live
    // transaction queue: intercept a TX, reconstruct its forged token from
    // fragment candidates before its own timer expires, then EXTRACT to bank
    // the running total or CONTINUE to push your luck. A Global Trace Meter
    // (0-100%) ticks continuously regardless of sub-step; hitting 100% wipes
    // anything not yet extracted and costs the same "denied at the door"
    // stack as every other Bank Heist failure — see resolveGate1Failure(),
    // called with approach 'phase2_overrun'.
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

    /** TX expiration timer bands (seconds), before the ICE 7+ cut below. */
    private const PHASE2_EASY_TIMER_RANGE = [3.0, 5.0];
    private const PHASE2_HARD_TIMER_RANGE = [6.0, 9.0];
    private const PHASE2_TIMER_ICE_CUT    = 0.75;

    /** Global Trace Meter tick rate, %/sec — always running regardless of sub-step. */
    private const PHASE2_TRACE_RATE_LOW_ICE  = 0.5;
    private const PHASE2_TRACE_RATE_HIGH_ICE = 0.8;

    /** Global Trace spike on a wrong sequence / TX timeout, % range. */
    private const PHASE2_TRACE_SPIKE_LOW_ICE  = [20.0, 30.0];
    private const PHASE2_TRACE_SPIKE_HIGH_ICE = [30.0, 40.0];

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

    /** [min, max] seconds a transaction of this band lives before expiring, after the ICE 7+ cut. @param 'easy'|'hard' $band */
    public function phase2TxTimerRange(string $band, int $bankIce): array
    {
        $range = $band === 'easy' ? self::PHASE2_EASY_TIMER_RANGE : self::PHASE2_HARD_TIMER_RANGE;
        $cut   = $bankIce >= self::PHASE2_ICE_THRESHOLD ? self::PHASE2_TIMER_ICE_CUT : 1.0;
        return [$range[0] * $cut, $range[1] * $cut];
    }

    /** Global Trace Meter tick rate, %/sec. */
    public function phase2TraceRate(int $bankIce): float
    {
        return $bankIce >= self::PHASE2_ICE_THRESHOLD
            ? self::PHASE2_TRACE_RATE_HIGH_ICE
            : self::PHASE2_TRACE_RATE_LOW_ICE;
    }

    /** [min, max] Global Trace spike percentage on a wrong sequence or TX timeout. */
    public function phase2TraceSpikeRange(int $bankIce): array
    {
        return $bankIce >= self::PHASE2_ICE_THRESHOLD
            ? self::PHASE2_TRACE_SPIKE_HIGH_ICE
            : self::PHASE2_TRACE_SPIKE_LOW_ICE;
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
