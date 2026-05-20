<template>
    <div class="node-content">

        <!-- Network name + SPLICE address -->
        <div class="nw-header">
            <span class="nw-ping">◈</span>
            <div class="nw-header-text">
                <div class="nw-title">{{ identity.networkName }}</div>
                <div class="nw-address">
                    <span class="nw-addr-label">SPLICE</span>
                    <span class="nw-addr-sep">//</span>
                    <span class="nw-addr-value">{{ identity.spliceAddress }}</span>
                </div>
            </div>
        </div>

        <div class="nw-divider" />

        <!-- Network data rows -->
        <div class="nw-data-rows">
            <div class="nw-row">
                <span class="nw-key">TYPE</span>
                <span class="nw-val" :class="typeColorClass">{{ typeLabel }}</span>
            </div>
            <div class="nw-row">
                <span class="nw-key">SECTOR</span>
                <span class="nw-val nw-val--dim">{{ sectorLabel }}</span>
            </div>
            <div class="nw-row">
                <span class="nw-key">STATUS</span>
                <span class="nw-val" :class="statusColorClass">{{ statusLabel }}</span>
            </div>
        </div>

        <div class="nw-divider" />

        <!-- CyberDoc store access -->
        <div v-if="node.type === 'cyberdoc'" class="nw-store-block">
            <div class="nw-store-desc">
                Licensed hardware &amp; software vendor.<br>Secure encrypted storefront.
            </div>
            <button class="nw-store-btn" @click="$emit('open-store')">
                <span class="nw-store-icon">⬡</span>
                OPEN STOREFRONT
            </button>
        </div>

        <!-- Resource availability (non-cyberdoc nodes only) -->
        <div v-else class="nw-resources">
            <div class="nw-res-title">NODE RESOURCES</div>

            <div class="nw-res-row">
                <span class="nw-res-key">CREDS</span>
                <span v-if="resources.creds.available" class="nw-res-val nw-res--avail">
                    ◈ {{ formatCreds(resources.creds.value) }}
                </span>
                <span v-else class="nw-res-val nw-res--depleted">✕ DEPLETED</span>
                <button
                    class="nw-hack-btn"
                    :class="{ 'nw-hack-btn--locked': !resources.creds.available }"
                    :disabled="!resources.creds.available"
                    @click="$emit('hack', 'creds')"
                >[HACK]</button>
            </div>

            <div class="nw-res-row">
                <span class="nw-res-key">TECH</span>
                <span v-if="resources.tech.available" class="nw-res-val nw-res--avail">
                    ◈ {{ resources.tech.value }} PTS
                </span>
                <span v-else class="nw-res-val nw-res--depleted">✕ DEPLETED</span>
                <button
                    class="nw-hack-btn"
                    :class="{ 'nw-hack-btn--locked': !resources.tech.available }"
                    :disabled="!resources.tech.available"
                    @click="$emit('hack', 'tech')"
                >[HACK]</button>
            </div>

            <div class="nw-res-row">
                <span class="nw-res-key">UPLINK</span>
                <span v-if="resources.uplink.available" class="nw-res-val nw-res--uplink">
                    ◈ {{ resources.uplink.value }} LINK{{ resources.uplink.value !== 1 ? 'S' : '' }}
                </span>
                <span v-else class="nw-res-val nw-res--depleted">✕ DEPLETED</span>
                <button
                    class="nw-hack-btn nw-hack-btn--uplink"
                    :class="{ 'nw-hack-btn--locked': !resources.uplink.available }"
                    :disabled="!resources.uplink.available"
                    @click="$emit('hack', 'uplink')"
                >[HACK]</button>
            </div>
        </div>

        <div class="nw-divider" />

        <!-- Footer connection status -->
        <div class="nw-footer">
            <span v-if="isOnNode" class="nw-status-you">◉ CONNECTED</span>
            <span v-else class="nw-status-remote">○ REMOTE NODE</span>
        </div>

    </div>
</template>

<script setup>
import { computed } from 'vue';
import { getNodeIdentity } from '@/composables/useNodeIdentity.js';

