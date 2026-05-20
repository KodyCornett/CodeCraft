<template>
    <div class="hud-bar">

        <!-- Handle -->
        <div class="hud-item">
            <span class="hud-key">CC</span>
            <span class="hud-sep">:</span>
            <span class="hud-val" :class="{ 'val-open-season': player.isOpenSeason }">
                {{ player.handle }}
            </span>
        </div>

        <div class="hud-divider" />

        <!-- District -->
        <div class="hud-item">
            <span class="hud-key">DIST</span>
            <span class="hud-sep">:</span>
            <span class="hud-val hud-val--dim">{{ player.district ?? 'UNKNOWN' }}</span>
        </div>

        <div class="hud-divider" />

        <!-- Uplink -->
        <div class="hud-item">
            <span class="hud-key">UPLINK</span>
            <span class="hud-sep">:</span>
            <span class="hud-val" :class="uplinkClass">
                {{ player.uplink ?? 0 }}/{{ player.maxUplink ?? 3 }}
            </span>
        </div>

        <div class="hud-divider" />

        <!-- Cache -->
        <div class="hud-item">
            <span class="hud-key">CACHE</span>
            <span class="hud-sep">:</span>
            <span class="hud-val" :class="cacheClass">
                {{ player.cache ?? 0 }}/{{ player.maxCache ?? 5 }}
            </span>
        </div>

        <div class="hud-divider" />

        <!-- Tech Points -->
        <div class="hud-item">
            <span class="hud-key">TP</span>
            <span class="hud-sep">:</span>
            <span class="hud-val hud-val--tp">{{ fmtTp(player.techPoints) }}</span>
        </div>

        <div class="hud-divider" />

        <!-- Bounty — ticker + stars + multiplier -->
        <div class="hud-item hud-item--bounty">
            <span class="hud-key">BOUNTY</span>
            <span class="hud-sep">:</span>

            <!-- Hack counter — resets each tier -->
            <span class="hud-val hud-ticker" :class="tickerClass">
                {{ bountyTicker.current }}/{{ bountyTicker.max }}
            </span>

            <!-- 5 star slots -->
            <div class="hud-stars">
                <span
                    v-for="i in 5"
                    :key="i"
                    class="hud-star"
                    :class="{
                        'star--lit':  i <= player.bountyLevel,
                        'star--os':   i <= player.bountyLevel && player.isOpenSeason,
                        'star--next': i === player.bountyLevel + 1,
                    }"
                >★</span>
            </div>

            <!-- Loot multiplier bonus — always visible, starts at +0% -->
            <span class="hud-multiplier" :class="{
                'hud-multiplier--active': player.bountyLevel > 0 && !player.isOpenSeason,
                'hud-multiplier--os':     player.isOpenSeason,
            }">
                +{{ multiplierBonus }}%
            </span>
        </div>

        <!-- Open Season badge — only when active -->
        <template v-if="player.isOpenSeason">
            <div class="hud-divider" />
            <div class="hud-item hud-item--os">
                <span>⚡ OPEN SEASON</span>
            </div>
        </template>

    </div>

    <!-- Move-block flash toast — mounts below the HUD bar when set -->
    <Transition name="hud-flash-fade">
        <div v-if="flash" class="hud-flash" :class="flashClass">
            <span class="hud-flash-icon">⛔</span>
            {{ flash }}
        </div>
    </Transition>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    player: {
        type: Object,
        default: () => ({
            handle: 'UNKNOWN', uplink: 3, maxUplink: 3,
            cache: 0, maxCache: 5,
            district: null, bountyLevel: 0, isOpenSeason: false,
            currentSS: 0, maxSS: 0, techPoints: 0,
        }),
    },
    rig:          { type: Object, default: null },
    currentNode:  { type: Object, default: null },
    bountyTicker: {
        type:    Object,
        default: () => ({ current: 0, max: 10 }),
    },
    /** Move-block flash message. Empty string = hidden. */
    flash: { type: String, default: '' },
});

/**
 * Format tech points for display.
 * Shows up to 2 decimal places but strips trailing zeros so:
 *   1.00 → "1"   |   1.50 → "1.5"   |   1.25 → "1.25"
 */
function fmtTp(val) {
    const n = parseFloat(val ?? 0);
    return isNaN(n) ? '0' : parseFloat(n.toFixed(2)).toString();
}

const uplinkClass = computed(() => {
    const pct = (props.player.uplink ?? 0) / (props.player.maxUplink ?? 3);
    if (pct <= 0.25) return 'val-uplink-crit';
    if (pct <= 0.5)  return 'val-uplink-low';
    return 'val-uplink-ok';
});

const cacheClass = computed(() => {
    const pct = (props.player.cache ?? 0) / (props.player.maxCache ?? 5);
    if (pct >= 1)    return 'val-cache-full';
    if (pct >= 0.75) return 'val-cache-high';
    if (pct >= 0.5)  return 'val-cache-mid';
    return 'val-cache-ok';
});

