<template>
    <aside class="side-panel">

        <!-- Scanline texture -->
        <div class="sp-scanline" />

        <!-- Panel header — always visible, aligns with HUD bar height -->
        <div class="sp-header">
            <div class="sp-header-left">
                <span class="sp-splice-logo">SPLICE</span>
                <span class="sp-header-sep">//</span>
                <span class="sp-title">SYS.PANEL</span>
            </div>
            <span class="sp-status-dot" />
        </div>

        <!-- Scrollable block stack -->
        <div class="sp-body">

            <NodeInfoBlock
                :node="node"
                :is-on-node="isOnNode"
                :is-adjacent="isAdjacent"
                :resources="resources"
                :node-players="nodePlayers"
                :traces="traces"
                @hack="$emit('hack', $event)"
                @open-store="$emit('open-store')"
                @hack-player="$emit('hack-player', $event)"
                @move="$emit('move')"
            />

            <LoadoutBlock
                :commands="commands"
                :current-s-s="currentSS"
                :max-s-s="maxSS"
                :is-limping="isLimping"
                @use-command="$emit('use-command', $event)"
            />

            <BountyBlock
                :bounties="bounties"
                :player-bounty="playerBounty"
                :player-multiplier="playerMultiplier"
                :player-open-season="playerOpenSeason"
            />

        </div>

    </aside>
</template>

<script setup>
import NodeInfoBlock from '@/components/panel/NodeInfoBlock.vue';
import LoadoutBlock  from '@/components/panel/LoadoutBlock.vue';
import BountyBlock   from '@/components/panel/BountyBlock.vue';

defineProps({
    // Node Info
    node:        { type: Object,  default: null },
    isOnNode:    { type: Boolean, default: false },
    isAdjacent:  { type: Boolean, default: false },
    resources:   { type: Object,  default: null },
    nodePlayers: { type: Array,   default: () => [] },
    traces:      { type: Array,   default: () => [] },

    // Loadout + SS
    commands:  { type: Array,   default: () => [] },
    currentSS: { type: Number,  default: 0 },
    maxSS:     { type: Number,  default: 0 },
    isLimping: { type: Boolean, default: false },

    // Bounty Board
    bounties:         { type: Array,   default: () => [] },
    playerBounty:     { type: Number,  default: 0 },
    playerMultiplier: { type: Number,  default: 1.0 },
    playerOpenSeason: { type: Boolean, default: false },
});

defineEmits(['hack', 'open-store', 'use-command', 'hack-player', 'move']);
</script>

<style scoped>
.side-panel {
    position: relative;
    width: 280px;
    flex-shrink: 0;
    background: rgba(3, 6, 10, 0.97);
    border-left: 1px solid rgba(0, 255, 255, 0.15);
    font-family: 'JetBrains Mono', monospace;
    display: flex;
    flex-direction: column;
    overflow: hidden;
    z-index: 10;
}

/* ── Scanline overlay ─────────────────────────────────────────────────────── */
.sp-scanline {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(0, 255, 255, 0.01) 2px,
        rgba(0, 255, 255, 0.01) 4px
    );
    pointer-events: none;
    z-index: 1;
}

/* ── Header — matches HUD bar height (32px) ───────────────────────────────── */
.sp-header {
    position: relative;
    z-index: 2;
    display: flex;
    align-items: center;
    justify-content: space-between;
    height: 32px;
    padding: 0 14px;
    background: rgba(0, 255, 255, 0.02);
    border-bottom: 1px solid rgba(0, 255, 255, 0.1);
    flex-shrink: 0;
}

.sp-header-left {
    display: flex;
    align-items: center;
    gap: 6px;
}

.sp-splice-logo {
    font-size: 9px;
    color: #00FFFF;
    letter-spacing: 0.2em;
    text-shadow: 0 0 10px rgba(0, 255, 255, 0.8), 0 0 20px rgba(0, 255, 255, 0.3);
}
.sp-header-sep {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.5);
}
.sp-title {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.85);
    letter-spacing: 0.14em;
    text-shadow: 0 0 8px rgba(0, 255, 255, 0.5);
}

.sp-status-dot {
    width: 5px;
    height: 5px;
    border-radius: 50%;
    background: #00FF88;
    box-shadow: 0 0 5px rgba(0, 255, 136, 0.6);
    animation: status-pulse 3s ease-in-out infinite;
}
@keyframes status-pulse {
    0%, 100% { opacity: 1;   }
    50%       { opacity: 0.3; }
}

/* ── Body — scrollable block stack ───────────────────────────────────────── */
.sp-body {
    position: relative;
    z-index: 2;
    flex: 1;
    overflow-y: auto;
    overflow-x: hidden;
}

.sp-body::-webkit-scrollbar       { width: 3px; }
.sp-body::-webkit-scrollbar-track { background: transparent; }
.sp-body::-webkit-scrollbar-thumb { background: rgba(0, 255, 255, 0.12); border-radius: 2px; }
</style>
