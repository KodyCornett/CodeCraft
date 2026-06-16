<template>
    <div class="ddp-root">
        <!-- No active dialogue for this doc -->
        <div v-if="!activeStage" class="ddp-no-dialogue">
            <div class="ddp-no-dialogue-icon">◈</div>
            <div class="ddp-no-dialogue-title">NO ACTIVE TRANSMISSION</div>
            <div class="ddp-no-dialogue-sub">{{ config.npcHandle }} has nothing to say right now.</div>
            <button class="ddp-back-btn" @click="spliceNavigate(SPLICE.TERMINAL)">
                [ RETURN TO TERMINAL ]
            </button>
        </div>

        <!-- Active dialogue — render through DialoguePage -->
        <DialoguePage
            v-else-if="activeStage"
            ref="dialoguePageRef"
            :entries="activeStage.dialogue"
            :npc-handle="config.npcHandle"
            :npc-subtitle="config.npcSubtitle"
            :location-label="config.locationLabel"
            :accent-color="config.accentColor"
            :ambient-src="config.ambientSrc"
            @reached-end="onDialogueReachedEnd"
            @complete="onDialogueComplete"
        />
    </div>
</template>

<script setup>
import { ref, computed, inject, watch, onMounted, onUnmounted } from 'vue';
import DialoguePage from './DialoguePage.vue';
import { SPLICE } from '../SpliceRouter.js';
import { useAudio } from '../../../composables/useAudio.js';

const props = defineProps({
    url: { type: String, default: '' },
});

// ── Doc config table ──────────────────────────────────────────────────────────
const DOC_CONFIG = {
    knuckle: {
        npcHandle:     'KNUCKLE',
        npcSubtitle:   'STREET DOC // BROWNE\'S ADDITION',
        locationLabel: 'BA-HUB // KNUCKLE\'S MED-WAGON',
        accentColor:   '#FF6B35',
        district:      "Browne's Addition",
        ambientSrc:    'k_node_BG.mp3',
    },
    patch: {
        npcHandle:     'PATCH',
        npcSubtitle:   'FIELD MEDIC // NORTH SPOKANE',
        locationLabel: 'NS-HUB // PATCH\'S CLINIC',
        accentColor:   '#00FFC8',
        district:      'North Spokane',
        ambientSrc:    'p_node_BG.mp3',
    },
    veil: {
        npcHandle:     'VEIL',
        npcSubtitle:   'IMPLANT ARTIST // DOWNTOWN',
        locationLabel: 'DT-HUB // VEIL\'S PARLOUR',
        accentColor:   '#B06FFF',
        district:      'Downtown',
        ambientSrc:    'v_node_BG.mp3',
    },
    axiom: {
        npcHandle:     'AXIOM',
        npcSubtitle:   'SYSTEMS TECH // UNIVERSITY DISTRICT',
        locationLabel: 'UD-HUB // AXIOM SYSTEMS',
        accentColor:   '#FFD700',
        district:      'University District',
        ambientSrc:    null,
    },
    float: {
        npcHandle:     'FLOAT',
        npcSubtitle:   'REPAIR SPECIALIST // SPOKANE VALLEY',
        locationLabel: 'SV-HUB // FLOAT\'S REPAIR BAY',
        accentColor:   '#00BFFF',
        district:      'Spokane Valley',
        ambientSrc:    'f_node_BG.mp3',
    },
};

// ── Parse doc handle from URL (splice://dialogue/knuckle → 'knuckle') ─────────
const docHandle = computed(() => {
    const match = props.url.match(/splice:\/\/dialogue\/(\w+)/);
    return match?.[1] ?? '';
});

const config = computed(() => DOC_CONFIG[docHandle.value] ?? {
    npcHandle: 'UNKNOWN',
    npcSubtitle: '',
    locationLabel: '',
    accentColor: '#00FFC8',
    district: '',
});

// ── Quest log injected from Game.vue ──────────────────────────────────────────
const questLog = inject('questLog', null);

