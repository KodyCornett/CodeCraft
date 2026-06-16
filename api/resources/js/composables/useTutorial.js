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

import { ref, computed, nextTick, watch, onMounted, onUnmounted } from 'vue';
import axios from 'axios';

// ── Quest definitions ─────────────────────────────────────────────────────────
//
// Quest order is the unlock order — each quest unlocks when the previous is complete.
// To add a new quest: append or insert an entry here. Step IDs must be globally unique.
// Existing step IDs must NOT be changed — they are persisted in stepsDone on the server.
// Existing quest IDs must NOT be changed — they are persisted in questsRewarded.
//
const QUEST_DEFS = [
    {
        id:       'q1_movement',
        label:    'FIRST STEPS',
        subtitle: 'Learn to move across the grid',
        hint:     'Click any node on the map, then press [JACK IN] in the panel that appears.',
        steps: [
            { id: 'inspect', label: 'Click any node on the map' },
            { id: 'move',    label: 'Press [JACK IN] to move to that node' },
        ],
        reward: 50,
    },
    {
        id:       'q2_rig',
        label:    'KNOW YOUR RIG',
        subtitle: 'Check your Uplink — every move costs one. Know what you have left.',
        hint:     'Click the ⬡ RIG button in the NavBar. From there you can also jump to the full Stat Reference.',
        steps: [
            { id: 'open_rig', label: 'Open your Rig read-out in SPLICE  ( ⬡ RIG )' },
        ],
        reward: 25,
    },
    {
        id:       'q3_stat_guide',
        label:    'READ THE LIMITS',
        subtitle: 'CPU gates which nodes you can crack. Read the table before you breach anything.',
        hint:     'Navigate to splice://sys.local/guide/stats — or find it via SPLICE → RIG → the reference link.',
        steps: [
            { id: 'read_stat_guide', label: 'Visit the Stat Reference  ( splice://sys.local/guide/stats )' },
        ],
        reward: 25,
    },
    {
        id:       'q2_manual',
        label:    'PRE-BREACH PROTOCOL',
        subtitle: 'Know the breach engine before you hit a node',
        hint:     'Navigate to splice://sys.local/guide/gridbreach — or find it via SPLICE home → Grid-Breach Manual.',
        steps: [
            { id: 'read_manual', label: 'Open the Grid-Breach Manual in SPLICE' },
        ],
        reward: 25,
    },
    {
        id:       'q3_hack',
        label:    'FIRST BREACH',
        subtitle: 'Hit a node and take its cache. Check the ICE rating first.',
        hint:     'Close SPLICE, select a node you are standing on, and press [INITIATE HACK].',
        steps: [
            { id: 'hack', label: "Initiate a hack on any node you're standing on" },
        ],
        reward: 100,
    },
    {
        id:       'q5_packet_hijack',
        label:    'KNOW YOUR WEAPONS',
        subtitle: 'Packet Hijack is how PvP works. Run a practice breach before you face a live opponent.',
        hint:     'Open SPLICE → Packet Hijack Manual ( splice://sys.local/guide/packethijack ) and hit [ LAUNCH PRACTICE BREACH ].',
        steps: [
            { id: 'ph_practice', label: 'Complete a practice Packet Hijack breach' },
        ],
        reward: 75,
    },
    {
        id:       'q4_cyberdoc',
        label:    'FIND A SAFE HARBOUR',
        subtitle: 'Pocket creds are lost on a PvP kill. Bank them at a CyberDoc before someone takes them.',
        hint:     'Move to a CyberDoc node (marked on the map), then open SPLICE. The store loads automatically.',
        steps: [
            { id: 'visit_cyberdoc',      label: 'Move to any CyberDoc node on the map' },
            { id: 'open_cyberdoc_store', label: 'Open the CyberDoc store in SPLICE' },
        ],
        reward: 75,
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
        tutorialSeen:       false,
        tutorialSkipped:    false,
        tutorialComplete:   false,   // set once, server-persisted — prevents all re-triggers
        cortexInstallSeen:  false,   // true once the full update + Watcher intrusion sequence has completed
        stepsDone:          {},
        questsRewarded:     [],
        hasBadge:           false,
    };
}

