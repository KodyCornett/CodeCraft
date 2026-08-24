<?php

namespace App\Services;

use App\Models\Node;
use App\Models\Player;

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
 * discrete OUTCOME events (a gate failed, an account cracked, a lockdown
 * hit) and applies their server-authoritative consequences — SS damage,
 * bounty, rewards, node cooldown. Rewards are always recomputed here from
 * account ICE, never trusted from the client, mirroring
 * NodeController::deplete()'s exact pattern.
 *
 * All stat reads go through RigService::effectiveStats(). All SS damage
 * goes through RigService::applyDamage() with source 'pve'. All bounty
 * increments reuse BountyService::recordNodeHack() — Bank Heist does not
 * invent a parallel bounty/point system; "bounty spike" always means N
 * calls to the same hack-count mechanic every other hack already uses.
 */
class BankHeistService
{
    // ── Gate 1 — Countertrace Timer (shared by Spoofed Handshake & Brute Force) ─

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

    /** Brute Force's detection tick rate is this multiple of Gate 2's baseline passive tick. */
    private const BRUTE_FORCE_TICK_MULT = 4;

    /** Gate 2 passive detection tick, %/sec, scaled by BankICE. */
    private const PASSIVE_TICK_PER_ICE = 0.15;

    // ── Gate 2 — Ledger & Accounts ──────────────────────────────────────────────

    /** Ledger size (account count) by bank tier 1-4. */
    private const LEDGER_SIZE = [1 => 4, 2 => 5, 3 => 6, 4 => 7];

    /** Investment accounts per ledger, regardless of tier (rest are Normal). */
    private const INVESTMENT_ACCOUNTS_PER_LEDGER = 2;

    /** Investment account ICE bonus over the bank's own ICE, capped at 10. */
    private const INVESTMENT_ICE_BONUS = 1;
    private const MAX_ICE               = 10;

    /** Salt-key length formula constants: 4 + floor(accountIce / 3). */
    private const SALT_KEY_BASE_LENGTH = 4;
    private const SALT_KEY_ICE_DIVISOR = 3;

    /** Failure-jump (detection spike) formula constants. */
    private const FAILURE_JUMP_FLOOR = 5;
    private const FAILURE_JUMP_MULT  = 4;

    /** Detection Bar threshold-band SS damage, keyed by band index (0-4). Bands 0-1 deal none. */
    private const THRESHOLD_SS_FRACTION = [2 => 0.5, 3 => 1.0, 4 => 2.0];

    /** Anomaly Countdown timer-cut fraction per detection band (0-3). Band 4 forces eject instead. */
    private const TIMER_CUT_BY_BAND = [0 => 0.0, 1 => 0.10, 2 => 0.25, 3 => 0.40];

    public function __construct(
        private readonly RigService   $rigService,
        private readonly BountyService $bountyService,
    ) {}

    // =========================================================================
    // Gate 1 — Countertrace Timer (shared by both approaches)
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

    /** Gate 2 passive detection tick, %/sec. */
    public function passiveTickRate(int $bankIce): float
    {
        return $bankIce * self::PASSIVE_TICK_PER_ICE;
    }

    /** Brute Force's detection tick, %/sec — 4x Gate 2's baseline. */
    public function bruteForceTickRate(int $bankIce): float
    {
        return $this->passiveTickRate($bankIce) * self::BRUTE_FORCE_TICK_MULT;
    }

