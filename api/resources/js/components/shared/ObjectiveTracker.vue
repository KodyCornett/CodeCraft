<template>
    <Transition name="obj-tracker-fade">
        <div v-if="objective" class="obj-tracker" :class="{ 'obj-tracker--collapsed': collapsed }">

            <!-- Header bar — always visible, click to collapse/expand -->
            <button class="obj-tracker__header" @click="collapsed = !collapsed">
                <span class="obj-tracker__label">ACTIVE MISSION</span>
                <span class="obj-tracker__doc">{{ objective.docName }}</span>
                <span class="obj-tracker__chevron">{{ collapsed ? '▶' : '▼' }}</span>
            </button>

            <!-- Expanded body -->
            <Transition name="obj-body-fade">
                <div v-if="!collapsed" class="obj-tracker__body">
                    <div class="obj-tracker__arc">{{ objective.arcTitle }}</div>

                    <div class="obj-tracker__stage-row">
                        <span class="obj-tracker__stage-num">{{ stagePad }}</span>
                        <span class="obj-tracker__stage-title">{{ objective.stageTitle }}</span>
                    </div>

                    <div v-if="objective.objectiveText" class="obj-tracker__objective">
                        <span class="obj-tracker__obj-prefix">▸</span>
                        {{ objective.objectiveText }}
                    </div>
                </div>
            </Transition>

        </div>
    </Transition>
</template>

<script setup>
import { ref, computed, watch } from 'vue';

const props = defineProps({
    objective: { type: Object, default: null },
});

const collapsed = ref(false);

// Pad stage number to two digits: "01", "02", etc.
const stagePad = computed(() => {
    if (props.objective == null) return '';
    return `[${String(props.objective.stageNumber).padStart(2, '0')}]`;
});

// Auto-expand whenever the active stage changes so the player sees the update.
watch(() => props.objective?.stageId, (next, prev) => {
    if (next && next !== prev) {
        collapsed.value = false;
    }
});
</script>

<style scoped>
/* ── Positioning ──────────────────────────────────────────────────────────── */
.obj-tracker {
    position: absolute;
    top: 12px;
    left: 12px;
    z-index: 30;
    width: 260px;
    font-family: 'JetBrains Mono', monospace;
    pointer-events: auto;
    user-select: none;
}

/* ── Shell ────────────────────────────────────────────────────────────────── */
.obj-tracker {
    background: rgba(4, 6, 14, 0.88);
    border: 1px solid rgba(0, 255, 200, 0.25);
    box-shadow: 0 0 20px rgba(0, 255, 200, 0.06), inset 0 0 16px rgba(0, 0, 0, 0.4);
}

/* ── Header ───────────────────────────────────────────────────────────────── */
.obj-tracker__header {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
    padding: 7px 10px;
    background: rgba(0, 255, 200, 0.05);
    border: none;
    border-bottom: 1px solid rgba(0, 255, 200, 0.15);
    cursor: pointer;
    text-align: left;
}

.obj-tracker--collapsed .obj-tracker__header {
    border-bottom: none;
}

.obj-tracker__label {
    font-size: 9px;
    letter-spacing: 0.2em;
    color: #00FFC8;
    text-shadow: 0 0 8px rgba(0, 255, 200, 0.6);
    flex-shrink: 0;
}

.obj-tracker__doc {
    font-size: 9px;
    letter-spacing: 0.1em;
    color: rgba(0, 255, 200, 0.45);
    flex: 1;
    text-align: right;
    overflow: hidden;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.obj-tracker__chevron {
    font-size: 8px;
    color: rgba(0, 255, 200, 0.5);
    flex-shrink: 0;
    margin-left: 4px;
}

/* ── Body ─────────────────────────────────────────────────────────────────── */
.obj-tracker__body {
    padding: 10px 12px 12px;
    display: flex;
    flex-direction: column;
    gap: 6px;
    overflow: hidden;
}

.obj-tracker__arc {
    font-size: 10px;
    letter-spacing: 0.14em;
    color: rgba(255, 255, 255, 0.7);
    line-height: 1.3;
}

.obj-tracker__stage-row {
    display: flex;
    align-items: baseline;
    gap: 6px;
}

.obj-tracker__stage-num {
    font-size: 9px;
    color: rgba(0, 255, 200, 0.5);
    flex-shrink: 0;
    letter-spacing: 0.05em;
}

.obj-tracker__stage-title {
    font-size: 11px;
    color: #00FFC8;
    letter-spacing: 0.1em;
    line-height: 1.3;
    text-shadow: 0 0 10px rgba(0, 255, 200, 0.4);
}

.obj-tracker__objective {
    display: flex;
    gap: 6px;
    font-size: 10px;
    color: rgba(255, 255, 255, 0.55);
    letter-spacing: 0.06em;
    line-height: 1.5;
    padding-top: 2px;
    border-top: 1px solid rgba(0, 255, 200, 0.08);
}

.obj-tracker__obj-prefix {
    color: rgba(0, 255, 200, 0.6);
    flex-shrink: 0;
    line-height: 1.5;
}

/* ── Transitions ──────────────────────────────────────────────────────────── */
.obj-tracker-fade-enter-active { transition: opacity 0.4s ease, transform 0.4s ease; }
.obj-tracker-fade-leave-active { transition: opacity 0.3s ease, transform 0.3s ease; }
.obj-tracker-fade-enter-from  { opacity: 0; transform: translateY(-6px); }
.obj-tracker-fade-leave-to    { opacity: 0; transform: translateY(-6px); }

.obj-body-fade-enter-active { transition: opacity 0.25s ease, max-height 0.25s ease; max-height: 200px; }
.obj-body-fade-leave-active { transition: opacity 0.2s ease, max-height 0.2s ease; max-height: 200px; }
.obj-body-fade-enter-from   { opacity: 0; max-height: 0; }
.obj-body-fade-leave-to     { opacity: 0; max-height: 0; }
</style>
