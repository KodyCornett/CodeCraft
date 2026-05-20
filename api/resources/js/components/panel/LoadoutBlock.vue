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
                <div class="lb-detail-row">
                    <span class="lb-detail-key">MAP</span>
                    <span class="lb-detail-val">{{ selectedCmd.mapEffect }}</span>
                </div>
                <div class="lb-detail-row">
                    <span class="lb-detail-key">HACK</span>
                    <span class="lb-detail-val">{{ selectedCmd.hackEffect }}</span>
                </div>
                <button
                    v-if="!selectedCmd.cooldown"
                    class="lb-use-btn"
                    @click="$emit('use-command', selectedCmd); selectedCmd = null"
                >
                    [USE ON MAP]
                </button>
                <div v-else class="lb-cd-notice">ON COOLDOWN — VISIT CYBERDOC TO RESET</div>
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

                <!-- System Stability — visible here since failed hacks damage SS -->
                <div class="lb-ss-row">
                    <span class="lb-ss-key">SS</span>
                    <div class="lb-ss-bar">
                        <div
                            class="lb-ss-fill"
                            :class="ssBarClass"
                            :style="{ width: ssPct + '%' }"
                        />
                    </div>
                    <span class="lb-ss-val" :class="ssValClass">
                        {{ ssPct }}%
                    </span>
                    <span v-if="isLimping" class="lb-limp-badge">LIMP</span>
                </div>
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
    if (props.isLimping)  return 'ss-fill--limp';
    if (ssPct.value <= 0) return 'ss-fill--dead';
    if (ssPct.value <= 25) return 'ss-fill--crit';
    if (ssPct.value <= 50) return 'ss-fill--low';
    return 'ss-fill--ok';
});
const ssValClass = computed(() => {
    if (props.isLimping)   return 'ss-val--limp';
    if (ssPct.value <= 25) return 'ss-val--crit';
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
.lb-idle-text { font-size: 7px; color: rgba(0,255,255,0.7); letter-spacing: 0.14em; text-shadow: 0 0 8px rgba(0,255,255,.4); }

.lb-content { font-family: 'JetBrains Mono', monospace; }

.lb-slot-label {
    font-size: 7px;
    color: rgba(0,255,255,.75);
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
.lb-cmd-name { font-size: 8px; color: rgba(0,255,255,.75); letter-spacing: .06em; }
.lb-cmd-tier { font-size: 7px; color: rgba(0,255,255,.3); }
.lb-cmd-type { font-size: 6px; letter-spacing: .1em; padding: 1px 4px; border-radius: 1px; }
.type--trap      { color: rgba(255,69,180,.7); border: 1px solid rgba(255,69,180,.2); }
.type--stealth   { color: rgba(125,249,255,.7); border: 1px solid rgba(125,249,255,.2); }
.type--defensive { color: rgba(0,255,136,.7);  border: 1px solid rgba(0,255,136,.2); }
.type--offensive { color: rgba(255,51,51,.8);  border: 1px solid rgba(255,51,51,.2); }

.lb-cd-badge     { font-size: 6px; color: rgba(255,51,51,.5);  letter-spacing: .1em; }
.lb-ready-badge  { font-size: 6px; color: rgba(0,255,136,.45); letter-spacing: .1em; }
.lb-active-badge {
    font-size: 6px;
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
    font-size: 6px;
    color: rgba(0,255,255,.28);
    letter-spacing: .1em;
    width: 30px;
    flex-shrink: 0;
    padding-top: 1px;
}
.lb-detail-val {
    font-size: 7px;
    color: rgba(0,255,255,.55);
    letter-spacing: .03em;
    line-height: 1.6;
}

.lb-hint {
    padding: 8px 14px 10px;
    font-size: 7px;
    color: rgba(0,255,255,.18);
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
    font-size: 6px;
    color: rgba(255,51,51,.45);
    letter-spacing: .08em;
    line-height: 1.6;
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

/* ── SS bar ───────────────────────────────────────────────────────────────── */
.lb-ss-row {
    display: flex;
    align-items: center;
    gap: 6px;
    width: 100%;
}
.lb-ss-key {
    font-size: 6px;
    color: rgba(0,255,255,.25);
    letter-spacing: .1em;
    width: 14px;
    flex-shrink: 0;
}
.lb-ss-bar {
    flex: 1;
    height: 4px;
    background: rgba(0,255,255,.07);
    border-radius: 2px;
    overflow: hidden;
}
.lb-ss-fill {
    height: 100%;
    border-radius: 2px;
    transition: width .4s ease, background .3s;
}
.ss-fill--ok   { background: #00FF88; }
.ss-fill--low  { background: #FFB300; }
.ss-fill--crit { background: #FF3333; animation: ss-crit-pulse .8s ease-in-out infinite; }
.ss-fill--limp { background: #FF3333; animation: ss-crit-pulse .8s ease-in-out infinite; }
.ss-fill--dead { background: rgba(255,51,51,.2); }
@keyframes ss-crit-pulse { 0%,100%{opacity:1} 50%{opacity:.35} }

.lb-ss-val { font-size: 7px; color: rgba(0,255,255,.45); letter-spacing: .04em; }
.ss-val--crit { color: #FF3333; }
.ss-val--limp { color: #FF3333; }

.lb-limp-badge {
    font-size: 6px;
    color: #FF3333;
    border: 1px solid rgba(255,51,51,.4);
    padding: 1px 4px;
    letter-spacing: .1em;
    animation: ss-crit-pulse .8s ease-in-out infinite;
}
.lb-stat { display: flex; align-items: center; gap: 4px; }
.lb-stat-key { font-size: 6px; color: rgba(0,255,255,.25); letter-spacing: .1em; }
.lb-stat-val { font-size: 8px; color: rgba(0,255,255,.5); }
.lb-stat--ready { color: #00FF88; }
.lb-stat-sep { font-size: 8px; color: rgba(0,255,255,.2); }
.lb-cd-warn {
    margin-left: auto;
    font-size: 6px;
    color: rgba(255,179,0,.6);
    letter-spacing: .08em;
    animation: warn-pulse 2s ease-in-out infinite;
}
@keyframes warn-pulse { 0%,100%{opacity:1} 50%{opacity:.45} }
</style>
