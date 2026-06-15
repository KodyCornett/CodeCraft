<template>
    <component
        v-if="activeComponent"
        :is="activeComponent"
        :skin="skin"
        @complete="$emit('complete')"
        @fail="$emit('fail')"
    />
    <!-- Fallback while a game type is still being built -->
    <div v-else class="qm-stub">
        <span class="qm-stub-tag">[MINIGAME_NOT_FOUND]</span>
        <span class="qm-stub-type">TYPE: {{ skin.gameType?.toUpperCase() }}</span>
        <span class="qm-stub-msg">// This sequence is not yet active in this build.</span>
        <button class="qm-stub-bypass" @click="$emit('complete')">
            [ BYPASS // DEV BUILD ]
        </button>
    </div>
</template>

<script setup>
import { computed } from 'vue';
import DisconnectLayer from './DisconnectLayer.vue';
import FlushBuffer     from './FlushBuffer.vue';

// Register game components here as they are built.
// Key = minigame_type string from quest_stages.minigame_type
const GAMES = {
    disconnect_layer: DisconnectLayer,
    flush_buffer:     FlushBuffer,
    // toxic_soak:         ToxicSoak,         -- not yet built
    // archive_extraction: ArchiveExtraction, -- not yet built
    // calibration_tether: CalibrationTether, -- not yet built
};

const props = defineProps({
    skin: { type: Object, required: true },
});

defineEmits(['complete', 'fail']);

const activeComponent = computed(() => GAMES[props.skin.gameType] ?? null);
</script>

<style scoped>
.qm-stub {
    position: fixed;
    inset: 0;
    z-index: 9000;
    background: #010a06;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 10px;
    font-family: 'JetBrains Mono', monospace;
}
.qm-stub-tag  { color: #ff3333; font-size: 14px; letter-spacing: 0.15em; }
.qm-stub-type { color: #4a9a7a; font-size: 11px; }
.qm-stub-msg  { color: rgba(0,255,100,0.3); font-size: 10px; }

.qm-stub-bypass {
    margin-top: 24px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.2em;
    background: transparent;
    border: 1px solid rgba(255, 51, 51, 0.4);
    color: rgba(255, 51, 51, 0.7);
    padding: 8px 24px;
    cursor: pointer;
    transition: all 0.15s;
}
.qm-stub-bypass:hover {
    background: rgba(255, 51, 51, 0.08);
    border-color: rgba(255, 51, 51, 0.8);
    color: #ff3333;
}
</style>
