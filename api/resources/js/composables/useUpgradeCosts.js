/**
 * useUpgradeCosts
 *
 * Centralised upgrade cost formula for all rig stat investments.
 *
 * Two scaling axes — both compound:
 *
 *  1. SAME-STAT SCALING (+60% per point already invested in that stat)
 *     Each additional point in the same stat costs significantly more.
 *     Forces players to spread investment rather than maxing one stat early.
 *
 *  2. GLOBAL PROGRESSION SCALING (+25% per total point invested anywhere)
 *     Every upgrade you buy makes the next one — in any stat — slightly more
 *     expensive. Reflects increasing system complexity as the rig grows.
 *
 *  3. CHASSIS TIER SCALING (×1.8 per tier above 1)
 *     Higher-tier chassis have bigger caps but the hardware costs more to tune.
 *     Upgrading a BlackHat v3.0 costs far more per point than a v1.0.
 *
 * Combined formula:
 *   cost = baseCost
 *        × (1.60 ^ sameStatInvested)
 *        × (1.25 ^ totalInvested)
 *        × (1.80 ^ (chassisTier - 1))
 *
 * Base costs (first point, tier 1 chassis, zero total invested):
 *   CPU:      150₡  2 TP
 *   RAM:      100₡  1 TP
 *   OS:       120₡  1 TP
 *   Storage:   80₡  0 TP
 *   Firewall: 200₡  3 TP
 */

const BASE_COSTS = {
    cpu:      { creds: 150, tp: 2 },
    ram:      { creds: 100, tp: 1 },
    os:       { creds: 120, tp: 1 },
    storage:  { creds:  80, tp: 0 },
    firewall: { creds: 200, tp: 3 },
};

const SAME_STAT_SCALE  = 1.60;   // per point already invested in same stat
const GLOBAL_SCALE     = 1.25;   // per total point invested anywhere
const CHASSIS_SCALE    = 1.80;   // per chassis tier above 1

export function useUpgradeCosts() {

    /**
     * Compute the cost of the NEXT point in a given stat.
     *
     * @param {string} stat           - 'cpu' | 'ram' | 'os' | 'storage' | 'firewall'
     * @param {number} investedInStat - Points already invested in this specific stat
     * @param {number} totalInvested  - Total points invested across ALL stats
     * @param {number} chassisTier    - Current rig tier (1–5)
     * @returns {{ creds: number, tp: number }}
     */
    function upgradeCost(stat, investedInStat, totalInvested, chassisTier) {
        const base = BASE_COSTS[stat];
        if (!base) return { creds: 0, tp: 0 };

        const sameStatMult = Math.pow(SAME_STAT_SCALE, investedInStat);
        const globalMult   = Math.pow(GLOBAL_SCALE,    totalInvested);
        const chassisMult  = Math.pow(CHASSIS_SCALE,   chassisTier - 1);

        const combined = sameStatMult * globalMult * chassisMult;

        return {
            creds: Math.round(base.creds * combined),
            tp:    Math.round(base.tp    * combined),
        };
    }

    /**
     * Sum of all invested points across every stat.
     * Used as the global progression counter.
     *
     * @param {Object} investedPoints - rig.investedPoints object
     * @returns {number}
     */
    function totalInvested(investedPoints) {
        if (!investedPoints) return 0;
        return Object.values(investedPoints).reduce((sum, v) => sum + (v ?? 0), 0);
    }

    /**
     * Whether the stat can still be upgraded (below chassis cap).
     *
     * @param {string} stat
     * @param {Object} rig  - full rig ref value
     * @returns {boolean}
     */
    function canUpgrade(stat, rig) {
        // rig[stat] is already the effective stat (base + invested) from RigService.
        // Do NOT add investedPoints again — that would double-count invested points.
        const current = rig[stat] ?? 0;
        const cap     = rig.caps?.[stat] ?? current;
        return current < cap;
    }

    /**
     * Effective stat value = base chassis stat + invested points.
     * rig[stat] is already this value as returned by RigService::effectiveStats().
     */
    function effectiveStat(stat, rig) {
        return rig[stat] ?? 0;
    }

    return { upgradeCost, totalInvested, canUpgrade, effectiveStat, BASE_COSTS };
}
