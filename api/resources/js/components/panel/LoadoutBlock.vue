<template>
    <PanelBlock icon="⬡" title="LOADOUT" :start-open="true">

        <!-- Idle state -->
        <div v-if="!commands || commands.length === 0" class="lb-idle">
            <span class="lb-idle-icon">⬡</span>
            <span class="lb-idle-text">NO COMMANDS EQUIPPED</span>
        </div>

        <!-- Command slots -->
        <div v-else class="lb-content">

            <div class="lb-slot-label">ACTIVE COMMANDS</div>

            <div class="lb-slots">
                <div
                    v-for="cmd in equippedCommands"
                    :key="cmd.id"
                    class="lb-slot"
                    :class="{
                        'lb-slot--ready':    !cmd.cooldown,
                        'lb-slot--cooldown': cmd.cooldown,
                        'lb-slot--selected': selectedCmd?.id === cmd.id,
                    }"
                    @click="selectCmd(cmd)"
                >
                    <!-- Status indicator -->
                    <span class="lb-status-dot" :class="cmd.cooldown ? 'dot--cd' : 'dot--ready'" />

                    <!-- Command name + tier -->
                    <div class="lb-cmd-info">
                        <span class="lb-cmd-name">{{ cmd.name.toUpperCase() }}</span>
                        <span class="lb-cmd-tier">T{{ cmd.tier }}</span>
                        <span class="lb-cmd-type" :class="`type--${cmd.type}`">{{ cmd.type.toUpperCase() }}</span>
                    </div>

                    <!-- Cooldown / active effect badge -->
                    <span v-if="cmd.cooldown && cmd.movesLeft > 0" class="lb-active-badge">{{ cmd.movesLeft }}M</span>
                    <span v-else-if="cmd.cooldown" class="lb-cd-badge">CD</span>
                    <span v-else class="lb-ready-badge">RDY</span>
                </div>
            </div>

            <div class="lb-divider" />

            <!-- Selected command detail -->
            <div v-if="selectedCmd" class="lb-detail">
                <div class="lb-detail-name">{{ selectedCmd.name.toUpperCase() }}</div>
                <div v-if="selectedCmd.mapEffect" class="lb-detail-row">
                    <span class="lb-detail-key">MAP</span>
                    <span class="lb-detail-val">{{ selectedCmd.mapEffect }}</span>
                </div>
                <div v-if="selectedCmd.gridbreachEffect" class="lb-detail-row">
                    <span class="lb-detail-key">GRIDBREACH</span>
                    <span class="lb-detail-val">{{ selectedCmd.gridbreachEffect }}</span>
                </div>
                <div v-if="selectedCmd.packethijackEffect" class="lb-detail-row">
                    <span class="lb-detail-key">PACKET HIJACK</span>
                    <span class="lb-detail-val">{{ selectedCmd.packethijackEffect }}</span>
                </div>
                <template v-if="selectedCmd.context === 'map'">
                    <button
                        v-if="!selectedCmd.cooldown"
                        class="lb-use-btn"
                        @click="$emit('use-command', selectedCmd); selectedCmd = null"
                    >
                        [USE ON MAP]
                    </button>
                    <div v-else class="lb-cd-notice">ON COOLDOWN — VISIT CYBERDOC TO RESET</div>
                </template>
                <div v-else class="lb-ph-notice">PACKET HIJACK ONLY — equip during a Packet Hijack session</div>
            </div>
            <div v-else class="lb-hint">Select a command to view effects</div>

            <div class="lb-divider" />

            <!-- Footer stats -->
            <div class="lb-footer">
                <div class="lb-footer-slots">
                    <span class="lb-stat">
                        <span class="lb-stat-key">READY</span>
                        <span class="lb-stat-val lb-stat--ready">{{ readyCount }}</span>
                    </span>
                    <span class="lb-stat-sep">/</span>
                    <span class="lb-stat">
                        <span class="lb-stat-val">{{ equippedCommands.length }}</span>
                        <span class="lb-stat-key">SLOTS</span>
                    </span>
                    <span v-if="cooldownCount > 0" class="lb-cd-warn">
                        ⚠ {{ cooldownCount }} ON COOLDOWN
                    </span>
                </div>

            </div>

        </div>

        <!-- System Stability — always-visible rig health -->
        <div class="lb-ss-section" :class="ssSectionClass">
            <div class="lb-ss-header">
                <span class="lb-ss-label">SYS.STABILITY</span>
                <span class="lb-ss-readout" :class="ssValClass">
                    {{ currentSS }}<span class="lb-ss-denom"> / {{ maxSS }}</span>
                </span>
            </div>
            <div class="lb-ss-track">
                <div class="lb-ss-fill" :class="ssBarClass" :style="{ width: ssPct + '%' }" />
                <div class="lb-ss-tick" style="left: 20%" />
                <div class="lb-ss-tick" style="left: 40%" />
                <div class="lb-ss-tick" style="left: 60%" />
                <div class="lb-ss-tick" style="left: 80%" />
            </div>
            <div class="lb-ss-footer-row">
                <span class="lb-ss-pct" :class="ssValClass">{{ ssPct }}%</span>
                <span v-if="ssPct < 25" class="lb-limp-badge">LIMP MODE</span>
            </div>
        </div>
    </PanelBlock>
