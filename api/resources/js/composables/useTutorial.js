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

import { ref, computed, nextTick } from 'vue';
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

// ── Logging helper ────────────────────────────────────────────────────────────
function log(msg, data) {
    if (data !== undefined) {
        console.log(`%c[TUTORIAL] ${msg}`, 'color:#00FFC8;font-weight:bold', data);
    } else {
        console.log(`%c[TUTORIAL] ${msg}`, 'color:#00FFC8;font-weight:bold');
    }
}
function warn(msg, data) {
    if (data !== undefined) {
        console.warn(`[TUTORIAL] ${msg}`, data);
    } else {
        console.warn(`[TUTORIAL] ${msg}`);
    }
}

function defaultState() {
    return {
        tutorialSeen:     false,
        tutorialSkipped:  false,
        tutorialComplete: false,   // set once, server-persisted — prevents all re-triggers
        stepsDone:        {},
        questsRewarded:   [],
        hasBadge:         false,
    };
}

// ── Composable ────────────────────────────────────────────────────────────────
export function useTutorial() {

    const _state        = ref(defaultState());
    const _syncing      = ref(false);   // prevents overlapping PATCH calls
    let   _dirty        = false;        // true if a save was skipped while _syncing — triggers retry
    const justCompleted = ref(false);   // pulses true once after tutorial finishes; Game.vue watches this

    // ── Hydration (called once on game boot) ──────────────────────────────────
    async function hydrate() {
        log('hydrate() → GET /api/tutorial/state');
        try {
            const { data } = await axios.get('/api/tutorial/state');
            _state.value = { ...defaultState(), ...(data.tutorial_state ?? {}) };
            log('hydrate() complete — state loaded', {
                stepsDone:      _state.value.stepsDone,
                questsRewarded: _state.value.questsRewarded,
                tutorialSeen:   _state.value.tutorialSeen,
                tutorialSkipped: _state.value.tutorialSkipped,
            });

            // If the tutorial was already completed (authoritative flag), or all
            // quests are rewarded, fire the complete endpoint silently to guarantee
            // the entry arc is unlocked. tutorialComplete is the primary gate —
            // questsRewarded is a fallback for sessions saved before this flag existed.
            const alreadyDone = _state.value.tutorialComplete
                || QUEST_DEFS.every(q => _state.value.questsRewarded.includes(q.id));
            if (alreadyDone) {
                // Make sure the flag is written if this is a legacy save (pre-flag).
                if (!_state.value.tutorialComplete) {
                    _state.value.tutorialComplete = true;
                    await _save();
                }
                log('hydrate() — tutorial already complete, re-firing complete endpoint to guarantee arc unlock');
                await _completeTutorial({ silent: true });
            }
        } catch (e) {
            warn('hydrate() failed to load state from server:', e?.message);
        }
    }

    // ── Persist to server ─────────────────────────────────────────────────────
    async function _save() {
        if (_syncing.value) {
            // Mark dirty so the in-flight save triggers a retry with the latest state.
            // Without this, rapid calls (markSeen → markStepDone) silently drop saves.
            _dirty = true;
            return;
        }
        _dirty = false;
        _syncing.value = true;
        log('_save() → PATCH /api/tutorial/state', _state.value);
        try {
            const { data } = await axios.patch('/api/tutorial/state', {
                tutorial_state: _state.value,
            });
            log('_save() confirmed — server echoed back:', data.tutorial_state);
        } catch (e) {
            warn('_save() failed to persist state:', e?.message);
        } finally {
            _syncing.value = false;
            if (_dirty) {
                log('_save() — dirty flag set, retrying with latest state');
                await _save();
            }
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
        !_state.value.tutorialSkipped
        && !_state.value.tutorialComplete
        && !allComplete.value
    );

    const tutorialSeen     = computed(() => _state.value.tutorialSeen);
    const tutorialSkipped  = computed(() => _state.value.tutorialSkipped);
    const tutorialComplete = computed(() => _state.value.tutorialComplete);
    const hasBadge         = computed(() => _state.value.hasBadge);

    // ── Actions ───────────────────────────────────────────────────────────────

    function markSeen() {
        log('markSeen()');
        _state.value.tutorialSeen = true;
        _save();
    }

    async function skip() {
        log('skip()');
        _state.value.tutorialSeen    = true;
        _state.value.tutorialSkipped = true;
        await _save();
        // Fire tutorial/complete so the entry arc (Knuckle) is initialised
        // even when the player skips. Without this, questDocs stays stale
        // and the dialogue button never appears at BA-hub.
        await _completeTutorial();
    }

    function clearBadge() {
        if (!_state.value.hasBadge) return;
        log('clearBadge()');
        _state.value.hasBadge = false;
        _save();
    }

    async function markStepDone(stepId) {
        if (_state.value.tutorialSkipped || _state.value.tutorialComplete) {
            log(`markStepDone('${stepId}') — skipped (tutorial skipped/complete)`);
            return;
        }
        if (_state.value.stepsDone[stepId]) {
            log(`markStepDone('${stepId}') — already done, no-op`);
            return;
        }

        const active = activeQuest.value;
        if (!active) {
            log(`markStepDone('${stepId}') — no active quest, no-op`);
            return;
        }
        if (!active.steps.find(s => s.id === stepId)) {
            log(`markStepDone('${stepId}') — step not in active quest '${active.id}', no-op`);
            return;
        }

        log(`markStepDone('${stepId}') — marking done in quest '${active.id}'`);
        _state.value.stepsDone[stepId] = true;
        _state.value.hasBadge          = true;
        await _save();

        const questNowDone = active.steps.every(s => _state.value.stepsDone[s.id]);
        log(`markStepDone('${stepId}') — quest '${active.id}' complete: ${questNowDone}`);

        if (questNowDone) {
            await _creditReward(active);
        }
    }

    async function _creditReward(quest) {
        if (_state.value.questsRewarded.includes(quest.id)) {
            log(`_creditReward('${quest.id}') — already rewarded, skipping`);
            return;
        }

        log(`_creditReward('${quest.id}') → POST /api/tutorial/reward`, { amount: quest.reward });
        try {
            const { data } = await axios.post('/api/tutorial/reward', {
                quest_id: quest.id,
                amount:   quest.reward,
            });
            log(`_creditReward('${quest.id}') — reward credited, wallet_creds now: ${data.wallet_creds}`);

            _state.value.questsRewarded.push(quest.id);
            await _save();

            const allRewarded = QUEST_DEFS.every(q => _state.value.questsRewarded.includes(q.id));
            log(`_creditReward('${quest.id}') — all quests rewarded: ${allRewarded}`, {
                rewarded: _state.value.questsRewarded,
            });

            if (allRewarded) {
                await _completeTutorial();
            }
        } catch (e) {
            warn(`_creditReward('${quest.id}') failed:`, e?.message);
        }
    }

    // silent=true → called from hydrate on reload; skips justCompleted pulse
    async function _completeTutorial({ silent = false } = {}) {
        log(`_completeTutorial() → POST /api/tutorial/complete (silent=${silent})`);

        // Save the authoritative completion flag BEFORE doing anything else.
        // This must be persisted first so that even if the pulse or arc-unlock
        // call fails, the flag is on the server and prevents replay on reload.
        if (!silent && !_state.value.tutorialComplete) {
            _state.value.tutorialComplete = true;
            await _save();
        }

        try {
            await axios.post('/api/tutorial/complete');
            log('_completeTutorial() — server confirmed arc unlock ✓');

            if (!silent) {
                log('_completeTutorial() — pulsing justCompleted to trigger Game.vue watcher');
                justCompleted.value = true;
                await nextTick();
                justCompleted.value = false;
            } else {
                log('_completeTutorial() — silent mode, skipping justCompleted pulse');
            }
        } catch (e) {
            const status = e?.response?.status;
            warn(`_completeTutorial() failed (HTTP ${status ?? 'unknown'}):`, e?.message);

            // Retry once after a short delay if throttled
            if (status === 429) {
                warn('_completeTutorial() — 429 throttled, retrying in 3 seconds...');
                await new Promise(resolve => setTimeout(resolve, 3000));
                try {
                    await axios.post('/api/tutorial/complete');
                    log('_completeTutorial() retry — arc unlock confirmed ✓');
                    if (!silent) {
                        justCompleted.value = true;
                        await nextTick();
                        justCompleted.value = false;
                    }
                } catch (retryErr) {
                    warn('_completeTutorial() retry also failed:', retryErr?.message);
                }
            }
        }
    }

    return {
        quests,
        activeQuest,
        allComplete,
        isTutorialActive,
        tutorialSeen,
        tutorialSkipped,
        tutorialComplete,
        hasBadge,
        justCompleted,
        hydrate,
        markSeen,
        skip,
        clearBadge,
        markStepDone,
    };
}
