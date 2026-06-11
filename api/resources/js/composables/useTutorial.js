/**
 * useTutorial
 *
 * Manages tutorial quest state — persisted server-side via GET/PATCH /api/tutorial/state
 * so it survives browser clears, device switches, and can be reset via player:reset.
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

// ── Quest definitions ─────────────────────────────────────────────────────────
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
            { id: 'hack', label: "Attempt a hack on any node you're standing on" },
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

function defaultState() {
    return {
        tutorialSeen:    false,
        tutorialSkipped: false,
        stepsDone:       {},
        questsRewarded:  [],
        hasBadge:        false,
    };
}

// ── Composable ────────────────────────────────────────────────────────────────
export function useTutorial() {

    const _state   = ref(defaultState());
    const _syncing = ref(false);   // prevents overlapping PATCH calls

    // ── Hydration (called once on game boot from useGameState / Game.vue) ─────
    async function hydrate() {
        try {
            const { data } = await axios.get('/api/tutorial/state');
            _state.value = { ...defaultState(), ...(data.tutorial_state ?? {}) };
        } catch (e) {
            console.warn('[TUTORIAL] Failed to load state from server:', e?.message);
        }
    }

    // ── Persist to server ─────────────────────────────────────────────────────
    async function _save() {
        if (_syncing.value) return;
        _syncing.value = true;
        try {
            await axios.patch('/api/tutorial/state', {
                tutorial_state: _state.value,
            });
        } catch (e) {
            console.warn('[TUTORIAL] Failed to persist state:', e?.message);
        } finally {
            _syncing.value = false;
        }
    }

    // ── Computed quest list ───────────────────────────────────────────────────
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

    function markSeen() {
        _state.value.tutorialSeen = true;
        _save();
    }

    function skip() {
        _state.value.tutorialSeen    = true;
        _state.value.tutorialSkipped = true;
        _save();
    }

    function clearBadge() {
        if (!_state.value.hasBadge) return;
        _state.value.hasBadge = false;
        _save();
    }

    async function markStepDone(stepId) {
        if (_state.value.tutorialSkipped)   return;
        if (_state.value.stepsDone[stepId]) return;

        const active = activeQuest.value;
        if (!active) return;
        if (!active.steps.find(s => s.id === stepId)) return;

        _state.value.stepsDone[stepId] = true;
        _state.value.hasBadge          = true;
        await _save();

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
            await _save();

            // If every quest is now rewarded, the tutorial is complete.
            // Fire the complete signal so the server unlocks the Knuckle arc.
            const allRewarded = QUEST_DEFS.every(q => _state.value.questsRewarded.includes(q.id));
            if (allRewarded) {
                await _completeTutorial();
            }
        } catch (e) {
            console.warn('[TUTORIAL] Reward credit failed:', e?.message);
        }
    }

    async function _completeTutorial() {
        try {
            await axios.post('/api/tutorial/complete');
        } catch (e) {
            console.warn('[TUTORIAL] Complete signal failed:', e?.message);
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
        hydrate,
        markSeen,
        skip,
        clearBadge,
        markStepDone,
    };
}
