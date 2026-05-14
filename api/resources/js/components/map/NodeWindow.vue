<template>
    <div class="node-window" :style="windowStyle">
        <!-- Header -->
        <div class="nw-header">
            <span class="nw-title">[{{ (node.label ?? node.id)?.toUpperCase() }}]</span>
            <button class="nw-close" @click="$emit('close')">✕</button>
        </div>

        <!-- Meta row -->
        <div class="nw-meta">
            <span class="nw-tier">TIER {{ node.tier ?? '?' }}</span>
            <span class="nw-sep">//</span>
            <span class="nw-state" :class="`nw-state--${node.state}`">{{ stateLabel }}</span>
        </div>

        <!-- Resources -->
        <div class="nw-resources">
            <div class="nw-resource">
                <span class="nw-rkey">CREDS</span>
                <span :class="node.credDepleted ? 'nw-depleted' : 'nw-available'">
                    {{ node.credDepleted ? 'DEPLETED' : 'AVAILABLE' }}
                </span>
            </div>
            <div class="nw-resource">
                <span class="nw-rkey">FUEL</span>
                <span :class="node.movementDepleted ? 'nw-depleted' : 'nw-available'">
                    {{ node.movementDepleted ? 'DEPLETED' : 'AVAILABLE' }}
                </span>
            </div>
        </div>

        <div class="nw-divider" />

        <!-- Remote node actions -->
        <template v-if="!isOnNode">
            <button
                class="nw-btn"
                :class="canMove ? 'nw-btn--primary' : 'nw-btn--disabled'"
                :disabled="!canMove || moving"
                @click="$emit('move')"
            >{{ moving ? '// MOVING...' : '[MOVE]' }}</button>
            <div v-if="!canMove" class="nw-hint">// NOT ADJACENT — FOLLOW CONNECTION LINKS</div>
            <div v-else-if="player.cpuCycles < cpuCost" class="nw-hint nw-hint--warn">
                // INSUFFICIENT CPU (NEED {{ cpuCost }})
            </div>
        </template>

        <!-- Own-node actions -->
        <template v-else>
            <button
                class="nw-btn nw-btn--hack"
                :class="canHackCreds ? 'nw-btn--secondary' : 'nw-btn--disabled'"
                :disabled="!canHackCreds"
                @click="$emit('hack', 'creds')"
            >
                [HACK_CREDS]
                <span class="nw-cred-val">{{ credValueDisplay }} ₡</span>
            </button>

            <button
                class="nw-btn nw-btn--hack"
                :class="canHackMovement ? 'nw-btn--secondary' : 'nw-btn--disabled'"
                :disabled="!canHackMovement"
                @click="$emit('hack', 'movement')"
            >
                [HACK_MOVEMENT]
                <span class="nw-cred-val">+{{ movementPts }} PTS</span>
            </button>

            <div v-if="isScorched" class="nw-hint nw-hint--warn">// NODE SCORCHED — RESOURCES OFFLINE</div>

            <div class="nw-divider" />
            <button class="nw-btn nw-btn--leave" @click="$emit('close')">[LEAVE]</button>
        </template>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    node:       { type: Object,  required: true },
    isOnNode:   { type: Boolean, default: false },
    isAdjacent: { type: Boolean, default: false },
    player:     { type: Object,  required: true },
    position:   { type: Object,  default: () => ({ x: 40, y: 40 }) }, // { x, y } relative to map-stage
    moving:     { type: Boolean, default: false },
});

defineEmits(['close', 'move', 'hack']);

// ── Position ──────────────────────────────────────────────────────────────────
const windowStyle = computed(() => ({
    left: props.position.x + 'px',
    top:  props.position.y + 'px',
}));

// ── Derived state ─────────────────────────────────────────────────────────────
const isScorched = computed(() => props.node.state === 'scorched');

const stateLabel = computed(() => {
    const map = {
        active:     'ACTIVE',
        hacked:     'COMPROMISED',
        scorched:   'SCORCHED',
        street_doc: 'STREET DOC',
    };
    return map[props.node.state] ?? props.node.state?.toUpperCase() ?? 'UNKNOWN';
});

const cpuCost = computed(() => props.player.isLimping ? 2 : 1);

const canMove = computed(() =>
    props.isAdjacent && props.player.cpuCycles >= cpuCost.value,
);

const canHackCreds = computed(() =>
    !props.node.credDepleted && !isScorched.value,
);

const canHackMovement = computed(() =>
    !props.node.movementDepleted && !isScorched.value,
);

// ── Cred value (calculated from last hacked timestamp) ────────────────────────
const credValueDisplay = computed(() => {
    const base = props.node.credValueBase ?? 100;
    if (!props.node.credLastHackedAt) return base;
    const mins = (Date.now() - new Date(props.node.credLastHackedAt).getTime()) / 60_000;
    return base + Math.floor(mins / 2.5) * 25;
});