const props = defineProps({
    node:     { type: Object,  required: true },
    isOnNode: { type: Boolean, default: false },
    resources: {
        type:    Object,
        default: () => ({
            creds:  { available: true, value: 750 },
            tech:   { available: true, value: 4   },
            uplink: { available: true, value: 2   },
        }),
    },
});

defineEmits(['hack', 'open-store']);

// ── Identity ──────────────────────────────────────────────────────────────────
const identity = computed(() => getNodeIdentity(props.node));

// ── Display labels ────────────────────────────────────────────────────────────
const typeLabel = computed(() => {
    if (props.node.type === 'cyberdoc') return 'CYBER DOC';
    return 'ACTION NODE';
});

const typeColorClass = computed(() => {
    if (props.node.type === 'cyberdoc') return 'nw-val--cyberdoc';
    return 'nw-val--action';
});

const sectorLabel = computed(() =>
    props.node.district ? props.node.district.toUpperCase() : 'TRANSIT RELAY',
);

const statusLabel = computed(() =>
    props.isOnNode ? 'ACTIVE SESSION' : 'STANDBY',
);

const statusColorClass = computed(() =>
    props.isOnNode ? 'nw-val--connected' : 'nw-val--dim',
);

// ── Helpers ───────────────────────────────────────────────────────────────────
function formatCreds(n) {
    return n >= 1000 ? (n / 1000).toFixed(1) + 'K' : String(n);
}
</script>

<style scoped>
.node-content {
    display: flex;
    flex-direction: column;
    width: 100%;
    font-family: 'JetBrains Mono', monospace;
}

/* ── Header (network name + address) ─────────────────────────────────────── */
.nw-header {
    display: flex;
    align-items: flex-start;
    gap: 10px;
    padding: 14px 14px 12px;
    background: rgba(0, 255, 255, 0.02);
}

.nw-ping {
    color: #00FF88;
    font-size: 10px;
    flex-shrink: 0;
    margin-top: 2px;
    animation: ping-pulse 2s ease-in-out infinite;
}

@keyframes ping-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.3; }
}

.nw-header-text {
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-width: 0;
}

.nw-title {
    font-size: 11px;
    color: #00FFFF;
    letter-spacing: 0.08em;
    white-space: nowrap;
    overflow: hidden;
    text-overflow: ellipsis;
    text-shadow: 0 0 8px rgba(0, 255, 255, 0.35);
}

.nw-address {
    display: flex;
    align-items: center;
    gap: 5px;
}

.nw-addr-label {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.3);
    letter-spacing: 0.1em;
}

.nw-addr-sep {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.15);
}

.nw-addr-value {
    font-size: 9px;
    color: rgba(0, 255, 200, 0.7);
    letter-spacing: 0.1em;
}

/* ── Divider ──────────────────────────────────────────────────────────────── */
.nw-divider {
    height: 1px;
    background: rgba(0, 255, 255, 0.07);
    margin: 0;
}

/* ── Data rows ────────────────────────────────────────────────────────────── */
.nw-data-rows {
    display: flex;
    flex-direction: column;
    padding: 8px 0 6px;
}

.nw-row {
    display: flex;
    align-items: center;
    padding: 4px 14px;
}

.nw-key {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.3);
    letter-spacing: 0.08em;
    width: 60px;
    flex-shrink: 0;
}

