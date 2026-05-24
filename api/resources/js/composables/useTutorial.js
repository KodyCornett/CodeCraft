/**
 * useTutorial
 *
 * Manages tutorial quest state — stored in localStorage so progress persists
 * across page reloads without requiring a backend migration.
 *
 * Quest rewards (wallet_creds) are credited server-side via POST /api/tutorial/reward.
 * Rewards go directly to wallet — they cannot be stolen by other players in PvP.
 *
 * To add a new quest: append an entry to QUEST_DEFS below.
 * Quest order determines unlock order — each quest unlocks when the previous
 * quest's steps are all complete.
 */

import { ref, computed } from 'vue';
import axios from 'axios';

const STORAGE_KEY = 'codecraft_tutorial_v1';

// ── Quest definitions ─────────────────────────────────────────────────────────
// Add future quests here. Order = unlock order.
const QUEST_DEFS = [
    {
        id:       'q1_movement',
        label:    'FIRST STEPS',
        subtitle: 'Learn to move across the grid',
        steps: [
            { id: 'inspect', label: 'Click any node on the map' },
            { id: 'move',    label: 'Press [JACK IN] to move to that node' },
        ],
        reward: 50,
    },
    {
        id:       'q2_manual',
        label:    'PRE-BREACH PROTOCOL',
        subtitle: 'Know the breach engine before you hit a node',
        steps: [
            { id: 'read_manual', label: 'Open the Grid-Breach Manual in SPLICE' },
        ],
        reward: 25,
    },
    {
        id:       'q3_hack',
        label:    'FIRST BREACH',
        subtitle: 'Hit a node and take its cache',
        steps: [
            { id: 'hack', label: 'Attempt a hack on any node you\'re standing on' },
        ],
        reward: 100,
    },
    {
        id:       'q4_cyberdoc',
        label:    'FIND A SAFE HARBOUR',
        subtitle: 'Pocket creds are lost on a PvP kill — bank them first',
        steps: [
            { id: 'visit_cyberdoc', label: 'Move to any CyberDoc node on the map' },
        ],
        reward: 50,
    },
];

// ── Storage helpers ───────────────────────────────────────────────────────────
function loadState() {
    try {
        const raw = localStorage.getItem(STORAGE_KEY);
        return raw ? JSON.parse(raw) : null;
    } catch { return null; }
}

function defaultState() {
    return {
        tutorialSeen:    false,  // welcome modal has been acknowledged
        tutorialSkipped: false,  // player clicked Skip on the welcome modal
        stepsDone:       {},     // { [stepId]: true }
        questsRewarded:  [],     // quest ids whose wallet reward has been credited
        hasBadge:        false,  // TERMINAL NavBar badge — new update pending
    };
}

// ── Composable ────────────────────────────────────────────────────────────────
export function useTutorial() {

    const _state = ref({ ...defaultState(), ...(loadState() ?? {}) });

    function _save() {
        localStorage.setItem(STORAGE_KEY, JSON.stringify(_state.value));
    }

    // ── Computed quest list ───────────────────────────────────────────────────
    // Each quest entry is enriched with: steps[].done, allDone, rewarded, locked.
    // A quest is locked until all steps of the preceding quest are complete.
    const quests = computed(() => {
        let prevComplete = true;
        return QUEST_DEFS.map((def) => {
            const steps    = def.steps.map(s => ({ ...s, done: !!_state.value.stepsDone[s.id] }));
            const allDone  = steps.every(s => s.done);
            const rewarded = _state.value.questsRewarded.includes(def.id);
            const locked   = !prevComplete;
            prevComplete   = allDone;
            return { ...def, steps, allDone, rewarded, locked };
        });
    });

    // First unlocked, not-yet-complete quest
    const activeQuest = computed(() =>
        quests.value.find(q => !q.locked && !q.allDone) ?? null
    );

    const allComplete = computed(() => quests.value.every(q => q.allDone));

    const isTutorialActive = computed(() =>
        !_state.value.tutorialSkipped && !allComplete.value
    );

    const tutorialSeen    = computed(() => _state.value.tutorialSeen);
    const tutorialSkipped = computed(() => _state.value.tutorialSkipped);
    const hasBadge        = computed(() => _state.value.hasBadge);

    // ── Actions ───────────────────────────────────────────────────────────────

    /** Mark the welcome modal as seen (called when player opens or skips it). */
    function markSeen() {
        _state.value.tutorialSeen = true;
        _save();
    }

    /** Skip the tutorial entirely. */
    function skip() {
        _state.value.tutorialSeen    = true;
        _state.value.tutorialSkipped = true;
        _save();
    }

    /** Clear the TERMINAL NavBar badge (called when player opens the TERMINAL page). */
    function clearBadge() {
        if (!_state.value.hasBadge) return;
        _state.value.hasBadge = false;
        _save();
    }

    /**
     * markStepDone(stepId)
     *
     * Idempotent — safe to call multiple times for the same step.
     * Only accepts step IDs that belong to the currently active quest,
     * so steps cannot be completed out of order.
     * When all steps of a quest are done the wallet reward is credited.
     */
    async function markStepDone(stepId) {
        if (_state.value.tutorialSkipped)   return;
        if (_state.value.stepsDone[stepId]) return;   // already done

        const active = activeQuest.value;
        if (!active) return;

        // Guard: only accept steps belonging to the active quest
        if (!active.steps.find(s => s.id === stepId)) return;

        _state.value.stepsDone[stepId] = true;
        _state.value.hasBadge          = true;
        _save();

        // If this step completed the quest, credit the reward
        const questNowDone = active.steps.every(s => _state.value.stepsDone[s.id]);
        if (questNowDone) {
            await _creditReward(active);
        }
    }

    async function _creditReward(quest) {
        if (_state.value.questsRewarded.includes(quest.id)) return;
        try {
            await axios.post('/api/tutorial/reward', {
                quest_id: quest.id,
                amount:   quest.reward,
            });
            _state.value.questsRewarded.push(quest.id);
            _save();
        } catch (e) {
            // Non-fatal — quest stays marked done; reward can be retried on next session
            console.warn('[TUTORIAL] Reward credit failed:', e?.message);
        }
    }

    return {
        quests,
        activeQuest,
        allComplete,
        isTutorialActive,
        tutorialSeen,
        tutorialSkipped,
        hasBadge,
        markSeen,
        skip,
        clearBadge,
        markStepDone,
    };
}
