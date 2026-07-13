/**
 * usePvpOutcomes
 *
 * Named outcome mutators for the three PvP result states:
 *   applyWin          — credit stolen creds to the winner's pocket
 *   applyLoss         — wipe pocket creds, set limp flag
 *   applyDeclinePenalty — deduct penalty, re-fetch SS, handle critical failure
 *
 * Extracted from usePvpFlow so each outcome has a clear, testable name rather
 * than being inline conditional blocks.
 */

export function usePvpOutcomes({ player, resyncPlayer, applyCriticalFailure }) {

    /**
     * Apply win outcome.
     * Adds credsStolen to the winner's pocket immediately (optimistic).
     * The resync in usePvpFlow will confirm the final pocket total.
     */
    function applyWin(result) {
        player.value.pocketCreds = (player.value.pocketCreds ?? 0) + (result.credsStolen ?? 0);
    }

    /**
     * Apply loss outcome.
     * Server has zeroed pocket_creds and set is_limping — mirror both locally
     * so the HUD updates before the resync lands.
     */
    function applyLoss() {
        player.value.pocketCreds = 0;
        player.value.isLimping   = true;
    }

    /**
     * Apply decline penalty outcome (target declined a challenge).
     * Re-fetches SS from the server because the decline penalty damage formula
     * is complex enough that guessing it client-side isn't worth the risk.
     */
    async function applyDeclinePenalty(result) {
        if (!result?.penalty) return;

        player.value.pocketCreds = result.penalty.pocket_after ?? player.value.pocketCreds;

        const meData = await resyncPlayer();
        if (meData?.rig) {
            player.value.currentSS = meData.rig.current_ss ?? player.value.currentSS;
        }

        if (result.critical_failure) {
            applyCriticalFailure(result.critical_failure);
        }
    }

    return { applyWin, applyLoss, applyDeclinePenalty };
}