</template>

<script setup>
import { ref, computed } from 'vue';
import PanelBlock from './PanelBlock.vue';

const props = defineProps({
    commands:  { type: Array,   default: () => [] },
    currentSS: { type: Number,  default: 0 },
    maxSS:     { type: Number,  default: 0 },
    isLimping: { type: Boolean, default: false },
});

defineEmits(['use-command']);

const selectedCmd = ref(null);

const equippedCommands = computed(() =>
    (props.commands ?? []).filter(c => c.equipped)
);

const readyCount    = computed(() => equippedCommands.value.filter(c => !c.cooldown).length);
const cooldownCount = computed(() => equippedCommands.value.filter(c =>  c.cooldown).length);

const ssPct = computed(() => {
    if (!props.maxSS) return 0;
    return Math.round((props.currentSS / props.maxSS) * 100);
});
const ssBarClass = computed(() => {
    if (ssPct.value <= 0)  return 'ss-fill--dead';
    if (ssPct.value < 25)  return 'ss-fill--limp';
    if (ssPct.value <= 25) return 'ss-fill--crit';
    if (ssPct.value <= 50) return 'ss-fill--low';
    return 'ss-fill--ok';
});
const ssValClass = computed(() => {
    if (ssPct.value < 25) return 'ss-val--limp';
    if (ssPct.value <= 25) return 'ss-val--crit';
    return '';
});
const ssSectionClass = computed(() => {
    if (ssPct.value < 25) return 'lb-ss--crit';
    if (ssPct.value <= 50) return 'lb-ss--warn';
    return '';
});

function selectCmd(cmd) {
    selectedCmd.value = selectedCmd.value?.id === cmd.id ? null : cmd;
}
</script>

<style scoped>
.lb-idle {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 8px;
    padding: 22px 16px;
    text-align: center;
    font-family: 'JetBrains Mono', monospace;
}
.lb-idle-icon { font-size: 14px; color: rgba(0,255,255,0.4); }
.lb-idle-text { font-size: 9px; color: rgba(0,255,255,0.78); letter-spacing: 0.14em; text-shadow: 0 0 8px rgba(0,255,255,.4); }

.lb-content { font-family: 'JetBrains Mono', monospace; }

.lb-slot-label {
    font-size: 8px;
    color: rgba(0,255,255,.82);
    letter-spacing: .14em;
    padding: 8px 14px 4px;
    text-shadow: 0 0 6px rgba(0,255,255,.4);
}

.lb-slots { display: flex; flex-direction: column; gap: 1px; padding: 0 8px 6px; }