    /**
     * Gate 1 failure — shared cost stack for both Spoofed Handshake and a
     * Brute Force run caught before its timer completes (Brute Force
     * borrows Gate 2's detection mechanics but NOT its capture consequences
     * — a Gate 1 failure is a Gate 1 failure regardless of approach).
     *
     * Applies: SS damage = BankICE (unmitigated — Firewall has no role in
     * Gate 1), bounty spike = +tier to the existing hack-count, and puts
     * the node on a tiered cooldown that blocks EVERY player, not just
     * this one.
     */
    public function resolveGate1Failure(Player $player, Node $node): array
    {
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

    /**
     * Brute Force's unavoidable tax on a fully clean exit — flat +1 to the
     * hack-count regardless of tier. Deliberately flat (not tier-scaled
     * like a failure bounty) since this fires even on a PERFECT run — it's
     * the price of going loud at all, not a penalty.
     */
    public function resolveBruteForceCleanExit(Player $player): array
    {
        $this->bountyService->recordNodeHack($player);

        return ['bounty_hacks_added' => 1];
    }

    // =========================================================================
    // Gate 2 — Ledger, Accounts, Detection
    // =========================================================================

    /** Account ICE — Normal sits at the bank's own ICE, Investment is +1 (capped at 10). */
    public function accountIce(int $bankIce, string $accountType): int
    {
        return $accountType === 'investment'
            ? min(self::MAX_ICE, $bankIce + self::INVESTMENT_ICE_BONUS)
            : $bankIce;
    }

    /** Ledger account count for a bank tier. */
    public function ledgerSize(int $tier): int
    {
        return self::LEDGER_SIZE[$tier] ?? self::LEDGER_SIZE[1];
    }

    /** How many of the ledger's accounts should be Investment type. */
    public function investmentAccountCount(int $tier): int
    {
        return min(self::INVESTMENT_ACCOUNTS_PER_LEDGER, $this->ledgerSize($tier));
    }

    /** Salt-key length (hex characters) for an account's CLI re-signing step. */
    public function saltKeyLength(int $accountIce): int
    {
        return self::SALT_KEY_BASE_LENGTH + intdiv($accountIce, self::SALT_KEY_ICE_DIVISOR);
    }

    /**
     * Per-account reward — reuses NodeController::deplete()'s exact existing
     * formulas verbatim, substituting accountIce for node.ice and always at
     * full completion (a crack success is binary, not partial progress).
     * Zero new economy math.
     *
     * @return array{creds: int, tech: float}
     */
    public function accountReward(int $accountIce, string $accountType, float $bountyMultiplier): array
    {
        $bountyMultiplier = max(1.0, $bountyMultiplier);

        if ($accountType === 'normal') {
            return [
                'creds' => (int) round($accountIce * 25 * $bountyMultiplier),
                'tech'  => 0.0,
            ];
        }

        $techBase = $accountIce <= 1
            ? 0.25
            : ($accountIce === 2 ? 0.5 : max(1.0, $accountIce - 2));

        $tech = max(0.25, round($techBase * $bountyMultiplier * 4) / 4);

        return ['creds' => 0, 'tech' => $tech];
    }

    /** Discrete detection spike on a failed account crack. */
    public function failureJump(int $accountIce, int $cpu): float
    {
        return max(self::FAILURE_JUMP_FLOOR, ($accountIce - $cpu) * self::FAILURE_JUMP_MULT);
    }

    /** Detection band index (0-4) for a 0-100 detection value. */
    public function detectionBand(float $detection): int
    {
        return match (true) {
            $detection >= 100 => 4,
            $detection >= 75  => 3,
            $detection >= 50  => 2,
            $detection >= 25  => 1,
            default            => 0,
        };
    }

    /** Anomaly Countdown timer-cut fraction for a detection band (0-3 only — band 4 forces eject). */
    public function timerCutFraction(int $band): float
    {
        return self::TIMER_CUT_BY_BAND[$band] ?? 0.0;
    }

    /** Threshold-band SS damage — reuses the "SS = ICE, unmitigated" precedent locked for Gate 1. */
    public function thresholdSsDamage(int $band, int $bankIce): int
    {
        $fraction = self::THRESHOLD_SS_FRACTION[$band] ?? 0.0;
        return (int) round($bankIce * $fraction);
    }

    /**
     * Resolve one account-crack event: success, clean failure, or abandoned
     * mid-attempt. A detection band of 4 always overrides the outcome to a
     * forced Lockdown, regardless of what the client reports, per the
     * Detection Bar's Threshold Consequences.
     *
     * @param 'normal'|'investment' $accountType
     * @param 'success'|'clean_failed'|'abandoned' $outcome
     * @param int $detectionBand 0-4, computed client-side from the running detection bar
     */
    public function resolveAccountEvent(
        Player $player,
        Node $node,
        string $accountType,
        string $outcome,
        int $detectionBand,
    ): array {
        $rig     = $this->rigService->getRigForPlayer($player);
        $bankIce = (int) $node->bank_ice;
        $tier    = (int) $node->bank_tier;
        $accountIce = $this->accountIce($bankIce, $accountType);

        // Detection at 100% always wins — forced eject, unsecured progress lost,
        // regardless of what outcome the client thought it was reporting.
        if ($detectionBand >= 4) {
            $amount = $bankIce * (int) self::THRESHOLD_SS_FRACTION[4];
            $damageResult = $rig ? $this->rigService->applyDamage($rig, $amount, 'pve', $player) : null;
            for ($i = 0; $i < $tier; $i++) {
                $this->bountyService->recordNodeHack($player);
            }

            return [
                'outcome'            => 'lockdown',
                'reward'             => ['creds' => 0, 'tech' => 0.0],
                'ss_damage'          => $amount,
                'bounty_hacks_added' => $tier,
                'rig'                => $damageResult['rig'] ?? $rig,
                'event'              => $damageResult['event'] ?? null,
            ];
        }

        $result = [
            'outcome'            => $outcome,
            'reward'             => ['creds' => 0, 'tech' => 0.0],
            'ss_damage'          => 0,
            'bounty_hacks_added' => 0,
            'failure_jump'       => 0.0,
            'rig'                => $rig,
            'event'              => null,
        ];

        // Threshold-band SS damage applies on every event once ICE is actively
        // engaged (band >= 2) — approximates "SS damage on each further
        // tick/failure" without a continuously-ticking server-side clock.
        $thresholdDamage = $this->thresholdSsDamage($detectionBand, $bankIce);
        if ($thresholdDamage > 0 && $rig) {
            $damageResult   = $this->rigService->applyDamage($rig, $thresholdDamage, 'pve', $player);
            $result['rig']  = $damageResult['rig'];
            $result['event'] = $damageResult['event'];
            $result['ss_damage'] += $thresholdDamage;
        }

        if ($outcome === 'success') {
            $bountyMultiplier = (float) ($player->bounty_multiplier ?? 1.0);
            $reward = $this->accountReward($accountIce, $accountType, $bountyMultiplier);
            $result['reward'] = $reward;

            if ($reward['creds'] > 0) {
                $player->pocket_creds = (int) ($player->pocket_creds ?? 0) + $reward['creds'];
            }
            if ($reward['tech'] > 0) {
                $player->tech_points = round((float) ($player->tech_points ?? 0) + $reward['tech'], 2);
            }
            $player->save();

        } elseif ($outcome === 'clean_failed') {
            $stats = $rig ? $this->rigService->effectiveStats($rig, $player) : null;
            $cpu   = $stats['cpu']['effective'] ?? 1;
            $result['failure_jump'] = $this->failureJump($accountIce, $cpu);

        } elseif ($outcome === 'abandoned') {
            // Costlier abort case — extra SS damage + a flat bounty jump on top
            // of the reward already being forfeited (no separate detection spike).
            if ($rig) {
                $damageResult = $this->rigService->applyDamage($rig, $accountIce, 'pve', $player);
                $result['rig'] = $damageResult['rig'];
                $result['event'] = $damageResult['event'] ?? $result['event'];
                $result['ss_damage'] += $accountIce;
            }
            $this->bountyService->recordNodeHack($player);
            $result['bounty_hacks_added'] += 1;
        }

        return $result;
    }
}
