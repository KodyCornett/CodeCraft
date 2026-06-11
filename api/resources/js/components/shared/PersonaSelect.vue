<template>
    <div class="ps-overlay">
        <div class="ps-scanline" />

        <!-- Corner brackets -->
        <span class="ps-corner ps-corner--tl" />
        <span class="ps-corner ps-corner--tr" />
        <span class="ps-corner ps-corner--bl" />
        <span class="ps-corner ps-corner--br" />

        <div class="ps-container">

            <!-- Header -->
            <div class="ps-header">
                <div class="ps-logo">
                    <span class="ps-logo-mark">◈</span>
                    <span class="ps-logo-text">THE SPLICE FREQUENCY</span>
                </div>
                <div class="ps-rule" />
                <div class="ps-title">IDENTITY VERIFICATION REQUIRED</div>
                <div class="ps-sub">The network does not recognise you yet. Choose who you are in SPLICE.</div>
                <div class="ps-sub ps-sub--warn">This selection is permanent. It cannot be changed.</div>
            </div>

            <!-- Confirmed state -->
            <Transition name="ps-confirm-fade">
                <div v-if="confirmed" class="ps-confirmed">
                    <div class="ps-confirmed-label">IDENTITY LOCKED</div>
                    <div class="ps-confirmed-name">{{ selected.name }}</div>
                    <div class="ps-confirmed-desc">{{ selected.desc }}</div>
                    <div class="ps-confirmed-sub">Connecting to SPLICE...</div>
                </div>
            </Transition>

            <!-- Selection list -->
            <div v-if="!confirmed" class="ps-body">

                <!-- Selected preview -->
                <Transition name="ps-preview-fade">
                    <div v-if="selected" class="ps-preview">
                        <span class="ps-preview-label">SELECTED —</span>
                        <span class="ps-preview-name">{{ selected.name }}</span>
                        <span class="ps-preview-sep">//</span>
                        <span class="ps-preview-desc">{{ selected.desc }}</span>
                    </div>
                    <div v-else class="ps-preview ps-preview--empty">
                        Select a persona from the list below.
                    </div>
                </Transition>

                <!-- Persona list -->
                <div class="ps-list" ref="listEl">
                    <div
                        v-for="p in PERSONAS"
                        :key="p.name"
                        class="ps-item"
                        :class="{ 'ps-item--active': selected?.name === p.name }"
                        @click="selectPersona(p)"
                    >
                        <span class="ps-item-icon">{{ selected?.name === p.name ? '►' : '·' }}</span>
                        <span class="ps-item-name">{{ p.name.toUpperCase() }}</span>
                        <span class="ps-item-desc">{{ p.desc }}</span>
                    </div>
                </div>

                <!-- Confirm button -->
                <div class="ps-footer">
                    <div class="ps-warning">
                        ⚠ Once confirmed this identity is bound to your runner permanently.
                    </div>
                    <button
                        class="ps-confirm-btn"
                        :class="{ 'ps-confirm-btn--ready': selected && !busy }"
                        :disabled="!selected || busy"
                        @click="confirm"
                    >
                        <span v-if="busy">LOCKING IDENTITY...</span>
                        <span v-else-if="selected">[ CONFIRM: {{ selected.name.toUpperCase() }} ]</span>
                        <span v-else>[ SELECT A PERSONA ]</span>
                    </button>
                    <span v-if="error" class="ps-error">{{ error }}</span>
                </div>

            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue';
import axios from 'axios';
import { PERSONAS } from '@/constants/personas.js';

const emit = defineEmits(['done']);

const selected = ref(null);
const confirmed = ref(false);
const busy      = ref(false);
const error     = ref(null);

function selectPersona(p) {
    selected.value = p;
    error.value    = null;
}

async function confirm() {
    if (!selected.value || busy.value) return;
    busy.value  = true;
    error.value = null;

    try {
        await axios.post('/api/player/persona', { persona: selected.value.name });
        confirmed.value = true;
        // Brief pause so player reads the lock confirmation, then enter the game
        setTimeout(() => emit('done', selected.value), 2200);
    } catch (e) {
        error.value = e?.response?.data?.message ?? 'Connection failed. Try again.';
        busy.value  = false;
    }
}
</script>