.nw-val               { font-size: 9px; letter-spacing: 0.06em; }
.nw-val--action       { color: #FF69B4; }
.nw-val--cyberdoc     { color: #FFB300; text-shadow: 0 0 6px rgba(255, 179, 0, 0.3); }
.nw-val--connected    { color: #00FF88; }
.nw-val--dim          { color: rgba(0, 255, 255, 0.45); }

/* ── Resources ────────────────────────────────────────────────────────────── */
.nw-resources {
    padding: 10px 14px 12px;
    display: flex;
    flex-direction: column;
    gap: 2px;
}

.nw-res-title {
    font-size: 7px;
    color: rgba(0, 255, 255, 0.2);
    letter-spacing: 0.14em;
    margin-bottom: 6px;
}

.nw-res-row {
    display: flex;
    align-items: center;
    padding: 3px 0;
}

.nw-res-key {
    font-size: 8px;
    color: rgba(0, 255, 255, 0.3);
    letter-spacing: 0.08em;
    width: 60px;
    flex-shrink: 0;
}

.nw-res-val           { font-size: 9px; letter-spacing: 0.06em; flex: 1; }
.nw-res--avail        { color: #00FF88; text-shadow: 0 0 6px rgba(0, 255, 136, 0.3); }
.nw-res--uplink       { color: #7DF9FF; text-shadow: 0 0 6px rgba(125, 249, 255, 0.3); }
.nw-res--depleted     { color: rgba(255, 51, 51, 0.45); letter-spacing: 0.1em; flex: 1; }

/* ── CyberDoc store block ─────────────────────────────────────────────────── */
.nw-store-block {
    padding: 14px 14px 16px;
    display: flex;
    flex-direction: column;
    gap: 12px;
}

.nw-store-desc {
    font-size: 8px;
    color: rgba(255, 179, 0, 0.4);
    letter-spacing: 0.06em;
    line-height: 1.7;
}

.nw-store-btn {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 8px;
    width: 100%;
    padding: 10px 0;
    background: rgba(255, 179, 0, 0.04);
    border: 1px solid rgba(255, 179, 0, 0.35);
    color: #FFB300;
    font-family: 'JetBrains Mono', monospace;
    font-size: 9px;
    letter-spacing: 0.12em;
    cursor: pointer;
    transition: background 0.15s, border-color 0.15s, box-shadow 0.15s;
}
.nw-store-btn:hover {
    background: rgba(255, 179, 0, 0.09);
    border-color: rgba(255, 179, 0, 0.7);
    box-shadow: 0 0 14px rgba(255, 179, 0, 0.12);
}

.nw-store-icon { font-size: 11px; opacity: 0.8; }

/* ── Hack buttons ─────────────────────────────────────────────────────────── */
.nw-hack-btn {
    margin-left: auto;
    background: transparent;
    border: 1px solid rgba(0, 255, 136, 0.3);
    color: rgba(0, 255, 136, 0.65);
    font-family: 'JetBrains Mono', monospace;
    font-size: 7px;
    letter-spacing: 0.1em;
    padding: 3px 7px;
    cursor: pointer;
    flex-shrink: 0;
    transition: color 0.12s, border-color 0.12s, box-shadow 0.12s, background 0.12s;
}
.nw-hack-btn:hover:not(:disabled) {
    color: #00FF88;
    border-color: rgba(0, 255, 136, 0.75);
    background: rgba(0, 255, 136, 0.07);
    box-shadow: 0 0 8px rgba(0, 255, 136, 0.18);
}

.nw-hack-btn--uplink {
    border-color: rgba(125, 249, 255, 0.3);
    color: rgba(125, 249, 255, 0.65);
}
.nw-hack-btn--uplink:hover:not(:disabled) {
    color: #7DF9FF;
    border-color: rgba(125, 249, 255, 0.75);
    background: rgba(125, 249, 255, 0.07);
    box-shadow: 0 0 8px rgba(125, 249, 255, 0.18);
}

.nw-hack-btn--locked,
.nw-hack-btn:disabled {
    border-color: rgba(255, 51, 51, 0.18);
    color: rgba(255, 51, 51, 0.28);
    cursor: not-allowed;
}

/* ── Footer ───────────────────────────────────────────────────────────────── */
.nw-footer {
    display: flex;
    align-items: center;
    padding: 8px 14px 12px;
    background: rgba(0, 0, 0, 0.15);
}

.nw-status-you    { font-size: 8px; color: #00FF88; letter-spacing: 0.1em; }
.nw-status-remote { font-size: 8px; color: rgba(0, 255, 255, 0.28); letter-spacing: 0.1em; }
</style>
