<template>
    <div class="ap-page">

        <!-- Header -->
        <div class="ap-header">
            <div class="ap-header-left">
                <span class="ap-dot" />
                <span class="ap-title">MISSION_ARCHIVE</span>
                <span class="ap-sep">//</span>
                <span class="ap-sub">FULL SEQUENCE LOG</span>
            </div>
            <button class="ap-refresh" @click="fetchArchive" :disabled="loading">
                {{ loading ? 'SYNCING...' : '↺ SYNC' }}
            </button>
        </div>

        <div class="ap-rule" />

        <!-- States -->
        <div v-if="loading && !events.length" class="ap-boot">
            <span class="ap-boot-cursor">▌</span> LOADING ARCHIVE...
        </div>
        <div v-else-if="error" class="ap-error">[ ERROR ] {{ error }}</div>
        <div v-else-if="!events.length" class="ap-empty">
            [ NO EVENTS ON RECORD — COMPLETE YOUR FIRST MISSION OBJECTIVE ]
        </div>

        <!-- Event log -->
        <div v-else class="ap-log">
            <div
                v-for="event in events"
                :key="event.id"
                class="ap-entry"
                :class="`ap-entry--${event.event_type}`"
            >
                <!-- Stage complete -->
                <template v-if="event.event_type === 'stage_complete'">
                    <div class="ap-entry-header">
                        <span class="ap-icon ap-icon--complete">✓</span>
                        <span class="ap-entry-label">{{ event.payload.doc_name.toUpperCase() }}</span>
                        <span class="ap-entry-sep">//</span>
                        <span class="ap-entry-arc">{{ event.payload.arc_title }}</span>
                        <span class="ap-entry-time">{{ fmtTime(event.occurred_at) }}</span>
                    </div>
                    <div class="ap-entry-title">{{ event.payload.stage_title }}</div>
                    <div v-if="event.payload.rep_granted > 0" class="ap-entry-rep">
                        +{{ event.payload.rep_granted }} REP
                    </div>
                </template>

                <!-- Branch choice -->
                <template v-else-if="event.event_type === 'branch_choice'">
                    <div class="ap-entry-header">
                        <span class="ap-icon ap-icon--branch">⇀</span>
                        <span class="ap-entry-label">{{ event.payload.doc_name.toUpperCase() }}</span>
                        <span class="ap-entry-sep">//</span>
                        <span class="ap-entry-arc">{{ event.payload.arc_title }}</span>
                        <span class="ap-entry-time">{{ fmtTime(event.occurred_at) }}</span>
                    </div>
                    <div class="ap-entry-title">{{ event.payload.stage_title }}</div>
                    <div class="ap-entry-choice">
                        CHOICE — {{ event.payload.chosen_doc_name.toUpperCase() }}
                    </div>
                </template>

                <!-- Watcher signal -->
                <template v-else-if="event.event_type === 'watcher_signal'">
                    <div class="ap-entry-header ap-entry-header--watcher">
                        <span class="ap-icon ap-icon--watcher">◈</span>
                        <span class="ap-watcher-tag">[ENCRYPTED_SIGNAL // SOURCE: UNKNOWN]</span>
                        <span class="ap-entry-time">{{ fmtTime(event.occurred_at) }}</span>
                    </div>
                    <div class="ap-entry-signal">{{ event.payload.signal_text }}</div>
                </template>

                <!-- Arc unlocked -->
                <template v-else-if="event.event_type === 'arc_unlocked'">
                    <div class="ap-entry-header">
                        <span class="ap-icon ap-icon--unlock">▶</span>
                        <span class="ap-entry-label">{{ event.payload.doc_name.toUpperCase() }}</span>
                        <span class="ap-entry-time">{{ fmtTime(event.occurred_at) }}</span>
                    </div>
                    <div class="ap-entry-title">{{ event.payload.arc_title }} — UNLOCKED</div>
                </template>

                <!-- Referral -->
                <template v-else-if="event.event_type === 'referral'">
                    <div class="ap-entry-header">
                        <span class="ap-icon ap-icon--referral">→</span>
                        <span class="ap-entry-label">INTRODUCTION</span>
                        <span class="ap-entry-time">{{ fmtTime(event.occurred_at) }}</span>
                    </div>
                    <div class="ap-entry-title">{{ event.payload.referral_doc_name.toUpperCase() }}</div>
                    <div class="ap-entry-ref-text">{{ event.payload.referral_text }}</div>
                </template>
            </div>
        </div>

    </div>
</template>

<script setup>
import { onMounted } from 'vue';
import { useQuestArchive } from '../../../composables/useQuestArchive.js';

const { events, loading, error, fetchArchive } = useQuestArchive();

function fmtTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('en-US', {
        hour12: false, dateStyle: 'short', timeStyle: 'short',
    });
}

