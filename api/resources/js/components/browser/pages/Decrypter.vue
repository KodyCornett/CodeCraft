<template>
    <div class="dcx-page">

        <!-- Decryption sequence overlay -->
        <div v-if="decrypting" class="dcx-decrypt-overlay">
            <div class="dcx-decrypt-doc">
                <div class="dcx-decrypt-doc-header">
                    <span class="dcx-decrypt-doc-tag">[ DECRYPTING DOCUMENT ]</span>
                </div>
                <div
                    v-for="i in 4"
                    :key="i"
                    class="dcx-decrypt-redaction"
                >
                    <div
                        class="dcx-decrypt-redaction-bar"
                        :style="{ width: redactionWidth(i - 1) + '%' }"
                    />
                </div>
                <div
                    class="dcx-decrypt-result"
                    :class="decryptProgress >= 100
                        ? (decryptOutcome === 'empty' ? 'dcx-decrypt-result--empty' : 'dcx-decrypt-result--hit')
                        : ''"
                >
                    {{ decryptDisplayText || '············' }}
                </div>
                <div class="dcx-decrypt-bar-track">
                    <div class="dcx-decrypt-bar-fill" :style="{ width: decryptProgress + '%' }" />
                </div>
                <div class="dcx-decrypt-pct">{{ Math.floor(decryptProgress) }}%</div>
            </div>
        </div>

        <!-- Header -->
        <div class="dcx-header">
            <div class="dcx-header-left">
                <span class="dcx-dot" />
                <span class="dcx-title">CODEX ARCHIVE</span>
                <span class="dcx-sep">//</span>
                <span class="dcx-sub">KEY RESOLUTION &amp; CODEX HISTORY</span>
            </div>
            <button class="dcx-refresh" @click="refresh" :disabled="loading">
                {{ loading ? 'SYNCING...' : '↺ SYNC' }}
            </button>
        </div>

        <div class="dcx-rule" />

        <div v-if="loading && !history.length && !unresolvedKeys.length" class="dcx-boot">
            <span class="dcx-boot-cursor">▌</span> LOADING DECRYPTER...
        </div>
        <div v-else-if="error" class="dcx-error">[ ERROR ] {{ error }}</div>

        <template v-else>
            <!-- Unresolved keys queue -->
            <div class="dcx-section">
                <div class="dcx-section-title">UNRESOLVED KEYS</div>
                <div v-if="!unresolvedKeys.length" class="dcx-empty">
                    [ NO KEYS PENDING — WIN ARCHIVE EXTRACTION ANYWHERE WHILE A CODEX IS ACTIVE FOR A CHANCE AT ONE ]
                </div>
                <div v-else class="dcx-key-list">
                    <div v-for="key in unresolvedKeys" :key="key.id" class="dcx-key-row">
                        <span class="dcx-key-time">{{ fmtTime(key.created_at) }}</span>
                        <button
                            class="dcx-resolve-btn"
                            :disabled="resolvingId === key.id"
                            @click="onResolve(key.id)"
                        >
                            {{ resolvingId === key.id ? 'RESOLVING...' : 'RESOLVE' }}
                        </button>
                    </div>
                </div>
                <div v-if="lastResolveMsg" class="dcx-resolve-banner" :class="lastResolveClass">
                    {{ lastResolveMsg }}
                </div>
            </div>

            <div class="dcx-rule" />

            <!-- History / tracking -->
            <div class="dcx-section">
                <div class="dcx-section-title">HISTORY</div>
                <div v-if="!history.length" class="dcx-empty">
                    [ NOTHING DECRYPTED YET ]
                </div>
                <div v-else class="dcx-history-list">
                    <button
                        v-for="entry in history"
                        :key="entry.id"
                        class="dcx-history-row"
                        :class="{ 'dcx-history-row--active': entry.slug === activeSlug }"
                        @click="openPage(entry.slug)"
                    >
                        <span class="dcx-history-type" :class="`dcx-history-type--${entry.type}`">
                            {{ entry.type === 'codex' ? 'CODEX' : 'FLAVOR' }}
                        </span>
                        <span class="dcx-history-title">{{ entry.title }}</span>
                        <span
                            class="dcx-history-status"
                            :class="`dcx-history-status--${entry.status}`"
                        >
                            {{ entry.status === 'completed' ? '✓ COMPLETED' : '○ UNRESOLVED' }}
                        </span>
                    </button>
                </div>
            </div>

            <div class="dcx-rule" />

            <!-- Reader -->
            <div class="dcx-section dcx-reader">
                <div class="dcx-section-title">READER</div>

                <div v-if="pageError" class="dcx-error">[ ERROR ] {{ pageError }}</div>
                <div v-else-if="!activePage" class="dcx-empty">
                    [ SELECT AN ENTRY FROM HISTORY TO READ IT ]
                </div>
                <template v-else>
                    <div class="dcx-page-title">{{ activePage.title }}</div>
                    <div class="dcx-page-body">{{ activePage.body }}</div>

                    <!-- Codex login widget -->
                    <div v-if="activePage.type === 'codex'" class="dcx-login">
                        <div class="dcx-login-header">
                            [ RESTRICTED SECTION —
                            {{ activePage.solved ? 'ACCESS GRANTED' : 'CREDENTIALS REQUIRED' }} ]
                        </div>
                        <div class="dcx-login-user">
                            USER: {{ activePage.login_username || 'unknown' }}
                        </div>

                        <template v-if="!activePage.solved">
                            <div
                                v-for="label in activePage.credential_labels"
                                :key="label"
                                class="dcx-login-row"
                            >
                                <input
                                    v-model="answerInputs[label]"
                                    class="dcx-login-input"
                                    type="text"
                                    :placeholder="label.toUpperCase().replace(/_/g, ' ')"
                                    @keydown.enter="onSolve"
                                />
                            </div>
                            <button class="dcx-login-btn" :disabled="solving" @click="onSolve">
                                {{ solving ? '...' : 'LOG IN' }}
                            </button>
                            <div v-if="solveMsg" class="dcx-solve-msg" :class="solveMsgClass">
                                {{ solveMsg }}
                            </div>

                            <div v-if="activePage.leads?.length" class="dcx-leads">
                                <div class="dcx-leads-label">REFERENCED LEADS —</div>
                                <button
                                    v-for="lead in activePage.leads"
                                    :key="lead.slug"
                                    class="dcx-lead-btn"
                                    @click="openPage(lead.slug)"
                                >
                                    → {{ lead.title }}
                                </button>
                            </div>
                        </template>

                        <div v-else class="dcx-solved-banner">
                            Access granted.
                            <span v-if="lastReward">
                                +{{ lastReward.reward_creds || 0 }}₡
                                <span v-if="lastReward.reward_tech_points">
                                    / +{{ lastReward.reward_tech_points }} tech
                                </span>
                            </span>
                        </div>

                        <div v-if="activePage.solved && activePage.unlocked_body" class="dcx-unlocked-body">
                            {{ activePage.unlocked_body }}
                        </div>
                    </div>
                </template>
            </div>
        </template>

    </div>