const ssClass = computed(() => {
    const max = props.player.maxSS ?? 0;
    if (!max) return 'val-ss-ok';
    const pct = (props.player.currentSS ?? 0) / max;
    if (pct <= 0)    return 'val-ss-dead';
    if (pct <= 0.25) return 'val-ss-crit';
    if (pct <= 0.5)  return 'val-ss-low';
    return 'val-ss-ok';
});

// Ticker pulses amber when close to next threshold
const tickerClass = computed(() => {
    const pct = props.bountyTicker.current / props.bountyTicker.max;
    if (props.player.bountyLevel > 0) {
        if (pct >= 0.8) return 'ticker--warn';
        return 'ticker--active';
    }
    if (pct >= 0.8) return 'ticker--warn';
    return 'ticker--idle';
});

// "+25%" style display — (multiplier - 1) * 100, rounded
const multiplierBonus = computed(() =>
    Math.round(((props.player.bountyMultiplier ?? 1.0) - 1.0) * 100)
);

// Flash toast colour — red for system failure, amber for uplink
const flashClass = computed(() =>
    props.flash.includes('SYSTEM FAILURE') ? 'hud-flash--critical' : 'hud-flash--warn'
);
</script>

<style scoped>
.hud-bar {
    position: absolute;
    top: 0;
    left: 0;
    right: 0;
    z-index: 20;
    height: 32px;
    display: flex;
    align-items: center;
    background: rgba(4, 4, 10, 0.82);
    border-bottom: 1px solid rgba(0, 255, 255, 0.15);
    backdrop-filter: blur(4px);
    padding: 0 14px;
    gap: 0;
    font-family: 'JetBrains Mono', monospace;
}

/* ── Items ────────────────────────────────────────────────────────────────── */
.hud-item {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 0 14px;
    white-space: nowrap;
}

.hud-key {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.35);
    letter-spacing: 0.1em;
}

.hud-sep {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.2);
}

.hud-val {
    font-size: 10px;
    color: #00FFFF;
    letter-spacing: 0.06em;
}

.hud-val--dim { color: rgba(0, 255, 255, 0.55); }

/* ── Divider ──────────────────────────────────────────────────────────────── */
.hud-divider {
    width: 1px;
    height: 14px;
    background: rgba(0, 255, 255, 0.12);
    flex-shrink: 0;
}

