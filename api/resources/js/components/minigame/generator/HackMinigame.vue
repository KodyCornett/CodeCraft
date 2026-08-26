<template>
    <component
        v-if="activeComponent"
        :is="activeComponent"
        :node="spec.node"
        :resource="spec.resource"
        :player-cpu="playerCpu"
        :player-ram="playerRam"
        :player-os="playerOs"
        :player-firewall="playerFirewall"
        :player-max-uplink="playerMaxUplink"
        :bounty-multiplier="bountyMultiplier"
        :paused="paused"
        @complete="$emit('complete', $event)"
        @failed="$emit('failed', $event)"
        @abort="$emit('abort')"
    />
</template>

<script setup>
/**
 * HackMinigame — the render half of the generator split.
 *
 * generateMinigame() (./generateMinigame.js) decides WHICH template plays
 * and returns a plain spec; this component's only job is resolving that
 * spec's key against the pool registry and mounting the matching
 * component. Every pool entry is expected to accept the same player/rig
 * props GridBreach already does and to emit the same complete/failed/abort
 * events — this component just forwards both directions untouched.
 *
 * Adding a new template to the pool never requires touching this file.
 */
import { computed, defineAsyncComponent } from 'vue';
import { findPoolEntry } from './pool.js';

const props = defineProps({
    // Generation spec from generateMinigame(): { key, node, resource }.
    spec:              { type: Object,  required: true },
    playerCpu:         { type: Number,  default: 3   },
    playerRam:         { type: Number,  default: 2   },
    playerOs:          { type: Number,  default: 2   },
    playerFirewall:    { type: Number,  default: 1   },
    playerMaxUplink:   { type: Number,  default: 3   },
    bountyMultiplier:  { type: Number,  default: 1.0 },
    paused:            { type: Boolean, default: false }, // true while a tour overlay is active
});

defineEmits(['complete', 'failed', 'abort']);

// Re-resolved only when the spec's key changes — not on every re-render.
const activeComponent = computed(() => {
    const entry = findPoolEntry(props.spec?.key);
    if (!entry) {
        console.error(`[HackMinigame] Unknown pool key "${props.spec?.key}" — nothing to render.`);
        return null;
    }
    return defineAsyncComponent({ loader: entry.component });
});
</script>
