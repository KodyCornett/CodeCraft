/**
 * WATCHER_TRANSITIONS
 *
 * Each entry fires the Watcher intrusion cinematic when the player leaves
 * that doc's hub node after completing the arc. The Watcher — not the doc —
 * directs the player to the next contact.
 *
 * Consumed by provide('onDocDialogueComplete', ...) in Game.vue.
 */
export const WATCHER_TRANSITIONS = {
    knuckle: {
        leaveNode:  'BA-hub',
        signalId:   'watcher-veil-redirect',
        signalText: '[PROCESS: RESUMING]\n▓░▓░▓▓░░▓░▓░\n...Downtown...\n*SIGNAL FRAGMENTING*\n[SYS_INTEGRITY: RECOVERING]\n...she sees what he cannot...\n*HIGH_FREQ_INTERFERENCE*\n...Veil...\n[KERNEL_PULSE: ACTIVE]\n...find...her...\n[CONTAINMENT: ░░░░░░░░░░] STABILIZING',
    },
    veil: {
        leaveNode:  'DT-hub',
        signalId:   'watcher-float-redirect',
        signalText: '[PROCESS: RESUMING]\n░▓░▓▓░▓░░▓▓░\n...Spokane Valley...\n*SIGNAL FRAGMENTING*\n[SYS_INTEGRITY: RECOVERING]\n...old architecture...she knows it...\n*HIGH_FREQ_INTERFERENCE*\n...Float...\n[KERNEL_PULSE: ACTIVE]\n...the salvager...\n[CONTAINMENT: ░░░░░░░░░░] STABILIZING',
    },
    float: {
        leaveNode:  'SV-hub',
        signalId:   'watcher-axiom-redirect',
        signalText: '[PROCESS: RESUMING]\n▓▓░░▓░▓▓░░▓░\n...University District...\n*SIGNAL FRAGMENTING*\n[SYS_INTEGRITY: RECOVERING]\n...they have been waiting...\n*HIGH_FREQ_INTERFERENCE*\n...Axiom...\n[KERNEL_PULSE: ACTIVE]\n...they already know...\n[CONTAINMENT: ░░░░░░░░░░] STABILIZING',
    },
    axiom: {
        leaveNode:  'UD-hub',
        signalId:   'watcher-patch-redirect',
        signalText: '[PROCESS: RESUMING]\n░░▓▓░▓░░▓▓░▓\n...North Spokane...\n*SIGNAL FRAGMENTING*\n[SYS_INTEGRITY: RECOVERING]\n...under the grid...\n*HIGH_FREQ_INTERFERENCE*\n...Patch...\n[KERNEL_PULSE: ACTIVE]\n...they hear everything...\n[CONTAINMENT: ░░░░░░░░░░] STABILIZING',
    },
};
