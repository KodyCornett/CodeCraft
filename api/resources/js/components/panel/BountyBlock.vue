<template>
    <PanelBlock icon="◎" title="BOUNTY BOARD" :start-open="true">

        <!-- Empty board -->
        <div v-if="activeBounties.length === 0" class="bb-idle">
            <span class="bb-idle-icon">◎</span>
            <span class="bb-idle-text">NETWORK CLEAR</span>
            <span class="bb-idle-sub">No active bounties detected</span>
        </div>

        <!-- Bounty entries -->
        <div v-else class="bb-content">

            <div class="bb-list-label">ACTIVE TARGETS</div>

            <div class="bb-list">
                <div
                    v-for="bounty in activeBounties"
                    :key="bounty.playerId"
                    class="bb-entry"
                    :class="{ 'bb-entry--os': bounty.isOpenSeason }"
                >
                    <!-- Open Season badge -->
                    <div v-if="bounty.isOpenSeason" class="bb-os-banner">⚡ OPEN SEASON</div>

                    <div class="bb-entry-row">
                        <!-- Handle + stars -->
                        <div class="bb-target-info">
                            <span class="bb-handle" :class="{ 'handle--os': bounty.isOpenSeason }">
                                {{ bounty.handle }}
                            </span>
                            <span class="bb-stars" :class="{ 'bb-stars--os': bounty.isOpenSeason }">
                                <span
                                    v-for="i in 5"
                                    :key="i"
                                    class="bb-star"
                                    :class="i <= bounty.stars ? 'bb-star--lit' : 'bb-star--dim'"
                                >★</span>
                            </span>
                        </div>

                        <!-- Reward -->
                        <div class="bb-reward">
                            <span class="bb-reward-label">YIELD</span>
                            <span class="bb-reward-val">◈ {{ bounty.reward > 0 ? bounty.reward : '???' }}</span>
                        </div>
                    </div>

                    <!-- Last known ping -->
                    <div class="bb-ping">
                        <span class="bb-ping-key">LAST PING</span>
                        <span class="bb-ping-val">{{ bounty.lastPing ?? '—' }}</span>
                    </div>
                    <div class="bb-ping">
                        <span class="bb-ping-key">DIST</span>
                        <span class="bb-ping-district">{{ bounty.district ?? 'UNKNOWN' }}</span>
                    </div>

                    <!-- Ping accuracy indicator -->
                    <div class="bb-accuracy">
                        <span
                            v-for="i in 5"
                            :key="i"
                            class="bb-acc-pip"
                            :class="i <= accuracyPips(bounty.level) ? 'pip--on' : 'pip--off'"
                        />
                        <span class="bb-acc-label">{{ accuracyLabel(bounty.level) }}</span>
                    </div>
                </div>
            </div>

        </div>

        <!-- Your own bounty status -->
        <div class="bb-self" :class="{ 'bb-self--active': playerBounty > 0, 'bb-self--os': playerOpenSeason }">
            <div class="bb-self-row">
                <span class="bb-self-key">YOUR BOUNTY</span>
                <span v-if="playerBounty === 0" class="bb-self-val self--none">CLEAN</span>
                <span v-else class="bb-self-stars" :class="{ 'bb-stars--os': playerOpenSeason }">
                    <span
                        v-for="i in 5"
                        :key="i"
                        class="bb-star"
                        :class="i <= playerBounty ? 'bb-star--lit' : 'bb-star--dim'"
                    >★</span>
                </span>
            </div>
            <div v-if="playerBounty > 0" class="bb-self-row">
                <span class="bb-self-key">LOOT BONUS</span>
                <span class="bb-self-mult" :class="{ 'self-mult--os': playerOpenSeason }">
                    +{{ selfBonusPct }}%
                </span>
            </div>
            <div v-if="playerOpenSeason" class="bb-os-self">⚡ YOU ARE OPEN SEASON</div>
        </div>

    </PanelBlock>
</template>

<script setup>
import { computed } from 'vue';
import PanelBlock from './PanelBlock.vue';

const props = defineProps({
    bounties: {
        type:    Array,
        default: () => [],
    },
    playerBounty:     { type: Number,  default: 0 },
    playerMultiplier: { type: Number,  default: 1.0 },
    playerOpenSeason: { type: Boolean, default: false },
});

const activeBounties = computed(() =>
    (props.bounties ?? []).filter(b => (b.stars ?? b.level ?? 0) > 0)
);

const selfBonusPct = computed(() =>
    Math.round(((props.playerMultiplier ?? 1.0) - 1.0) * 100)
);

function levelClass(level) {
    if (level >= 5) return 'level--os';
    if (level >= 3) return 'level--high';
    if (level >= 2) return 'level--mid';
    return 'level--low';
}

// Accuracy pips: higher bounty = more accurate = more pips lit
function accuracyPips(level) {
    if (level >= 5) return 5;  // Open Season — exact node always
    if (level >= 4) return 4;
    if (level >= 3) return 3;
    if (level >= 2) return 2;
    return 1;
}

function accuracyLabel(level) {
    if (level >= 5) return 'EXACT NODE';
    if (level >= 4) return 'NEAR NODE';
    if (level >= 3) return 'AREA';
    if (level >= 2) return 'DISTRICT';
    return 'VAGUE';
}
</script>

