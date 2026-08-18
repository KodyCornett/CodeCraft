<template>
    <div class="av9-page">
        <div class="av9-hazard" />

        <header class="av9-header">
            <span class="av9-logo">⚡</span>
            <div>
                <div class="av9-brand-name">A.V.I.S.T.A. — SUBSTATION 09 OVERRIDE</div>
                <div class="av9-brand-sub">Monroe Street Bridge Underdeck // Restricted Technician Terminal</div>
            </div>
        </header>

        <div v-if="loading" class="av9-loading">
            <span class="av9-loading-cursor">▌</span> ESTABLISHING SECURE LINK...
        </div>

        <div v-else-if="locked" class="av9-locked">
            [ ACCESS DENIED ] Terminal credentials unknown or not yet unlocked.
            Resolve a Codex key at the Codex Archive to gain access.
        </div>

        <div v-else-if="errorMsg" class="av9-error">[ CONNECTION FAILED ] {{ errorMsg }}</div>

        <template v-else>
            <pre class="av9-body">{{ page?.body }}</pre>

            <div class="av9-login">
                <div class="av9-login-header">
                    [ {{ page?.solved ? 'AUTHENTICATION ACCEPTED — LEVEL-4 OVERRIDE ACTIVE' : 'GRID OPERATOR AUTHENTICATION REQUIRED' }} ]
                </div>
                <div class="av9-login-user">TERMINAL ID: {{ page?.login_username || 'unknown' }}</div>

                <template v-if="!page?.solved">
                    <div v-for="label in page?.credential_labels ?? []" :key="label" class="av9-login-row">
                        <label class="av9-login-label">{{ label.toUpperCase().replace(/_/g, ' ') }}</label>
                        <input
                            v-model="answerInputs[label]"
                            class="av9-login-input"
                            type="text"
                            @keydown.enter="onSolve"
                        />
                    </div>
                    <button class="av9-login-btn" :disabled="solving" @click="onSolve">
                        {{ solving ? '...' : 'AUTHENTICATE' }}
                    </button>
                    <div v-if="solveMsg" class="av9-solve-msg" :class="solveMsgClass">{{ solveMsg }}</div>

                    <div v-if="page?.leads?.length" class="av9-leads">
                        <div class="av9-leads-label">REFERENCED SOURCES —</div>
                        <button v-for="lead in page.leads" :key="lead.slug" class="av9-lead-btn" @click="goTo(lead.slug)">
                            → {{ lead.title }}
                        </button>
                    </div>
                </template>

                <template v-else>
                    <div class="av9-solved-banner">
                        Access granted.
                        <span v-if="lastReward">
                            +{{ lastReward.reward_creds || 0 }}₡
                            <span v-if="lastReward.reward_tech_points">/ +{{ lastReward.reward_tech_points }} tech</span>
                        </span>
                    </div>
                    <pre v-if="page?.unlocked_body" class="av9-unlocked-body">{{ page.unlocked_body }}</pre>
                </template>
            </div>
        </template>
    </div>
</template>

<script setup>
import { ref, onMounted, inject } from 'vue';
import { useCodex } from '@/composables/useCodex.js';
import { routeForSlug } from '@/composables/codexPageRoutes.js';

defineProps({ url: { type: String, default: '' } });

const SLUG = 'codex-avista-substation-09';
const { fetchPage, solvePage, fetchState } = useCodex();
const spliceNavigate = inject('spliceNavigate', () => {});

const loading  = ref(true);
const locked   = ref(false);
const errorMsg = ref(null);
const page     = ref(null);

const answerInputs   = ref({});
const solving         = ref(false);
const solveMsg        = ref('');
const solveMsgClass   = ref('');
const lastReward      = ref(null);

onMounted(async () => {
    try {
        page.value = await fetchPage(SLUG);
        for (const label of page.value?.credential_labels ?? []) answerInputs.value[label] = '';
    } catch (e) {
        // A 404 here almost always means "not yet unlocked" (see CodexService::getPageBySlug) — the
        // client can't tell that apart from a genuinely bad slug, so treat both as locked rather than
        // showing a raw error message.
        if (e?.response?.status === 404) {
            locked.value = true;
        } else {
            errorMsg.value = e?.response?.data?.message ?? e.message ?? 'Page unavailable';
        }
    } finally {
        loading.value = false;
    }
});

function goTo(slug) {
    const target = routeForSlug(slug);
    if (target) spliceNavigate(target);
}