// ── Composable ────────────────────────────────────────────────────────────────
export function useTutorial() {

    const _state        = ref(defaultState());
    const _syncing      = ref(false);   // prevents overlapping PATCH calls
    let   _dirty        = false;        // true if a save was skipped while _syncing — triggers retry
    const justCompleted = ref(false);   // pulses true once after tutorial finishes; Game.vue watches this
    let   _hydrated     = false;        // true once hydrate() has replaced _state with server data

    // ── Hydration (called once on game boot) ──────────────────────────────────
    async function hydrate() {
        log('hydrate() → GET /api/tutorial/state');
        try {
            const { data } = await axios.get('/api/tutorial/state');
            _state.value = { ...defaultState(), ...(data.tutorial_state ?? {}) };
            _hydrated = true;
            log('hydrate() complete — state loaded', {
                stepsDone:       _state.value.stepsDone,
                questsRewarded:  _state.value.questsRewarded,
                tutorialSeen:    _state.value.tutorialSeen,
                tutorialSkipped: _state.value.tutorialSkipped,
                tutorialComplete: _state.value.tutorialComplete,
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
            _hydrated = true;   // unblock markStepDone even on network failure
        }
    }

    // ── Persist to server ─────────────────────────────────────────────────────
    //
    // Guarantees: `await _save()` only resolves AFTER the state is confirmed on
    // the server.  If another PATCH is already in flight, we wait for it to finish
    // and then fire a second one with the latest state (coalesced into one retry).
    //
    // Concurrent callers each wait independently via watch(_syncing).  When the
    // in-flight PATCH completes, ALL waiters wake up; only the first one to run
    // sees _dirty = true and triggers the retry — the rest return immediately.
    async function _save() {
        if (_syncing.value) {
            _dirty = true;
            // Block until the in-flight PATCH finishes, then retry with latest state.
            await new Promise(resolve => {
                const unwatch = watch(_syncing, (v) => { if (!v) { unwatch(); resolve(); } });
            });
            if (!_dirty) return;   // another concurrent caller already fired the retry
            return _save();
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
                // Fallback for non-awaited callers whose watch callbacks fire after
                // the finally block. Prevents any edge-case state from being silently dropped.
                log('_save() — dirty flag in finally, retrying with latest state');
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

    const tutorialSeen      = computed(() => _state.value.tutorialSeen);
    const tutorialSkipped   = computed(() => _state.value.tutorialSkipped);
    const tutorialComplete  = computed(() => _state.value.tutorialComplete);
    const hasBadge          = computed(() => _state.value.hasBadge);

    // true whenever the player has completed or skipped the tutorial but has NOT
    // yet seen the full CORTEX_PATCH update sequence + Watcher intrusion.
    // Game.vue watches [booted, needsCortexInstall] and launches CORTEX_PATCH
    // as soon as both are true — covering both the in-session and reload cases.
    const needsCortexInstall = computed(() =>
        _state.value.tutorialComplete && !_state.value.cortexInstallSeen
    );

    // ── Actions ───────────────────────────────────────────────────────────────

    function markCortexInstall() {
        if (_state.value.cortexInstallSeen) return;
        log('markCortexInstall() — CORTEX_PATCH + Watcher intrusion complete');
        _state.value.cortexInstallSeen = true;
        _save();
    }

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
        if (!_hydrated) {
            // Watchers on currentNodeId and selectedNode fire during initial position
            // restore — before hydrate() has loaded authoritative server state.
            // Ignore all step triggers until we know the real state.
            log(`markStepDone('${stepId}') — hydrate not complete, ignoring boot-time trigger`);
            return;
        }
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
        log(`markStepDone('${stepId}') — checkpoint saved to server ✓`);

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
            log(`_creditReward('${quest.id}') — quest reward saved to server ✓`);

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

    // ── Flush — guarantee state is on the server before session ends ─────────
    // Sets _dirty so _save() always fires (even if state looks clean), then
    // awaits _save() which now blocks until the PATCH is confirmed. Safe to
    // call even when nothing is pending — costs one extra PATCH at most.
    async function flush() {
        log('flush() — ensuring tutorial state is persisted before session ends');
        _dirty = true;
        await _save();
        log('flush() — done ✓');
    }

    // ── beforeunload safety net ───────────────────────────────────────────────
    // Fires a keepalive PATCH on tab close, refresh, or any unload. This is the
    // last-resort guard — flush() should have already handled clean logouts.
    // keepalive: true lets the request complete even after the page is gone.
    function _onBeforeUnload() {
        if (!_hydrated) return;
        const rawToken = document.cookie.match(/XSRF-TOKEN=([^;]+)/)?.[1];
        if (!rawToken) return;
        fetch('/api/tutorial/state', {
            method:    'PATCH',
            keepalive: true,
            headers: {
                'Content-Type':  'application/json',
                'X-XSRF-TOKEN':  decodeURIComponent(rawToken),
            },
            body: JSON.stringify({ tutorial_state: _state.value }),
        }).catch(() => {});   // silent — session may already be gone
    }

    onMounted(() => {
        log('registering beforeunload save beacon');
        window.addEventListener('beforeunload', _onBeforeUnload);
    });
    onUnmounted(() => {
        window.removeEventListener('beforeunload', _onBeforeUnload);
    });

    return {
        quests,
        activeQuest,
        allComplete,
        isTutorialActive,
        tutorialSeen,
        tutorialSkipped,
        tutorialComplete,
        needsCortexInstall,
        hasBadge,
        justCompleted,
        hydrate,
        markSeen,
        skip,
        clearBadge,
        markStepDone,
        markCortexInstall,
        flush,
    };
}
