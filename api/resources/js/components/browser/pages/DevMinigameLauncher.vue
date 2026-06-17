<template>
    <div class="dev-wrap">

        <div class="dev-header">
            <span class="dev-tag">[ DEV BUILD ]</span>
            <span class="dev-title">MINIGAME LAUNCHER</span>
            <span class="dev-sub">// Remove splice://dev/minigames from SpliceRouter.js before release</span>
        </div>

        <!-- Difficulty selector -->
        <div class="dev-diff-row">
            <span class="dev-diff-label">DIFFICULTY</span>
            <button
                v-for="d in [1, 2, 3]"
                :key="d"
                class="dev-diff-btn"
                :class="{ 'dev-diff-btn--active': selectedDiff === d }"
                @click="selectedDiff = d"
            >D{{ d }}</button>
            <span class="dev-diff-label" style="margin-left:16px">LOCKS REQ</span>
            <button
                v-for="n in [3, 5, 7, 10]"
                :key="n"
                class="dev-diff-btn"
                :class="{ 'dev-diff-btn--active': selectedLocks === n }"
                @click="selectedLocks = n"
            >{{ n }}</button>
        </div>

        <!-- Game list -->
        <div class="dev-games">
            <div
                v-for="game in GAMES"
                :key="game.type"
                class="dev-game"
                :class="{ 'dev-game--stub': !game.built }"
            >
                <div class="dev-game-header">
                    <span class="dev-game-type">{{ game.type.toUpperCase() }}</span>
                    <span class="dev-game-status" :class="game.built ? 'dev-status--built' : 'dev-status--stub'">
                        {{ game.built ? '[ BUILT ]' : '[ STUB ]' }}
                    </span>
                </div>
                <div class="dev-game-quest">{{ game.quest }}</div>
                <div class="dev-game-brief">{{ game.brief }}</div>
                <button
                    class="dev-launch-btn"
                    :disabled="!game.built"
                    @click="onLaunch(game)"
                >{{ game.built ? '[ LAUNCH ]' : '[ NOT YET BUILT ]' }}</button>
            </div>
        </div>

    </div>
</template>

<script setup>
import { ref } from 'vue';
import { useQuestMinigame } from '@/composables/useQuestMinigame.js';

const { launch } = useQuestMinigame();

const selectedDiff  = ref(1);
const selectedLocks = ref(5);

const GAMES = [
    {
        type:  'disconnect_layer',
        built: true,
        quest: 'Quest 1 — Knuckle / Browne\'s Addition',
        brief: 'Select a path across the grid whose values sum to the governor target. Three attempts.',
        skin: {
            primaryBarLabel: 'TRACE',
            stabilityLabel:  'SYSTEM HEAT',
            objectiveText:   'Sever the governor chain before it reroutes.',
            fileName:        'BA-V14.sys',
            hideBars:        true,
            timeLimit:       90,
        },
    },
    {
        type:  'flush_buffer',
        built: true,
        quest: 'Quest 2 — Veil / Downtown',
        brief: 'Capture and forensically analyse incoming signals. Flush anomalous packets before the buffer overflows.',
        skin: {
            primaryBarLabel: 'ICE TRACE',
            stabilityLabel:  'STABILITY',
            objectiveText:   'Identify and flush ghost signals. Stability collapses if the buffer floods.',
            fileName:        'DT-V8.sys',
            hideBars:        false,
            timeLimit:       180,
        },
    },
    {
        type:  'toxic_soak',
        built: true,
        quest: 'Quest 3 — Float / Spokane Valley',
        brief: 'Vent pressure vectors before they overflow. Hold until absorption threshold is reached.',
        skin: {
            primaryBarLabel: 'ABSORPTION',
            stabilityLabel:  'OVERLOAD',
            objectiveText:   'Hold position. Anchor until saturation threshold.',
            fileName:        'SV-V9.sys',
            hideBars:        false,
            timeLimit:       30,
        },
    },
    {
        type:  'archive_extraction',
        built: true,
        quest: 'Quest 4 — Axiom / University District',
        brief: 'Suppress real ICE probes. Ignore ghost signals. Let the archive pull itself out.',
        skin: {
            primaryBarLabel: 'TRACE',
            stabilityLabel:  'STABILITY',
            objectiveText:   'Extract the packet. Avoid triggering live ICE.',
            fileName:        'UD-V17.sys',
            hideBars:        false,
            timeLimit:       55,
        },
    },
    {
        type:  'calibration_tether',
        built: false,
        quest: 'Quest 5 — Patch / North Spokane',
        brief: 'Move volatile sub-routines to the drop-box before they drain your system.',
        skin: {
            primaryBarLabel: 'PAYLOAD',
            stabilityLabel:  'INTEGRITY',
            objectiveText:   'Deliver the sub-routines. Do not let the chain cascade.',
            fileName:        'NS-V13.sys',
            hideBars:        false,
            timeLimit:       30,
        },
    },
];