</template>

<script setup>
import { ref, onMounted } from 'vue';
import { useCodex } from '../../../composables/useCodex.js';

const {
    unresolvedKeys, history, loading, error,
    fetchState, resolveKey, fetchPage, solvePage,
} = useCodex();

// Note: ArchiveExtraction.vue reports wins via useCodex().reportArchiveWin()
// (fire-and-forget — a miss or "no active thread" is a normal, silent
// no-op there). Still open: no "codex active" prompt at nodes yet, and
// Archive Extraction is still only launchable through the quest-minigame
// pipeline rather than freely at any node — separate follow-up work.

const resolvingId     = ref(null);
const lastResolveMsg   = ref('');
const lastResolveClass = ref('');

// ── Decryption sequence — redacted document un-scrambling as the bar fills ──
const decrypting          = ref(false);
const decryptProgress     = ref(0);   // 0-100
const decryptDisplayText  = ref('');
const decryptOutcome       = ref(null); // 'hit' | 'empty', only meaningful once progress hits 100

const DECRYPT_DURATION_MS = 2400;
const SCRAMBLE_CHARS       = '!@#$%^&*<>[]{}/\\|01#$%';

function scrambleReveal(target, progress) {
    const revealCount = Math.floor(target.length * progress);
    return target.split('').map((ch, i) => {
        if (ch === ' ') return ' ';
        if (i < revealCount) return ch;
        return SCRAMBLE_CHARS[Math.floor(Math.random() * SCRAMBLE_CHARS.length)];
    }).join('');
}

// Staggered "un-redaction" — each bar starts shrinking a bit later than the
// one before it, so the reveal cascades down the document instead of every
// line clearing in lockstep.
function redactionWidth(lineIndex) {
    const start = lineIndex * 12;
    const span  = 45;
    const p     = Math.max(0, Math.min(1, (decryptProgress.value - start) / span));
    return (1 - p) * (85 - lineIndex * 8);
}

