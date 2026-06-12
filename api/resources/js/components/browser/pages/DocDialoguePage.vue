<template>
    <div class="ddp-root">
        <!-- Loading state -->
        <div v-if="loading" class="ddp-loading">
            <span class="ddp-loading-cursor">█</span>
            <span>RETRIEVING TRANSMISSION...</span>
        </div>

        <!-- No active dialogue for this doc -->
        <div v-else-if="!activeStage" class="ddp-no-dialogue">
            <div class="ddp-no-dialogue-icon">◈</div>
            <div class="ddp-no-dialogue-title">NO ACTIVE TRANSMISSION</div>
            <div class="ddp-no-dialogue-sub">{{ config.npcHandle }} has nothing to say right now.</div>
            <button class="ddp-back-btn" @click="spliceNavigate(SPLICE.TERMINAL)">
                [ RETURN TO TERMINAL ]
            </button>
        </div>

        <!-- Active dialogue — render through DialoguePage -->
        <DialoguePage
            v-else
            :entries="activeStage.dialogue"
            :npc-handle="config.npcHandle"
            :npc-subtitle="config.npcSubtitle"
            :location-label="config.locationLabel"
            :accent-color="config.accentColor"
            @complete="onDialogueComplete"
        />
    </div>
</template>

<script setup>
import { ref, computed, inject, onMounted } from 'vue';
import DialoguePage from './DialoguePage.vue';
import { SPLICE } from '../SpliceRouter.js';

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
    },
    patch: {
        npcHandle:     'PATCH',
        npcSubtitle:   'FIELD MEDIC // NORTH SPOKANE',
        locationLabel: 'NS-HUB // PATCH\'S CLINIC',
        accentColor:   '#00FFC8',
        district:      'North Spokane',
    },
    veil: {
        npcHandle:     'VEIL',
        npcSubtitle:   'IMPLANT ARTIST // DOWNTOWN',
        locationLabel: 'DT-HUB // VEIL\'S PARLOUR',
        accentColor:   '#B06FFF',
        district:      'Downtown',
    },
    axiom: {
        npcHandle:     'AXIOM',
        npcSubtitle:   'SYSTEMS TECH // UNIVERSITY DISTRICT',
        locationLabel: 'UD-HUB // AXIOM SYSTEMS',
        accentColor:   '#FFD700',
        district:      'University District',
    },
    float: {
        npcHandle:     'FLOAT',
        npcSubtitle:   'REPAIR SPECIALIST // SPOKANE VALLEY',
        locationLabel: 'SV-HUB // FLOAT\'S REPAIR BAY',
        accentColor:   '#00BFFF',
        district:      'Spokane Valley',
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

const loading = ref(false);

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

// ── Navigation ────────────────────────────────────────────────────────────────
const spliceNavigate = inject('spliceNavigate', () => {});

// ── On dialogue complete — mark stage done, return to terminal ─────────────────
async function onDialogueComplete() {
    if (!activeStage.value || !questLog) {
        spliceNavigate(SPLICE.TERMINAL);
        return;
    }

    loading.value = true;
    try {
        const completeStage = questLog.completeStage ?? questLog.complete;
        if (completeStage) {
            await completeStage(activeStage.value.id);
        }
    } catch (e) {
        console.warn('[DocDialogue] completeStage failed:', e.message ?? e);
    } finally {
        loading.value = false;
    }

    spliceNavigate(SPLICE.TERMINAL);
}
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

/* ── Loading ──────────────────────────────────────────────────────────────── */
.ddp-loading {
    flex: 1;
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 12px;
    font-size: 10px;
    letter-spacing: 0.2em;
    color: rgba(0, 255, 200, 0.5);
}

.ddp-loading-cursor {
    animation: ddp-blink 0.55s steps(1) infinite;
}

@keyframes ddp-blink {
    0%, 49%  { opacity: 1; }
    50%, 100% { opacity: 0; }
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
