/**
 * WATCHER_TRANSITIONS
 *
 * Each entry fires the Watcher intrusion cinematic when the player leaves
 * that doc's hub node after completing the arc. The Watcher — not the doc —
 * directs the player to the next contact.
 *
 * `district` matches the `district` field on the doc object returned by
 * GET /api/quests, and is used to locate that doc's entry arc in questDocs
 * so the pending/sent state can be re-derived from server data on every
 * quest-log load (see Game.vue's questDocs watcher). This replaces the old
 * client-only provide('onDocDialogueComplete', ...) arming path, which
 * didn't survive a reload.
 */
export const WATCHER_TRANSITIONS = {
    knuckle: {
        district:   "Browne's Addition",
        leaveNode:  'BA-hub',
        signalId:   'watcher-veil-redirect',
        signalText: '[PROCESS: RESUMING]\n▓░▓░▓▓░░▓░▓░\n...Downtown...\n*SIGNAL FRAGMENTING*\n[SYS_INTEGRITY: RECOVERING]\n...she sees what he cannot...\n*HIGH_FREQ_INTERFERENCE*\n...Veil...\n[KERNEL_PULSE: ACTIVE]\n...find...her...\n[CONTAINMENT: ░░░░░░░░░░] STABILIZING',
    },
    veil: {
        district:   'Downtown',
        leaveNode:  'DT-hub',
        signalId:   'watcher-float-redirect',
        signalText: '[PROCESS: RESUMING]\n░▓░▓▓░▓░░▓▓░\n...Spokane Valley...\n*SIGNAL FRAGMENTING*\n[SYS_INTEGRITY: RECOVERING]\n...old architecture...she knows it...\n*HIGH_FREQ_INTERFERENCE*\n...Float...\n[KERNEL_PULSE: ACTIVE]\n...the salvager...\n[CONTAINMENT: ░░░░░░░░░░] STABILIZING',
    },
    float: {
        district:   'Spokane Valley',
        leaveNode:  'SV-hub',
        signalId:   'watcher-axiom-redirect',
        signalText: '[PROCESS: RESUMING]\n▓▓░░▓░▓▓░░▓░\n...University District...\n*SIGNAL FRAGMENTING*\n[SYS_INTEGRITY: RECOVERING]\n...they have been waiting...\n*HIGH_FREQ_INTERFERENCE*\n...Axiom...\n[KERNEL_PULSE: ACTIVE]\n...they already know...\n[CONTAINMENT: ░░░░░░░░░░] STABILIZING',
    },
    axiom: {
        district:   'University District',
        leaveNode:  'UD-hub',
        signalId:   'watcher-patch-redirect',
        signalText: '[PROCESS: RESUMING]\n░░▓▓░▓░░▓▓░▓\n...North Spokane...\n*SIGNAL FRAGMENTING*\n[SYS_INTEGRITY: RECOVERING]\n...under the grid...\n*HIGH_FREQ_INTERFERENCE*\n...Patch...\n[KERNEL_PULSE: ACTIVE]\n...they hear everything...\n[CONTAINMENT: ░░░░░░░░░░] STABILIZING',
    },
};