onMounted(fetchArchive);
</script>

<style scoped>
.ap-page {
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: #a0c4b8;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-height: 100%;
}

/* Header */
.ap-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    margin-bottom: 2px;
}
.ap-header-left { display: flex; align-items: center; gap: 6px; }
.ap-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: #00ff9d; box-shadow: 0 0 6px #00ff9d; flex-shrink: 0;
}
.ap-title  { color: #00ff9d; font-size: 12px; font-weight: 700; letter-spacing: 1px; }
.ap-sep    { color: #3a5a52; }
.ap-sub    { color: #4a7a6a; font-size: 10px; }
.ap-refresh {
    background: none; border: 1px solid #1e4a3a; color: #4a9a7a;
    font-family: inherit; font-size: 10px; padding: 2px 8px; cursor: pointer;
}
.ap-refresh:hover:not(:disabled) { border-color: #00ff9d; color: #00ff9d; }
.ap-refresh:disabled { opacity: 0.4; cursor: default; }

.ap-rule { border: none; border-top: 1px solid #1a3a2a; margin: 2px 0; }
.ap-boot  { color: #4a9a7a; padding: 20px 0; text-align: center; }
.ap-boot-cursor { animation: ap-blink 1s step-end infinite; }
.ap-error { color: #ff4444; padding: 8px 0; }
.ap-empty { color: #2a4a3a; font-size: 10px; padding: 20px 0; text-align: center; font-style: italic; }
@keyframes ap-blink { 50% { opacity: 0; } }

/* Event log */
.ap-log { display: flex; flex-direction: column; gap: 2px; }

.ap-entry {
    padding: 8px 10px;
    border-left: 2px solid #1a3a2a;
    margin-bottom: 2px;
}
.ap-entry--stage_complete { border-left-color: #1e5a3a; }
.ap-entry--branch_choice  { border-left-color: #5a3a1e; }
.ap-entry--watcher_signal { border-left-color: rgba(0,255,100,0.2); background: rgba(0,255,100,0.02); }
.ap-entry--arc_unlocked   { border-left-color: #1e3a5a; }
.ap-entry--referral       { border-left-color: #3a2a5a; }

.ap-entry-header {
    display: flex;
    align-items: center;
    gap: 6px;
    margin-bottom: 4px;
}
.ap-entry-header--watcher { opacity: 0.7; }
.ap-icon { font-size: 10px; flex-shrink: 0; }
.ap-icon--complete  { color: #2a7a4a; }
.ap-icon--branch    { color: #7a5a2a; }
.ap-icon--watcher   { color: rgba(0,255,100,0.4); }
.ap-icon--unlock    { color: #2a4a7a; }
.ap-icon--referral  { color: #5a3a7a; }

.ap-entry-label { font-size: 10px; font-weight: 700; color: #7ab8a0; letter-spacing: 0.1em; }
.ap-entry-sep   { color: #2a4a3a; }
.ap-entry-arc   { font-size: 9px; color: #3a6a52; flex: 1; }
.ap-entry-time  { font-size: 8px; color: #2a4a3a; margin-left: auto; flex-shrink: 0; }

.ap-entry-title {
    font-size: 10px;
    color: #6a9a82;
    padding-left: 16px;
}
.ap-entry-rep {
    font-size: 9px;
    color: #2a7a4a;
    padding-left: 16px;
    margin-top: 2px;
}
.ap-entry-choice {
    font-size: 9px;
    color: #9a7a2a;
    padding-left: 16px;
    margin-top: 2px;
    letter-spacing: 0.05em;
}

/* Watcher signal */
.ap-watcher-tag { font-size: 8px; color: rgba(0,255,100,0.25); letter-spacing: 0.08em; flex: 1; }
.ap-entry-signal {
    font-size: 10px;
    color: rgba(0,255,100,0.55);
    line-height: 1.6;
    white-space: pre-wrap;
    padding: 6px 0 2px 16px;
    border-left: 1px solid rgba(0,255,100,0.08);
    margin-left: 10px;
}

/* Referral */
.ap-entry-ref-text {
    font-size: 9px;
    color: #5a4a7a;
    padding-left: 16px;
    margin-top: 2px;
    font-style: italic;
}
</style>