const movementPts = computed(() => Math.max(3, (props.node.tier ?? 1) + 2));
</script>

<style scoped>
.node-window {
    position: absolute;
    z-index: 25;
    width: 240px;
    background: rgba(4, 4, 4, 0.96);
    border: 1px solid rgba(0, 255, 255, 0.35);
    box-shadow: 0 0 16px rgba(0, 255, 255, 0.08), inset 0 0 32px rgba(0, 0, 0, 0.4);
    font-family: 'JetBrains Mono', monospace;
    animation: nw-appear 0.14s ease-out forwards;
    pointer-events: all;
}

@keyframes nw-appear {
    from { opacity: 0; transform: translateY(-6px) scale(0.97); }
    to   { opacity: 1; transform: translateY(0)    scale(1);    }
}

/* ── Header ──────────────────────────────────────────────────────────────────── */
.nw-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 8px 10px 6px;
    border-bottom: 1px solid rgba(0, 255, 255, 0.12);
}

.nw-title {
    font-size: 10px;
    color: #00FFFF;
    letter-spacing: 0.06em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    max-width: 180px;
}

.nw-close {
    background: none;
    border: none;
    color: rgba(0, 255, 255, 0.4);
    font-size: 10px;
    cursor: pointer;
    padding: 0 2px;
    line-height: 1;
    font-family: inherit;
    flex-shrink: 0;
}
.nw-close:hover { color: #FF3333; }

/* ── Meta ────────────────────────────────────────────────────────────────────── */
.nw-meta {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 5px 10px;
    font-size: 9px;
    letter-spacing: 0.05em;
}

.nw-tier { color: rgba(0, 255, 255, 0.5); }
.nw-sep  { color: rgba(0, 255, 255, 0.2); }

.nw-state { font-size: 9px; letter-spacing: 0.06em; }
.nw-state--active     { color: #FF00CC; }
.nw-state--hacked     { color: rgba(255, 0, 204, 0.5); }
.nw-state--scorched   { color: #555555; }
.nw-state--street_doc { color: #FFB300; }

/* ── Resources ───────────────────────────────────────────────────────────────── */
.nw-resources {
    display: flex;
    flex-direction: column;
    gap: 3px;
    padding: 4px 10px 6px;
}

.nw-resource {
    display: flex;
    align-items: center;
    gap: 8px;
    font-size: 9px;
}

.nw-rkey     { color: rgba(0, 255, 255, 0.35); min-width: 48px; letter-spacing: 0.04em; }
.nw-available { color: #00FF88; letter-spacing: 0.04em; }
.nw-depleted  { color: rgba(255, 51, 51, 0.6); letter-spacing: 0.04em; }

/* ── Divider ─────────────────────────────────────────────────────────────────── */
.nw-divider {
    height: 1px;
    background: rgba(0, 255, 255, 0.08);
    margin: 4px 0;
}

/* ── Buttons ─────────────────────────────────────────────────────────────────── */
.nw-btn {
    display: block;
    width: calc(100% - 20px);
    margin: 4px 10px;
    padding: 6px 8px;
    background: none;
    border: 1px solid;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.05em;
    cursor: pointer;
    text-align: left;
    transition: background 0.1s, border-color 0.1s, color 0.1s;
}

.nw-btn--primary {
    border-color: rgba(0, 255, 255, 0.5);
    color: #00FFFF;
}
.nw-btn--primary:hover:not(:disabled) {
    background: rgba(0, 255, 255, 0.08);
    border-color: #00FFFF;
}

.nw-btn--secondary {
    border-color: rgba(255, 0, 204, 0.4);
    color: #FF00CC;
}
.nw-btn--secondary:hover:not(:disabled) {
    background: rgba(255, 0, 204, 0.06);
    border-color: #FF00CC;
}

.nw-btn--disabled {
    border-color: rgba(60, 60, 60, 0.6);
    color: #333333;
    cursor: not-allowed;
}

.nw-btn--hack {
    display: flex;
    justify-content: space-between;
    align-items: center;
}

.nw-btn--leave {
    border-color: rgba(0, 255, 255, 0.2);
    color: rgba(0, 255, 255, 0.4);
    margin-bottom: 8px;
}
.nw-btn--leave:hover {
    background: rgba(0, 255, 255, 0.04);
    color: rgba(0, 255, 255, 0.7);
}

.nw-cred-val {
    color: #00FF88;
    font-size: 9px;
}

/* ── Hint ────────────────────────────────────────────────────────────────────── */
.nw-hint {
    padding: 2px 10px 6px;
    font-size: 8px;
    color: rgba(0, 255, 255, 0.3);
    letter-spacing: 0.04em;
}
.nw-hint--warn { color: rgba(255, 179, 0, 0.55); }
</style>