<style scoped>
.bb-idle {
    display: flex;
    flex-direction: column;
    align-items: center;
    gap: 6px;
    padding: 18px 16px 10px;
    text-align: center;
    font-family: 'JetBrains Mono', monospace;
}
.bb-idle-icon { font-size: 14px; color: rgba(0,255,255,0.35); }
.bb-idle-text { font-size: 9px; color: rgba(0,255,255,0.75); letter-spacing: 0.14em; }
.bb-idle-sub  { font-size: 9px; color: rgba(0,255,255,0.58); letter-spacing: 0.06em; }

.bb-content { font-family: 'JetBrains Mono', monospace; }

.bb-list-label {
    font-size: 8px;
    color: rgba(0,255,255,.65);
    letter-spacing: .14em;
    padding: 8px 14px 4px;
}

.bb-list { display: flex; flex-direction: column; gap: 1px; padding: 0 0 4px; }

.bb-entry {
    padding: 7px 14px 8px;
    border-top: 1px solid rgba(0,255,255,.05);
    display: flex;
    flex-direction: column;
    gap: 3px;
}
.bb-entry--os {
    background: rgba(255,51,51,.03);
    border-top-color: rgba(255,51,51,.15);
}

.bb-os-banner {
    font-size: 8px;
    color: #FF3333;
    letter-spacing: .14em;
    animation: os-flash 1.2s ease-in-out infinite;
    margin-bottom: 2px;
}
@keyframes os-flash { 0%,100%{opacity:1} 50%{opacity:.3} }

.bb-entry-row {
    display: flex;
    align-items: center;
    justify-content: space-between;
}

.bb-target-info { display: flex; align-items: center; gap: 6px; }
.bb-handle {
    font-size: 9px;
    color: rgba(0,255,255,.8);
    letter-spacing: .06em;
}
.handle--os {
    color: #FF3333;
    text-shadow: 0 0 6px rgba(255,51,51,.4);
}
/* Stars in entries */
.bb-stars       { display: flex; align-items: center; gap: 2px; }
.bb-stars--os .bb-star--lit { color: #FF4444 !important; text-shadow: 0 0 6px rgba(255,68,68,.8) !important; }

.bb-star       { font-size: 10px; line-height: 1; transition: color .2s; }
.bb-star--lit  { color: #FFB300; text-shadow: 0 0 6px rgba(255,179,0,.8); }
.bb-star--dim  { color: rgba(255,255,255,.28); }

.bb-self-stars { display: flex; align-items: center; gap: 2px; }

.bb-reward { display: flex; flex-direction: column; align-items: flex-end; gap: 1px; }
.bb-reward-label { font-size: 7px; color: rgba(0,255,136,.65); letter-spacing: .1em; }
.bb-reward-val { font-size: 9px; color: #00FF88; letter-spacing: .06em; }

.bb-ping {
    display: flex;
    align-items: flex-start;
    gap: 8px;
}
.bb-ping-key {
    font-size: 7px;
    color: rgba(0,255,255,.6);
    letter-spacing: .1em;
    width: 40px;
    flex-shrink: 0;
}
.bb-ping-val      { font-size: 8px; color: rgba(0,255,200,.82); letter-spacing: .06em; }
.bb-ping-district { font-size: 8px; color: rgba(0,255,255,.72); letter-spacing: .06em; }

.bb-accuracy {
    display: flex;
    align-items: center;
    gap: 3px;
    margin-top: 2px;
}
.bb-acc-pip {
    width: 10px;
    height: 3px;
    border-radius: 1px;
    flex-shrink: 0;
}
.pip--on  { background: #FF3333; box-shadow: 0 0 3px rgba(255,51,51,.5); }
.pip--off { background: rgba(0,255,255,.1); }
.bb-acc-label {
    font-size: 7px;
    color: rgba(255,51,51,.78);
    letter-spacing: .1em;
    margin-left: 3px;
}

/* ── Self bounty bar ────────────────────────────────────────────────────────── */
.bb-self {
    border-top: 1px solid rgba(0,255,255,.08);
    padding: 7px 14px 9px;
    display: flex;
    flex-direction: column;
    gap: 3px;
    font-family: 'JetBrains Mono', monospace;
    background: rgba(0,255,255,.01);
}
.bb-self--active { background: rgba(255,179,0,.02); border-top-color: rgba(255,179,0,.15); }
.bb-self--os     { background: rgba(255,51,51,.03);  border-top-color: rgba(255,51,51,.2); }

.bb-self-row { display: flex; align-items: center; gap: 8px; }
.bb-self-key { font-size: 8px; color: rgba(0,255,255,.65); letter-spacing: .1em; width: 72px; flex-shrink: 0; }
.bb-self-val { font-size: 8px; letter-spacing: .06em; }
.self--none   { color: rgba(0,255,136,.7); }
.self--active { color: #FFB300; }

.bb-self-mult        { font-size: 8px; color: #FFB300; letter-spacing: .06em; }
.self-mult--os       { color: #FF4444; }
.bb-os-self   {
    font-size: 8px;
    color: #FF3333;
    letter-spacing: .12em;
    animation: os-flash 1.2s ease-in-out infinite;
    margin-top: 2px;
}
</style>