function runDecryptAnimation(targetText) {
    return new Promise((resolve) => {
        const start = performance.now();
        function tick(now) {
            const progress = Math.min(1, (now - start) / DECRYPT_DURATION_MS);
            decryptProgress.value    = progress * 100;
            decryptDisplayText.value = progress >= 1 ? targetText : scrambleReveal(targetText, progress);
            if (progress < 1) {
                requestAnimationFrame(tick);
            } else {
                resolve();
            }
        }
        requestAnimationFrame(tick);
    });
}

const activeSlug  = ref(null);
const activePage  = ref(null);
const pageError    = ref(null);

const answerInputs   = ref({}); // keyed by credential label
const solving         = ref(false);
const solveMsg        = ref('');
const solveMsgClass   = ref('');
const lastReward      = ref(null);

function fmtTime(iso) {
    if (!iso) return '';
    return new Date(iso).toLocaleString('en-US', { hour12: false, dateStyle: 'short', timeStyle: 'short' });
}

async function refresh() {
    await fetchState();
}

async function onResolve(keyId) {
    resolvingId.value   = keyId;
    lastResolveMsg.value = '';
    try {
        const result = await resolveKey(keyId);

        const targetText = result.outcome === 'nothing_left'
            ? 'NOTHING LEFT TO FIND'
            : result.page.title.toUpperCase();

        decrypting.value         = true;
        decryptOutcome.value     = result.outcome === 'nothing_left' ? 'empty' : 'hit';
        decryptProgress.value    = 0;
        decryptDisplayText.value = '';

        await runDecryptAnimation(targetText);
        await new Promise((r) => setTimeout(r, 700)); // hold on the revealed text briefly

        decrypting.value = false;

        if (result.outcome === 'nothing_left') {
            lastResolveMsg.value   = '[ KEY RESOLVED — NOTHING LEFT TO FIND ]';
            lastResolveClass.value = 'dcx-resolve-banner--empty';
        } else {
            const kind = result.page.type === 'codex' ? 'CODEX' : 'DOCUMENT';
            lastResolveMsg.value   = `[ KEY RESOLVED — ${kind} "${result.page.title}" UNLOCKED ]`;
            lastResolveClass.value = 'dcx-resolve-banner--hit';
        }
        await fetchState();
    } catch (e) {
        decrypting.value       = false;
        lastResolveMsg.value   = `[ ERROR ] ${e?.response?.data?.message ?? e.message}`;
        lastResolveClass.value = 'dcx-resolve-banner--empty';
    } finally {
        resolvingId.value = null;
    }
}

async function openPage(slug) {
    activeSlug.value  = slug;
    pageError.value    = null;
    answerInputs.value = {};
    solveMsg.value      = '';
    lastReward.value    = null;
    try {
        activePage.value = await fetchPage(slug);
        for (const label of activePage.value?.credential_labels ?? []) {
            answerInputs.value[label] = '';
        }
    } catch (e) {
        activePage.value = null;
        pageError.value   = e?.response?.data?.message ?? e.message ?? 'Page unavailable';
    }
}

async function onSolve() {
    if (!activePage.value) return;
    const labels = activePage.value.credential_labels ?? [];
    if (labels.some((label) => !answerInputs.value[label]?.trim())) return;

    solving.value = true;
    solveMsg.value = '';
    try {
        const result = await solvePage(activePage.value.id, { ...answerInputs.value });
        if (result.solved) {
            solveMsg.value      = '[ MATCH — ACCESS GRANTED ]';
            solveMsgClass.value = 'dcx-solve-msg--hit';
            lastReward.value     = result;
            activePage.value     = {
                ...activePage.value,
                solved: true,
                unlocked_body: result.unlocked_body ?? activePage.value.unlocked_body,
            };
            await fetchState();
        } else {
            solveMsg.value      = '[ ACCESS DENIED — NO MATCH ]';
            solveMsgClass.value = 'dcx-solve-msg--miss';
        }
    } catch (e) {
        solveMsg.value      = `[ ERROR ] ${e?.response?.data?.message ?? e.message}`;
        solveMsgClass.value = 'dcx-solve-msg--miss';
    } finally {
        solving.value = false;
    }
}

onMounted(refresh);
</script>

<style scoped>
.dcx-page {
    position: relative;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    color: #a0c4b8;
    padding: 10px 12px;
    display: flex;
    flex-direction: column;
    gap: 4px;
    min-height: 100%;
}