async function onSolve() {
    if (!page.value) return;
    const labels = page.value.credential_labels ?? [];
    if (labels.some((label) => !answerInputs.value[label]?.trim())) return;

    solving.value = true;
    solveMsg.value = '';
    try {
        const result = await solvePage(page.value.id, { ...answerInputs.value });
        if (result.solved) {
            solveMsg.value      = '[ MATCH — ACCESS GRANTED ]';
            solveMsgClass.value = 'av9-solve-msg--hit';
            lastReward.value     = result;
            page.value = { ...page.value, solved: true, unlocked_body: result.unlocked_body ?? page.value.unlocked_body };
            await fetchState();
        } else {
            solveMsg.value      = '[ ACCESS DENIED — NO MATCH ]';
            solveMsgClass.value = 'av9-solve-msg--miss';
        }
    } catch (e) {
        solveMsg.value      = `[ ERROR ] ${e?.response?.data?.message ?? e.message}`;
        solveMsgClass.value = 'av9-solve-msg--miss';
    } finally {
        solving.value = false;
    }
}
</script>

<style scoped>
.av9-page {
    font-family: 'JetBrains Mono', monospace;
    background: #14100a;
    color: #e0c98a;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.av9-hazard {
    height: 6px;
    flex-shrink: 0;
    background: repeating-linear-gradient(135deg, #d4a72c 0 12px, #14100a 12px 24px);
}

.av9-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    border-bottom: 1px solid rgba(212,167,44,0.25);
    background: #1c1608;
}
.av9-logo { font-size: 20px; color: #d4a72c; }
.av9-brand-name { font-size: 12px; font-weight: 700; letter-spacing: 0.06em; color: #f0d68a; }
.av9-brand-sub  { font-size: 8px; color: #9a8450; }

.av9-loading, .av9-locked, .av9-error { padding: 30px; text-align: center; font-size: 11px; color: #9a8450; }
.av9-locked { color: #ff8844; }
.av9-error  { color: #ff6666; }
.av9-loading-cursor { animation: av9-blink 1s step-end infinite; }
@keyframes av9-blink { 50% { opacity: 0; } }

.av9-body {
    margin: 0;
    padding: 16px 18px 8px;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #cbb579;
}

.av9-login { margin: 8px 18px 24px; padding: 12px; border: 1px solid #4a3a10; background: rgba(212,167,44,0.03); }
.av9-login-header { font-size: 9px; color: #d4a72c; letter-spacing: 0.08em; margin-bottom: 4px; }
.av9-login-user   { font-size: 10px; color: #9a8450; margin-bottom: 8px; }
.av9-login-row    { display: flex; flex-direction: column; gap: 2px; margin-bottom: 8px; }
.av9-login-label  { font-size: 8px; color: #9a8450; letter-spacing: 0.08em; }
.av9-login-input {
    background: rgba(0,0,0,0.3); border: 1px solid #4a3a10; color: #d4a72c;
    font-family: inherit; font-size: 10px; padding: 5px 8px;
}
.av9-login-input:focus { outline: none; border-color: #d4a72c; }
.av9-login-btn {
    background: none; border: 1px solid #5a4a1e; color: #d4a72c;
    font-family: inherit; font-size: 9px; padding: 5px 12px; cursor: pointer; letter-spacing: 0.1em;
}
.av9-login-btn:hover:not(:disabled) { border-color: #d4a72c; background: rgba(212,167,44,0.08); }
.av9-login-btn:disabled { opacity: 0.4; cursor: default; }

.av9-solve-msg { font-size: 9px; margin-top: 6px; letter-spacing: 0.05em; }
.av9-solve-msg--hit  { color: #00ff9d; }
.av9-solve-msg--miss { color: #ff6666; }

.av9-solved-banner { font-size: 10px; color: #00ff9d; letter-spacing: 0.05em; }
.av9-unlocked-body {
    margin-top: 10px; padding: 8px 10px; border-left: 2px solid #00ff9d;
    background: rgba(0,255,157,0.04); font-size: 10px; line-height: 1.6;
    color: #d6e8e0; white-space: pre-wrap;
}

.av9-leads { margin-top: 10px; display: flex; flex-direction: column; gap: 3px; }
.av9-leads-label { font-size: 8px; color: #7a6840; letter-spacing: 0.1em; margin-bottom: 2px; }
.av9-lead-btn {
    background: none; border: none; color: #c8a860; text-align: left;
    font-family: inherit; font-size: 10px; padding: 3px 0; cursor: pointer;
}
.av9-lead-btn:hover { color: #d4a72c; }
</style>