<style scoped>
/* ── Full-screen overlay ────────────────────────────────────────────────────── */
.ps-overlay {
    position: fixed;
    inset: 0;
    z-index: 9999;
    background: #010508;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

.ps-scanline {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent, transparent 2px,
        rgba(0,255,157,0.008) 2px, rgba(0,255,157,0.008) 4px
    );
    pointer-events: none;
}

/* Corner brackets */
.ps-corner {
    position: absolute;
    width: 20px; height: 20px;
    border-color: rgba(0,255,157,0.15);
    border-style: solid;
}
.ps-corner--tl { top: 16px; left: 16px;   border-width: 1px 0 0 1px; }
.ps-corner--tr { top: 16px; right: 16px;  border-width: 1px 1px 0 0; }
.ps-corner--bl { bottom: 16px; left: 16px;  border-width: 0 0 1px 1px; }
.ps-corner--br { bottom: 16px; right: 16px; border-width: 0 1px 1px 0; }

/* ── Container ───────────────────────────────────────────────────────────────── */
.ps-container {
    position: relative;
    z-index: 1;
    width: min(640px, 96vw);
    max-height: 96vh;
    display: flex;
    flex-direction: column;
    border: 1px solid rgba(0,255,157,0.12);
    background: rgba(1,6,10,0.98);
    box-shadow: 0 0 80px rgba(0,255,157,0.04);
}

/* ── Header ──────────────────────────────────────────────────────────────────── */
.ps-header {
    padding: clamp(10px, 2vh, 20px) 24px clamp(8px, 1.5vh, 14px);
    border-bottom: 1px solid rgba(0,255,157,0.08);
    flex-shrink: 0;
}
.ps-logo {
    display: flex;
    align-items: center;
    gap: 8px;
    margin-bottom: clamp(6px, 1vh, 12px);
}
.ps-logo-mark {
    font-size: 14px;
    color: #00ff9d;
    text-shadow: 0 0 12px rgba(0,255,157,0.5);
    animation: ps-pulse 4s ease-in-out infinite;
}
@keyframes ps-pulse { 0%,100%{opacity:1} 50%{opacity:0.5} }
.ps-logo-text {
    font-size: 10px;
    color: #00ff9d;
    letter-spacing: 0.3em;
    text-shadow: 0 0 8px rgba(0,255,157,0.3);
}
.ps-rule {
    border: none;
    border-top: 1px solid rgba(0,255,157,0.08);
    margin-bottom: clamp(6px, 1vh, 10px);
}
.ps-title {
    font-size: 13px;
    color: #c0f0d8;
    letter-spacing: 0.15em;
    margin-bottom: 4px;
}
.ps-sub {
    font-size: 9px;
    color: rgba(0,255,157,0.35);
    letter-spacing: 0.08em;
    margin-bottom: 2px;
}
.ps-sub--warn {
    color: rgba(255,179,0,0.5);
}

/* ── Body ────────────────────────────────────────────────────────────────────── */
.ps-body {
    display: flex;
    flex-direction: column;
    flex: 1;
    overflow: hidden;
}

/* Preview strip */
.ps-preview {
    padding: 7px 24px;
    border-bottom: 1px solid rgba(0,255,157,0.06);
    font-size: 10px;
    color: #00ff9d;
    display: flex;
    gap: 8px;
    align-items: baseline;
    flex-wrap: wrap;
    min-height: 28px;
    flex-shrink: 0;
}
.ps-preview--empty { color: rgba(0,255,157,0.2); }
.ps-preview-label  { color: rgba(0,255,157,0.4); font-size: 9px; }
.ps-preview-name   { font-weight: 700; letter-spacing: 0.1em; }
.ps-preview-sep    { color: rgba(0,255,157,0.3); }
.ps-preview-desc   { color: rgba(0,255,157,0.6); font-size: 9px; flex: 1; }