.lb-slot {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 8px;
    border: 1px solid transparent;
    cursor: pointer;
    transition: all .12s;
    border-radius: 1px;
}
.lb-slot--ready {
    border-color: rgba(0,255,136,.12);
    background: rgba(0,255,136,.02);
}
.lb-slot--ready:hover {
    border-color: rgba(0,255,136,.35);
    background: rgba(0,255,136,.06);
}
.lb-slot--cooldown {
    border-color: rgba(255,51,51,.1);
    background: rgba(255,51,51,.02);
    cursor: not-allowed;
    opacity: .55;
}
/* Active effect — command is on cooldown but still running (has movesLeft) */
.lb-slot--cooldown:has(.lb-active-badge) {
    border-color: rgba(125,249,255,.25);
    background: rgba(125,249,255,.04);
    opacity: 1;
    cursor: default;
}
.lb-slot--selected {
    border-color: rgba(0,255,255,.35) !important;
    background: rgba(0,255,255,.04) !important;
}

.lb-status-dot {
    width: 5px; height: 5px;
    border-radius: 50%;
    flex-shrink: 0;
}
.dot--ready  { background: #00FF88; box-shadow: 0 0 4px rgba(0,255,136,.6); animation: dot-pulse 2s ease-in-out infinite; }
.dot--cd     { background: rgba(255,51,51,.4); }
@keyframes dot-pulse { 0%,100%{opacity:1} 50%{opacity:.4} }

.lb-cmd-info {
    display: flex;
    align-items: center;
    gap: 6px;
    flex: 1;
    min-width: 0;
}
.lb-cmd-name { font-size: 9px; color: rgba(0,255,255,.88); letter-spacing: .06em; }
.lb-cmd-tier { font-size: 8px; color: rgba(0,255,255,.55); }
.lb-cmd-type { font-size: 7px; letter-spacing: .1em; padding: 1px 4px; border-radius: 1px; }
.type--trap      { color: rgba(255,69,180,.7); border: 1px solid rgba(255,69,180,.2); }
.type--stealth   { color: rgba(125,249,255,.7); border: 1px solid rgba(125,249,255,.2); }
.type--defensive { color: rgba(0,255,136,.7);  border: 1px solid rgba(0,255,136,.2); }
.type--offensive { color: rgba(255,51,51,.8);  border: 1px solid rgba(255,51,51,.2); }

.lb-cd-badge     { font-size: 7px; color: rgba(255,51,51,.75);  letter-spacing: .1em; }
.lb-ready-badge  { font-size: 7px; color: rgba(0,255,136,.75); letter-spacing: .1em; }
.lb-active-badge {
    font-size: 7px;
    color: #7DF9FF;
    letter-spacing: .1em;
    animation: active-pulse 1s ease-in-out infinite;
}
@keyframes active-pulse { 0%,100%{opacity:1} 50%{opacity:.5} }

.lb-divider { height:1px; background:rgba(0,255,255,.06); }

.lb-detail {
    padding: 8px 14px 10px;
    display: flex;
    flex-direction: column;
    gap: 5px;
}
.lb-detail-name {
    font-size: 8px;
    color: #00FFFF;
    letter-spacing: .08em;
    margin-bottom: 2px;
}
.lb-detail-row { display: flex; gap: 8px; align-items: flex-start; }
.lb-detail-key {
    font-size: 8px;
    color: rgba(0,255,255,.62);
    letter-spacing: .1em;
    width: 34px;
    flex-shrink: 0;
    padding-top: 1px;
}
.lb-detail-val {
    font-size: 9px;
    color: rgba(0,255,255,.78);
    letter-spacing: .03em;
    line-height: 1.6;
}

.lb-hint {
    padding: 8px 14px 10px;
    font-size: 9px;
    color: rgba(0,255,255,.52);
    letter-spacing: .06em;
    font-style: italic;
}

.lb-use-btn {
    margin-top: 6px;
    background: transparent;
    border: 1px solid rgba(0,255,136,.35);
    color: rgba(0,255,136,.7);
    font-family: 'JetBrains Mono', monospace;
    font-size: 7px;
    letter-spacing: .1em;
    padding: 4px 10px;
    cursor: pointer;
    transition: all .12s;
    width: 100%;
}
.lb-use-btn:hover {
    color: #00FF88;
    border-color: rgba(0,255,136,.7);
    background: rgba(0,255,136,.07);
}

.lb-cd-notice {
    margin-top: 6px;
    font-size: 8px;
    color: rgba(255,51,51,.72);
    letter-spacing: .08em;
    line-height: 1.6;
}
.lb-ph-notice {
    margin-top: 6px;
    font-size: 8px;
    color: rgba(125,249,255,.55);
    letter-spacing: .06em;
    line-height: 1.6;
    font-style: italic;
}

.lb-footer {
    padding: 6px 14px 8px;
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.lb-footer-slots {
    display: flex;
    align-items: center;
    gap: 6px;
}

/* ── Prominent SS Section ─────────────────────────────────────────────────── */
.lb-ss-section {
    padding: 10px 14px 12px;
    border-top: 1px solid rgba(0,255,255,.08);
    background: rgba(0,255,255,.012);
    font-family: 'JetBrains Mono', monospace;
    transition: background .3s, border-color .3s;
}
.lb-ss--warn {
    background: rgba(255,179,0,.025);
    border-top-color: rgba(255,179,0,.15);
}
.lb-ss--crit {
    background: rgba(255,51,51,.04);
    border-top-color: rgba(255,51,51,.25);
}
.lb-ss-header {
    display: flex;
    justify-content: space-between;
    align-items: baseline;
    margin-bottom: 8px;
}
.lb-ss-label {
    font-size: 8px;
    color: rgba(0,255,255,.72);
    letter-spacing: .18em;
    text-shadow: 0 0 8px rgba(0,255,255,.25);
}
.lb-ss-readout {
    font-size: 12px;
    color: rgba(0,255,255,.9);
    letter-spacing: .04em;
    text-shadow: 0 0 10px rgba(0,255,255,.4);
}
.lb-ss-denom {
    font-size: 9px;
    color: rgba(0,255,255,.55);
}
.lb-ss-track {
    position: relative;
    height: 8px;
    background: rgba(0,255,255,.07);
    border-radius: 2px;
    overflow: hidden;
    margin-bottom: 6px;
}
.lb-ss-fill {
    position: absolute;
    left: 0; top: 0; bottom: 0;
    border-radius: 2px;
    transition: width .4s ease, background .3s;
}
.lb-ss-tick {
    position: absolute;
    top: 0; bottom: 0;
    width: 1px;
    background: rgba(0,0,12,.65);
    z-index: 2;
    pointer-events: none;
}
.ss-fill--ok   { background: #00FF88; box-shadow: 0 0 6px rgba(0,255,136,.5); }
.ss-fill--low  { background: #FFB300; box-shadow: 0 0 6px rgba(255,179,0,.4); }
.ss-fill--crit { background: #FF3333; animation: ss-crit-pulse .8s ease-in-out infinite; }
.ss-fill--limp { background: #FF3333; animation: ss-crit-pulse .8s ease-in-out infinite; }
.ss-fill--dead { background: rgba(255,51,51,.2); }
@keyframes ss-crit-pulse { 0%,100%{opacity:1} 50%{opacity:.35} }

.lb-ss-footer-row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    min-height: 14px;
}
.lb-ss-pct { font-size: 8px; color: rgba(0,255,255,.65); letter-spacing: .06em; }
.ss-val--crit { color: #FF3333; text-shadow: 0 0 8px rgba(255,51,51,.5); }
.ss-val--limp { color: #FF3333; text-shadow: 0 0 8px rgba(255,51,51,.5); }

.lb-limp-badge {
    font-size: 6px;
    color: #FF3333;
    border: 1px solid rgba(255,51,51,.4);
    padding: 1px 5px;
    letter-spacing: .12em;
    animation: ss-crit-pulse .8s ease-in-out infinite;
}
.lb-stat { display: flex; align-items: center; gap: 4px; }
.lb-stat-key { font-size: 8px; color: rgba(0,255,255,.62); letter-spacing: .1em; }
.lb-stat-val { font-size: 9px; color: rgba(0,255,255,.72); }
.lb-stat--ready { color: #00FF88; }
.lb-stat-sep { font-size: 9px; color: rgba(0,255,255,.38); }
.lb-cd-warn {
    margin-left: auto;
    font-size: 6px;
    color: rgba(255,179,0,.6);
    letter-spacing: .08em;
    animation: warn-pulse 2s ease-in-out infinite;
}
@keyframes warn-pulse { 0%,100%{opacity:1} 50%{opacity:.45} }
</style>
