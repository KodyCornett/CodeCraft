/**
 * Shared node-hack reward formula for pool templates that are binary
 * win/lose puzzles (no partial credit mid-run — completionPct is always 1.0
 * on a clean solve). Mirrors GridBreach's own reward math (its local
 * `rewardAmount` computed) so every pool template previews the same payout
 * shape a player would see from any other template.
 *
 * This is a DISPLAY value only, used for the optimistic local reward event
 * and the outcome banner text. The real, authoritative reward is computed
 * server-side in NodeController::deplete() from `resource` and the
 * completionPct a template reports — nothing here is trusted.
 */

export function computeRewardAmount({ resource, ice, bountyMultiplier = 1, playerMaxUplink = 3 }) {
    const mult = bountyMultiplier ?? 1;

    if (resource === 'creds') {
        return Math.round(ice * 25 * mult);
    }
    if (resource === 'tech') {
        let base;
        if (ice <= 1)       base = 0.25;
        else if (ice === 2) base = 0.5;
        else                base = Math.max(1, ice - 2);
        return Math.max(0.25, Math.round(base * mult * 4) / 4);
    }
    if (resource === 'uplink') {
        return playerMaxUplink;
    }
    return 0;
}

export function outcomeSuccessMessage(resource, amount) {
    if (resource === 'creds')  return `${amount} CREDS EXTRACTED — BALANCE UPDATED`;
    if (resource === 'tech')   return `${amount} TECH POINTS HARVESTED — RIG QUEUE UPDATED`;
    if (resource === 'uplink') return `UPLINK RESTORED TO ${amount} — MOVEMENT AVAILABLE`;
    return 'RESOURCE EXTRACTED';
}