/* Scrollable list */
.ps-list {
    flex: 1;
    overflow-y: auto;
    padding: 8px 0;
}
.ps-list::-webkit-scrollbar { width: 3px; }
.ps-list::-webkit-scrollbar-track { background: transparent; }
.ps-list::-webkit-scrollbar-thumb { background: rgba(0,255,157,0.15); }

.ps-item {
    display: flex;
    align-items: baseline;
    gap: 10px;
    padding: 5px 24px;
    cursor: pointer;
    transition: background 0.1s;
}
.ps-item:hover { background: rgba(0,255,157,0.04); }
.ps-item--active { background: rgba(0,255,157,0.07); }

.ps-item-icon { width: 10px; flex-shrink: 0; color: #00ff9d; font-size: 10px; }
.ps-item-name {
    font-size: 11px;
    font-weight: 700;
    color: #a0d8c0;
    letter-spacing: 0.1em;
    flex-shrink: 0;
    width: 90px;
}
.ps-item--active .ps-item-name { color: #00ff9d; }
.ps-item-desc {
    font-size: 9px;
    color: rgba(0,255,157,0.35);
    line-height: 1.5;
}
.ps-item--active .ps-item-desc { color: rgba(0,255,157,0.55); }

/* ── Footer ──────────────────────────────────────────────────────────────────── */
.ps-footer {
    padding: 10px 24px;
    border-top: 1px solid rgba(0,255,157,0.08);
    display: flex;
    flex-direction: column;
    gap: 6px;
    flex-shrink: 0;
}
.ps-warning {
    font-size: 8px;
    color: rgba(255,179,0,0.4);
    letter-spacing: 0.06em;
}
.ps-confirm-btn {
    width: 100%;
    padding: 9px 12px;
    background: transparent;
    border: 1px solid rgba(0,255,157,0.15);
    color: rgba(0,255,157,0.3);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.18em;
    cursor: not-allowed;
    transition: all 0.15s;
}
.ps-confirm-btn--ready {
    border-color: rgba(0,255,157,0.5);
    color: rgba(0,255,157,0.9);
    cursor: pointer;
}
.ps-confirm-btn--ready:hover {
    background: rgba(0,255,157,0.06);
    border-color: #00ff9d;
    color: #00ff9d;
    box-shadow: 0 0 20px rgba(0,255,157,0.08);
}
.ps-confirm-btn:disabled { opacity: 0.5; }
.ps-error {
    font-size: 9px;
    color: #ff4444;
    letter-spacing: 0.06em;
}

/* ── Confirmed state ─────────────────────────────────────────────────────────── */
.ps-confirmed {
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 14px;
    padding: 40px 28px;
}
.ps-confirmed-label {
    font-size: 9px;
    color: rgba(0,255,157,0.4);
    letter-spacing: 0.3em;
}
.ps-confirmed-name {
    font-size: 32px;
    color: #00ff9d;
    letter-spacing: 0.3em;
    text-shadow: 0 0 40px rgba(0,255,157,0.4);
    animation: ps-pulse 2s ease-in-out infinite;
}
.ps-confirmed-desc {
    font-size: 10px;
    color: rgba(0,255,157,0.5);
    text-align: center;
    max-width: 420px;
    line-height: 1.6;
}
.ps-confirmed-sub {
    font-size: 8px;
    color: rgba(0,255,157,0.25);
    letter-spacing: 0.18em;
    animation: ps-blink 1s steps(1) infinite;
}
@keyframes ps-blink { 0%,49%{opacity:1} 50%,100%{opacity:0} }

/* ── Transitions ─────────────────────────────────────────────────────────────── */
.ps-confirm-fade-enter-active { transition: opacity 0.4s ease; }
.ps-confirm-fade-enter-from   { opacity: 0; }
.ps-preview-fade-enter-active,
.ps-preview-fade-leave-active { transition: opacity 0.2s; }
.ps-preview-fade-enter-from,
.ps-preview-fade-leave-to     { opacity: 0; }
</style>
