/**
 * useBankHeist
 *
 * Cross-gate state + server calls for the Bank Heist minigame. Mirrors
 * useRigDamage.js's thin axios-wrapper style — the countertrace/Anomaly
 * timers and the puzzles themselves are client-computed (same trust model
 * as GridBreach), this composable only reports discrete outcomes to
 * BankHeistController and returns the server-authoritative result.
 *
 * All formulas below are duplicated from BankHeistService.php on purpose —
 * they're locked, non-secret numbers (see BANK_HEIST_BUILD_PLAN.md), and
 * duplicating them here lets the client compute its own timer/readout the
 * same way GridBreach computes `difficulty` from node.ice, with zero
 * round-trips needed just to start playing. Every REWARD, however, is
 * always recomputed server-side from account ICE — this file's
 * accountReward() is UI-preview only, never trusted for the real payout.
 */

import { ref } from 'vue';
import axios   from 'axios';

// ── Locked formula constants — keep in sync with BankHeistService.php ──────
const TIMER_BASE          = 45;
const TIMER_RAM_MULT      = 3;
const TIMER_BONUS_MULT    = 3;
const TIMER_PENALTY_MULT  = 2;
const TIMER_FLOOR         = 15;
const WRONG_ACTION_BASE   = 5;
const WRONG_ACTION_OS_MULT = 0.4;
const LEDGER_SIZE         = { 1: 4, 2: 5, 3: 6, 4: 7 };
const INVESTMENT_PER_LEDGER = 2;
const INVESTMENT_ICE_BONUS  = 1;
const MAX_ICE                = 10;
const SALT_KEY_BASE_LENGTH   = 4;
const SALT_KEY_ICE_DIVISOR   = 3;
const FAILURE_JUMP_FLOOR     = 5;
const FAILURE_JUMP_MULT      = 4;
const PASSIVE_TICK_PER_ICE   = 0.15;
const BRUTE_FORCE_TICK_MULT  = 4;
const DECAY_PCT_PER_TICK     = 1;
const DECAY_TICK_SECONDS     = 3;
const TIMER_CUT_BY_BAND      = [0, 0.10, 0.25, 0.40]; // bands 0-3; band 4 = lockdown

/** Base countertrace/Anomaly timer (seconds) — same shape for Gate 1 and every Gate 2 account crack. */
export function baseTimer(cpu, ram, ice) {
    const base = TIMER_BASE + ram * TIMER_RAM_MULT;
    const diff = cpu - ice;
    const mod  = diff >= 0 ? diff * TIMER_BONUS_MULT : -(diff ** 2) * TIMER_PENALTY_MULT;
    return Math.max(TIMER_FLOOR, base + mod);
}

/** Seconds docked off the running timer for one wrong probe/decrypt/key attempt. */
export function wrongActionPenalty(os) {
    return Math.max(1, WRONG_ACTION_BASE - Math.round(os * WRONG_ACTION_OS_MULT));
}

export function decoyCount(bankIce) {
    return bankIce;
}

export function passiveTickRate(bankIce) {
    return bankIce * PASSIVE_TICK_PER_ICE;
}

export function bruteForceTickRate(bankIce) {
    return passiveTickRate(bankIce) * BRUTE_FORCE_TICK_MULT;
}

export function accountIce(bankIce, accountType) {
    return accountType === 'investment' ? Math.min(MAX_ICE, bankIce + INVESTMENT_ICE_BONUS) : bankIce;
}

export function ledgerSize(tier) {
    return LEDGER_SIZE[tier] ?? LEDGER_SIZE[1];
}

/** Builds the ledger's account list — deterministic from tier/bankIce, matches server. */
export function buildLedger(bankIce, tier) {
    const size = ledgerSize(tier);
    const investmentCount = Math.min(INVESTMENT_PER_LEDGER, size);
    const accounts = [];
    for (let i = 0; i < size; i++) {
        const type = i < investmentCount ? 'investment' : 'normal';
        const ice  = accountIce(bankIce, type);
        accounts.push({
            id: `acct-${i}`,
            type,
            ice,
            saltKeyLength: SALT_KEY_BASE_LENGTH + Math.floor(ice / SALT_KEY_ICE_DIVISOR),
            status: 'available', // 'available' | 'looted' | 'locked'
        });
    }
    return accounts;
}

/** UI-preview reward only — the server always recomputes this from account ICE at crack time. */
export function previewAccountReward(ice, type, bountyMultiplier = 1.0) {
    const mult = Math.max(1.0, bountyMultiplier);
    if (type === 'normal') {
        return { creds: Math.round(ice * 25 * mult), tech: 0 };
    }
    const base = ice <= 1 ? 0.25 : (ice === 2 ? 0.5 : Math.max(1.0, ice - 2));
    return { creds: 0, tech: Math.max(0.25, Math.round(base * mult * 4) / 4) };
}

export function failureJump(ice, cpu) {
    return Math.max(FAILURE_JUMP_FLOOR, (ice - cpu) * FAILURE_JUMP_MULT);
}

export function detectionBand(detection) {
    if (detection >= 100) return 4;
    if (detection >= 75)  return 3;
    if (detection >= 50)  return 2;
    if (detection >= 25)  return 1;
    return 0;
}

export function timerCutFraction(band) {
    return TIMER_CUT_BY_BAND[band] ?? 0;
}

/** Random uppercase hex string of the given length — the account's correct salt key. */
export function generateSaltKey(length) {
    const h = '0123456789ABCDEF';
    let out = '';
    for (let i = 0; i < length; i++) out += h[Math.floor(Math.random() * 16)];
    return out;
}

export function useBankHeist() {
    const busy = ref(false);

    async function post(url, body) {
        busy.value = true;
        try {
            const res = await axios.post(url, body);
            return res.data;
        } catch {
            return null;
        } finally {
            busy.value = false;
        }
    }

    /** @param {string} canvasId @param {'spoofed_handshake'|'brute_force'} approach */
    function gate1Failed(canvasId, approach) {
        return post(`/api/bank-heist/${canvasId}/gate1-failed`, { approach });
    }

    function bruteForceCleanExit(canvasId) {
        return post(`/api/bank-heist/${canvasId}/brute-force-clean-exit`, {});
    }

    /**
     * @param {string} canvasId
     * @param {'normal'|'investment'} accountType
     * @param {'success'|'clean_failed'|'abandoned'} outcome
     * @param {number} band 0-4
     */
    function accountResult(canvasId, accountType, outcome, band) {
        return post(`/api/bank-heist/${canvasId}/account-result`, {
            account_type: accountType,
            outcome,
            detection_band: band,
        });
    }

    return {
        busy,
        gate1Failed,
        bruteForceCleanExit,
        accountResult,
        // Pure helpers re-exported for convenience so components only import one module
        baseTimer, wrongActionPenalty, decoyCount, passiveTickRate, bruteForceTickRate,
        accountIce, ledgerSize, buildLedger, previewAccountReward, failureJump,
        detectionBand, timerCutFraction, generateSaltKey,
        DECAY_PCT_PER_TICK, DECAY_TICK_SECONDS,
    };
}