/* Decryption sequence overlay */
.dcx-decrypt-overlay {
    position: absolute;
    inset: 0;
    z-index: 20;
    background: rgba(0, 6, 4, 0.92);
    display: flex;
    align-items: center;
    justify-content: center;
    padding: 20px;
}
.dcx-decrypt-doc {
    width: 100%;
    max-width: 340px;
    background: rgba(0, 20, 14, 0.5);
    border: 1px solid rgba(0, 255, 157, 0.25);
    box-shadow: 0 0 24px rgba(0, 255, 157, 0.08);
    padding: 16px;
    display: flex;
    flex-direction: column;
    gap: 10px;
}
.dcx-decrypt-doc-header { text-align: center; margin-bottom: 4px; }
.dcx-decrypt-doc-tag {
    font-size: 9px;
    color: #00ff9d;
    letter-spacing: 0.2em;
    animation: dcx-decrypt-flicker 1.6s step-end infinite;
}

.dcx-decrypt-redaction { height: 8px; display: flex; }
.dcx-decrypt-redaction-bar {
    height: 100%;
    background: #0a0a0a;
    border: 1px solid rgba(0, 255, 157, 0.15);
    transition: width 0.08s linear;
}

.dcx-decrypt-result {
    margin-top: 4px;
    min-height: 16px;
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.12em;
    text-align: center;
    color: #4a9a7a;
    text-shadow: 0 0 6px rgba(0, 255, 157, 0.3);
}
.dcx-decrypt-result--hit   { color: #00ff9d; text-shadow: 0 0 10px rgba(0, 255, 157, 0.6); }
.dcx-decrypt-result--empty { color: #ff6666; text-shadow: 0 0 10px rgba(255, 100, 100, 0.4); }

.dcx-decrypt-bar-track {
    height: 4px;
    background: rgba(0, 255, 157, 0.08);
    border: 1px solid rgba(0, 255, 157, 0.15);
}
.dcx-decrypt-bar-fill {
    height: 100%;
    background: linear-gradient(90deg, #1e5a3a, #00ff9d);
    transition: width 0.08s linear;
}
.dcx-decrypt-pct {
    align-self: flex-end;
    font-size: 9px;
    color: #4a9a7a;
    letter-spacing: 0.1em;
}

@keyframes dcx-decrypt-flicker { 0%, 90% { opacity: 1; } 92% { opacity: 0.4; } 94% { opacity: 1; } }

.dcx-header { display: flex; align-items: center; justify-content: space-between; margin-bottom: 2px; }
.dcx-header-left { display: flex; align-items: center; gap: 6px; }
.dcx-dot {
    width: 7px; height: 7px; border-radius: 50%;
    background: #00ff9d; box-shadow: 0 0 6px #00ff9d; flex-shrink: 0;
}
.dcx-title { color: #00ff9d; font-size: 12px; font-weight: 700; letter-spacing: 1px; }
.dcx-sep   { color: #3a5a52; }
.dcx-sub   { color: #4a7a6a; font-size: 10px; }
.dcx-refresh {
    background: none; border: 1px solid #1e4a3a; color: #4a9a7a;
    font-family: inherit; font-size: 10px; padding: 2px 8px; cursor: pointer;
}
.dcx-refresh:hover:not(:disabled) { border-color: #00ff9d; color: #00ff9d; }
.dcx-refresh:disabled { opacity: 0.4; cursor: default; }

.dcx-rule { border: none; border-top: 1px solid #1a3a2a; margin: 4px 0; }
.dcx-boot  { color: #4a9a7a; padding: 20px 0; text-align: center; }
.dcx-boot-cursor { animation: dcx-blink 1s step-end infinite; }
.dcx-error { color: #ff4444; padding: 8px 0; }
.dcx-empty { color: #2a4a3a; font-size: 10px; padding: 12px 0; text-align: center; font-style: italic; }
@keyframes dcx-blink { 50% { opacity: 0; } }

.dcx-section { display: flex; flex-direction: column; gap: 6px; }
.dcx-section-title { color: #7ab8a0; font-size: 10px; font-weight: 700; letter-spacing: 0.15em; }

/* Key queue */
.dcx-key-list { display: flex; flex-direction: column; gap: 3px; }
.dcx-key-row {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 8px; border-left: 2px solid #1a3a2a;
}
.dcx-key-source { font-size: 10px; color: #6a9a82; flex: 1; }
.dcx-key-time   { font-size: 8px; color: #2a4a3a; }
.dcx-resolve-btn {
    background: none; border: 1px solid #1e4a3a; color: #4a9a7a;
    font-family: inherit; font-size: 9px; padding: 3px 10px; cursor: pointer; letter-spacing: 0.1em;
}
.dcx-resolve-btn:hover:not(:disabled) { border-color: #00ff9d; color: #00ff9d; }
.dcx-resolve-btn:disabled { opacity: 0.4; cursor: default; }

.dcx-resolve-banner { font-size: 10px; padding: 6px 8px; letter-spacing: 0.05em; }
.dcx-resolve-banner--hit   { color: #00ff9d; }
.dcx-resolve-banner--empty { color: #6a7a72; }

/* History */
.dcx-history-list { display: flex; flex-direction: column; gap: 2px; }
.dcx-history-row {
    display: flex; align-items: center; gap: 8px;
    padding: 6px 8px; border-left: 2px solid #1a3a2a;
    background: none; cursor: pointer; text-align: left;
    font-family: inherit; width: 100%;
}
.dcx-history-row:hover { background: rgba(0,255,100,0.03); }
.dcx-history-row--active { border-left-color: #00ff9d; background: rgba(0,255,100,0.05); }

.dcx-history-type {
    font-size: 8px; font-weight: 700; letter-spacing: 0.1em;
    padding: 1px 5px; flex-shrink: 0;
}
.dcx-history-type--flavor { color: #4a7a6a; border: 1px solid #1e4a3a; }
.dcx-history-type--codex  { color: #d4a72c; border: 1px solid #5a4a1e; }

.dcx-history-title  { font-size: 10px; color: #a0c4b8; flex: 1; }
.dcx-history-status { font-size: 8px; letter-spacing: 0.05em; flex-shrink: 0; }
.dcx-history-status--completed  { color: #00ff9d; }
.dcx-history-status--unresolved { color: #d4a72c; }

/* Reader */
.dcx-reader { padding-bottom: 10px; }
.dcx-page-title { font-size: 12px; color: #00ff9d; font-weight: 700; margin-bottom: 4px; }
.dcx-page-body {
    font-size: 10px; color: #8ab0a0; line-height: 1.7; white-space: pre-wrap;
    padding: 8px 10px; border-left: 1px solid rgba(0,255,100,0.08); margin-bottom: 8px;
}

.dcx-login { border: 1px solid #3a2a10; background: rgba(212,167,44,0.03); padding: 10px; }
.dcx-login-header { font-size: 9px; color: #d4a72c; letter-spacing: 0.1em; margin-bottom: 4px; }
.dcx-login-user   { font-size: 10px; color: #8a9a92; margin-bottom: 8px; }
.dcx-login-row    { display: flex; gap: 6px; margin-bottom: 6px; }
.dcx-login-input {
    flex: 1; background: rgba(0,0,0,0.3); border: 1px solid #3a2a10; color: #d4a72c;
    font-family: inherit; font-size: 10px; padding: 5px 8px;
}
.dcx-login-input:focus { outline: none; border-color: #d4a72c; }
.dcx-login-btn {
    background: none; border: 1px solid #5a4a1e; color: #d4a72c;
    font-family: inherit; font-size: 9px; padding: 5px 12px; cursor: pointer; letter-spacing: 0.1em;
}
.dcx-login-btn:hover:not(:disabled) { border-color: #d4a72c; background: rgba(212,167,44,0.08); }
.dcx-login-btn:disabled { opacity: 0.4; cursor: default; }

.dcx-solve-msg { font-size: 9px; margin-top: 6px; letter-spacing: 0.05em; }
.dcx-solve-msg--hit  { color: #00ff9d; }
.dcx-solve-msg--miss { color: #ff6666; }

.dcx-solved-banner { font-size: 10px; color: #00ff9d; letter-spacing: 0.05em; }

.dcx-unlocked-body {
    margin-top: 10px; padding: 8px 10px; border-left: 2px solid #00ff9d;
    background: rgba(0,255,157,0.04); font-size: 10px; line-height: 1.6;
    color: #d6e8e0; white-space: pre-wrap;
}

.dcx-leads { margin-top: 10px; display: flex; flex-direction: column; gap: 3px; }
.dcx-leads-label { font-size: 8px; color: #6a7a72; letter-spacing: 0.1em; margin-bottom: 2px; }
.dcx-lead-btn {
    background: none; border: none; color: #7ab8a0; text-align: left;
    font-family: inherit; font-size: 10px; padding: 3px 0; cursor: pointer;
}
.dcx-lead-btn:hover { color: #00ff9d; }
</style>