function onLaunch(game) {
    const skin = {
        gameType:          game.type,
        fileName:          game.skin.fileName,
        nodeCanvasId:      null,
        objectiveText:     game.skin.objectiveText,
        successText:       'Objective complete. Disconnecting.',
        failText:          'Connection lost.',
        primaryBarLabel:   game.skin.primaryBarLabel,
        stabilityLabel:    game.skin.stabilityLabel,
        timeLimit:         game.skin.timeLimit,
        hideBars:          game.skin.hideBars,
        dealsDamageOnFail: false,
        difficulty:        selectedDiff.value,
        locksRequired:     selectedLocks.value,
    };
    launch('dev', game.type, skin);
}
</script>

<style scoped>
.dev-wrap {
    padding: 20px;
    font-family: 'JetBrains Mono', monospace;
    display: flex;
    flex-direction: column;
    gap: 16px;
    min-height: 100%;
    box-sizing: border-box;
}

/* ── Header ──────────────────────────────────────────────────────────────── */

.dev-header {
    display: flex;
    flex-direction: column;
    gap: 4px;
    padding-bottom: 12px;
    border-bottom: 1px solid rgba(0,255,100,0.12);
}

.dev-tag   { font-size: 9px;  color: #ff3333; letter-spacing: 0.2em; }
.dev-title { font-size: 14px; color: #00ff9d; letter-spacing: 0.15em; font-weight: 700; }
.dev-sub   { font-size: 8px;  color: rgba(0,255,100,0.2); letter-spacing: 0.1em; }

/* ── Difficulty row ──────────────────────────────────────────────────────── */

.dev-diff-row {
    display: flex;
    align-items: center;
    gap: 10px;
}

.dev-diff-label {
    font-size: 9px;
    color: rgba(0,255,100,0.3);
    letter-spacing: 0.18em;
    margin-right: 4px;
}

.dev-diff-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.15em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.2);
    color: rgba(0,255,100,0.4);
    padding: 5px 14px;
    cursor: pointer;
    transition: all 0.1s;
}

.dev-diff-btn:hover {
    border-color: rgba(0,255,100,0.5);
    color: rgba(0,255,100,0.7);
}

.dev-diff-btn--active {
    border-color: #00ff9d;
    color: #00ff9d;
    background: rgba(0,255,100,0.06);
}

/* ── Game cards ──────────────────────────────────────────────────────────── */

.dev-games {
    display: flex;
    flex-direction: column;
    gap: 10px;
}

.dev-game {
    border: 1px solid rgba(0,255,100,0.15);
    padding: 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}

.dev-game--stub {
    border-color: rgba(0,255,100,0.07);
    opacity: 0.5;
}

.dev-game-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.dev-game-type   { font-size: 11px; font-weight: 700; color: #00ff9d; letter-spacing: 0.1em; }

.dev-game-status { font-size: 8px; letter-spacing: 0.15em; }
.dev-status--built { color: rgba(0,255,100,0.5); }
.dev-status--stub  { color: rgba(255,100,100,0.4); }

.dev-game-quest { font-size: 8px; color: rgba(0,255,100,0.3); letter-spacing: 0.1em; }
.dev-game-brief { font-size: 9px; color: rgba(0,255,100,0.55); line-height: 1.5; }

.dev-launch-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.18em;
    background: transparent;
    border: 1px solid rgba(0,255,100,0.3);
    color: rgba(0,255,100,0.6);
    padding: 7px 0;
    cursor: pointer;
    transition: all 0.1s;
    margin-top: 4px;
}

.dev-launch-btn:hover:not(:disabled) {
    background: rgba(0,255,100,0.06);
    border-color: #00ff9d;
    color: #00ff9d;
}

.dev-launch-btn:disabled {
    border-color: rgba(0,255,100,0.08);
    color: rgba(0,255,100,0.2);
    cursor: not-allowed;
}
</style>