/* ── SS states ───────────────────────────────────────────────────────────── */
.val-ss-ok   { color: #00FF88; }
.val-ss-low  { color: #FFB300; }
.val-ss-crit {
    color: #FF3333;
    animation: crit-pulse 0.8s ease-in-out infinite;
}
.val-ss-dead { color: rgba(255,51,51,.3); }

/* ── Tech Points ─────────────────────────────────────────────────────────── */
.hud-val--tp { color: rgba(125,249,255,.75); }

/* ── Uplink states ────────────────────────────────────────────────────────── */
.val-uplink-ok   { color: #00FF88; }
.val-uplink-low  { color: #FFB300; }
.val-uplink-crit {
    color: #FF3333;
    animation: crit-pulse 0.8s ease-in-out infinite;
}

/* ── Cache states — colour reflects ping exposure risk, not a lock ────────── */
.val-cache-ok   { color: rgba(0, 255, 255, 0.6); }
.val-cache-mid  { color: #FFB300; }
.val-cache-high {
    color: #FF6B00;
    animation: cache-warn 1.5s ease-in-out infinite;
}
.val-cache-full {
    /* Full cache = maximum ping exposure radius */
    color: #FF3333;
    animation: crit-pulse 0.6s ease-in-out infinite;
}

@keyframes cache-warn {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.5; }
}

/* ── Bounty section ───────────────────────────────────────────────────────── */
.hud-item--bounty { gap: 6px; }

/* Hack counter */
.hud-ticker     { font-size: 10px; letter-spacing: 0.06em; }
.ticker--idle   { color: rgba(0, 255, 255, 0.45); }
.ticker--active { color: #FFB300; }
.ticker--warn   {
    color: #FF6B00;
    animation: ticker-warn 0.9s ease-in-out infinite;
}

@keyframes ticker-warn {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.5; }
}

/* Stars */
.hud-stars {
    display: flex;
    align-items: center;
    gap: 3px;
}

.hud-star {
    font-size: 13px;
    color: rgba(255, 255, 255, 0.15);
    transition: color 0.3s ease, text-shadow 0.3s ease;
    line-height: 1;
}

.hud-star.star--lit {
    color: #FFB300;
    text-shadow: 0 0 8px rgba(255, 179, 0, 0.9), 0 0 2px rgba(255, 179, 0, 1);
    animation: star-glow 2.5s ease-in-out infinite;
}

.hud-star.star--os {
    color: #FF4444;
    text-shadow: 0 0 10px rgba(255, 68, 68, 1.0), 0 0 3px rgba(255, 68, 68, 1);
    animation: star-os 1.2s ease-in-out infinite;
}

/* Next unlit star — visible enough to track progress toward */
.hud-star.star--next {
    color: rgba(255, 179, 0, 0.3);
    animation: star-next 2.5s ease-in-out infinite;
}

@keyframes star-glow {
    0%, 100% { text-shadow: 0 0 6px rgba(255, 179, 0, 0.7), 0 0 2px rgba(255, 179, 0, 1); }
    50%       { text-shadow: 0 0 14px rgba(255, 179, 0, 1.0), 0 0 4px rgba(255, 179, 0, 1); }
}

@keyframes star-os {
    0%, 100% { text-shadow: 0 0 8px rgba(255, 68, 68, 0.8);  color: #FF4444; }
    50%       { text-shadow: 0 0 18px rgba(255, 68, 68, 1.0); color: #FF6666; }
}

@keyframes star-next {
    0%, 100% { opacity: 0.3; }
    50%       { opacity: 0.7; }
}

/* Multiplier bonus badge */
.hud-multiplier {
    font-size: 9px;
    letter-spacing: 0.08em;
    padding: 1px 5px;
    border: 1px solid rgba(0, 255, 255, 0.15);
    background: transparent;
    color: rgba(0, 255, 255, 0.3);
}

.hud-multiplier--active {
    color: #FFB300;
    border-color: rgba(255, 179, 0, 0.45);
    background: rgba(255, 179, 0, 0.06);
    animation: multi-glow 2.5s ease-in-out infinite;
}

.hud-multiplier--os {
    color: #FF4444;
    border-color: rgba(255, 68, 68, 0.55);
    background: rgba(255, 68, 68, 0.08);
    animation: multi-os 1.2s ease-in-out infinite;
}

@keyframes multi-glow {
    0%, 100% { box-shadow: 0 0 4px rgba(255, 179, 0, 0.15); }
    50%       { box-shadow: 0 0 8px rgba(255, 179, 0, 0.35); }
}

@keyframes multi-os {
    0%, 100% { box-shadow: 0 0 6px rgba(255, 68, 68, 0.2); }
    50%       { box-shadow: 0 0 12px rgba(255, 68, 68, 0.5); }
}

/* ── Bounty text (legacy, unused — kept for reference) ────────────────────── */
.val-bounty {
    color: #FFB300;
    animation: bounty-glow 2s ease-in-out infinite;
}

/* ── Open Season ──────────────────────────────────────────────────────────── */
.hud-item--os {
    color: #FF3333;
    font-size: 9px;
    letter-spacing: 0.1em;
    animation: crit-pulse 1.2s ease-in-out infinite;
}

.val-open-season {
    color: #FF3333;
    text-shadow: 0 0 8px rgba(255, 51, 51, 0.6);
    animation: os-glitch 4s steps(1) infinite;
}

/* ── Animations ───────────────────────────────────────────────────────────── */
@keyframes crit-pulse {
    0%, 100% { opacity: 1; }
    50%       { opacity: 0.4; }
}

@keyframes bounty-glow {
    0%, 100% { text-shadow: 0 0 4px rgba(255, 179, 0, 0.4); }
    50%       { text-shadow: 0 0 10px rgba(255, 179, 0, 0.8); }
}

@keyframes os-glitch {
    0%   { transform: translateX(0);    }
    3%   { transform: translateX(-2px); }
    5%   { transform: translateX(2px);  }
    7%   { transform: translateX(0);    }
    100% { transform: translateX(0);    }
}

/* ── Move-block flash toast ───────────────────────────────────────────────── */
.hud-flash {
    position: absolute;
    top: 36px;   /* sits just below the 32px HUD bar */
    left: 50%;
    transform: translateX(-50%);
    z-index: 21;
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 5px 18px;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.12em;
    white-space: nowrap;
    border: 1px solid;
    backdrop-filter: blur(4px);
    pointer-events: none;
}

.hud-flash--critical {
    color: #FF3333;
    border-color: rgba(255, 51, 51, 0.5);
    background: rgba(255, 51, 51, 0.08);
    animation: crit-pulse 0.8s ease-in-out infinite;
}

.hud-flash--warn {
    color: #FFB300;
    border-color: rgba(255, 179, 0, 0.45);
    background: rgba(255, 179, 0, 0.06);
}

.hud-flash-icon { font-size: 11px; }

.hud-flash-fade-enter-active { transition: opacity 0.15s ease; }
.hud-flash-fade-leave-active { transition: opacity 0.4s ease; }
.hud-flash-fade-enter-from,
.hud-flash-fade-leave-to    { opacity: 0; }
</style>
