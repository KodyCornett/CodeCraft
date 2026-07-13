/**
 * useBountyEscalation
 *
 * Owns the hack-count → star-level escalation ladder.
 * Tracks hackCount, drives bountyTicker, shows the ICE alert banner,
 * and updates player state when a new star threshold is crossed.
 *
 * Game.vue calls checkBountyEscalation(nodeIce, firePingFn) after each hack.
 */

import { ref, computed } from 'vue';

export const BOUNTY_THRESHOLDS = [
    { hacks: 30, level: 5, multiplier: 2.25, openSeason: true,  message: '⚡ MAXIMUM HEAT — HEAVY ICE CLOSING IN' },
    { hacks: 25, level: 4, multiplier: 2.00, openSeason: true,  message: 'OPEN SEASON DECLARED — ALL PLAYERS NOTIFIED' },
    { hacks: 20, level: 3, multiplier: 1.75, openSeason: false, message: 'ICE PRIORITY TARGET — ELIMINATION ORDER ISSUED' },
    { hacks: 15, level: 2, multiplier: 1.50, openSeason: false, message: 'WARNING: ICE HAS TRACKED YOUR ACTIONS — ASSETS ON THE WAY' },
    { hacks: 10, level: 1, multiplier: 1.25, openSeason: false, message: 'ICE IS WATCHING — SENDING ASSETS AFTER TARGET' },
];

// Absolute hack counts at which each star is awarded.
export const STAR_HACK_THRESHOLDS = [10, 15, 20, 25, 30];

export function useBountyEscalation(player) {
    const hackCount   = ref(0);
    const bountyAlert = ref(null);
    let   _alertTimer = null;

    // Ticker: current hack progress toward the next star threshold.
    // Level 0 counts to 10. Each level after counts to 5.
    const bountyTicker = computed(() => {
        const n    = hackCount.value;
        const next = STAR_HACK_THRESHOLDS.find(t => n < t) ?? STAR_HACK_THRESHOLDS.at(-1);
        return { current: Math.min(n, next), max: next };
    });

    function showBountyAlert(message) {
        if (_alertTimer) clearTimeout(_alertTimer);
        bountyAlert.value = message;
        _alertTimer = setTimeout(() => { bountyAlert.value = null; }, 5000);
    }

    /**
     * Check if the current hackCount has crossed a new star threshold.
     * Updates player bounty state and fires the alert banner.
     * Pass firePingFn to trigger a threshold ping (omit to skip ping).
     * @returns {object|null} The tier that was crossed, or null if no change.
     */
    function checkBountyEscalation(nodeIce, firePingFn) {
        const n    = hackCount.value;
        const tier = BOUNTY_THRESHOLDS.find(t => n >= t.hacks);
        if (!tier || tier.level <= player.value.bountyLevel) return null;

        player.value.bountyLevel      = tier.level;
        player.value.bountyMultiplier = tier.multiplier;
        player.value.isOpenSeason     = tier.openSeason;

        showBountyAlert(tier.message);

        if (tier.level >= 2 && firePingFn) {
            firePingFn('threshold', nodeIce);
        }

        console.log(`[BOUNTY] ★${tier.level} — ${tier.message}`);
        return tier;
    }

    /**
     * Convert a raw hack count to a 0–5 star level.
     * Used on boot to restore star display from the server's run counter.
     */
    function starLevelFromCount(count) {
        return BOUNTY_THRESHOLDS.find(t => count >= t.hacks)?.level ?? 0;
    }

    return {
        hackCount,
        bountyTicker,
        bountyAlert,
        showBountyAlert,
        checkBountyEscalation,
        starLevelFromCount,
    };
}