// Find the active stage with dialogue for this doc
const activeStage = computed(() => {
    if (!questLog || !docHandle.value) return null;
    const docs = questLog.docs?.value ?? questLog.docs ?? [];
    const doc  = docs.find(d => d.district === config.value.district);
    if (!doc) return null;

    for (const arc of doc.arcs ?? []) {
        for (const stage of arc.stages ?? []) {
            if (stage.status === 'active' && stage.dialogue?.length > 0) {
                return stage;
            }
        }
    }
    return null;
});

// ── Audio ─────────────────────────────────────────────────────────────────────
const { fadeOutForDialogue, fadeInAfterDialogue } = useAudio();

onMounted(() => fadeOutForDialogue());
onUnmounted(() => fadeInAfterDialogue());

// ── Navigation + callbacks ────────────────────────────────────────────────────
const spliceNavigate          = inject('spliceNavigate',          () => {});
const onDocDialogueComplete   = inject('onDocDialogueComplete',   () => {});

// ── DialoguePage ref — used to stop audio and read completion state ───────────
const dialoguePageRef = ref(null);

// ── Stage completion guard — prevents double-completing if the player clicks
//    [CLOSE TRANSMISSION] and the browser close signal both fire. ──────────────
const stageCompleted = ref(false);

// ── Core stage-complete API call — fire-and-forget, no loading state ───────────
async function _doCompleteStage() {
    if (!activeStage.value || !questLog) return;
    try {
        const completeStage = questLog.completeStage ?? questLog.complete;
        const stageResult   = completeStage ? await completeStage(activeStage.value.id) : null;
        // Arm the Watcher transition when the arc just ended — detected by the server
        // returning next_stage_id: null (no further stage exists in this arc).
        if (!stageResult?.next_stage_id) {
            onDocDialogueComplete(docHandle.value);
        }
    } catch (e) {
        console.warn('[DocDialogue] completeStage failed:', e.message ?? e);
    }
}

// ── Stage complete — fires as soon as the last entry is revealed (button appears).
//    The [CLOSE TRANSMISSION] button and clicking outside are now pure close
//    actions — the stage has already been marked done server-side. ───────────────
function onDialogueReachedEnd() {
    if (stageCompleted.value) return;
    stageCompleted.value = true;
    _doCompleteStage(); // fire-and-forget — player is still reading the final line
}

// ── [CLOSE TRANSMISSION] button / @complete — just navigate away ───────────────
function onDialogueComplete() {
    spliceNavigate(SPLICE.TERMINAL);
}

// ── Browser-close signal — stop audio immediately before the leave transition ──
const browserIsClosing = inject('browserIsClosing', ref(false));
watch(browserIsClosing, (closing) => {
    if (!closing) return;
    dialoguePageRef.value?.stop();
}, { flush: 'sync' });
</script>

<style scoped>
.ddp-root {
    width: 100%;
    height: 100%;
    background: rgba(4, 2, 10, 0.99);
    display: flex;
    flex-direction: column;
    font-family: 'JetBrains Mono', monospace;
}

/* ── No dialogue ──────────────────────────────────────────────────────────── */
.ddp-no-dialogue {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 12px;
    padding: 40px;
    text-align: center;
}

.ddp-no-dialogue-icon {
    font-size: 28px;
    color: rgba(0, 255, 200, 0.25);
}

.ddp-no-dialogue-title {
    font-size: 12px;
    letter-spacing: 0.2em;
    color: rgba(255, 255, 255, 0.5);
}

.ddp-no-dialogue-sub {
    font-size: 10px;
    letter-spacing: 0.1em;
    color: rgba(255, 255, 255, 0.25);
}

.ddp-back-btn {
    margin-top: 20px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.2em;
    background: transparent;
    border: 1px solid rgba(0, 255, 200, 0.25);
    color: rgba(0, 255, 200, 0.6);
    padding: 8px 20px;
    cursor: pointer;
    transition: all 0.15s;
}

.ddp-back-btn:hover {
    background: rgba(0, 255, 200, 0.06);
    border-color: rgba(0, 255, 200, 0.5);
    color: #00FFC8;
}
</style>
