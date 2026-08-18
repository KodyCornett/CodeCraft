<template>
    <div class="cft-page">
        <header class="cft-header">
            <div class="cft-brand-name">C.O.P.P.E.R.H.E.A.D. — FREIGHT LOGISTICS TERMINAL</div>
            <div class="cft-brand-sub">Encrypted Syndicate Node // Authorized Chop Shop Operators Only</div>
        </header>

        <div v-if="loading" class="cft-loading">
            <span class="cft-loading-cursor">▌</span> pinging syndicate relay...
        </div>

        <div v-else-if="locked" class="cft-locked">
            [ ACCESS DENIED ] Terminal credentials unknown or not yet unlocked.
            Resolve a Codex key at the Codex Archive to gain access.
        </div>

        <div v-else-if="errorMsg" class="cft-error">[ CONNECTION FAILED ] {{ errorMsg }}</div>

        <template v-else>
            <pre class="cft-body">{{ page?.body }}</pre>

            <div class="cft-login">
                <div class="cft-login-header">
                    [ {{ page?.solved ? 'MANIFEST UNLOCKED' : 'SYNDICATE PASSCODE REQUIRED' }} ]
                </div>
                <div class="cft-login-user">OPS ID: {{ page?.login_username || 'unknown' }}</div>

                <template v-if="!page?.solved">
                    <div v-for="label in page?.credential_labels ?? []" :key="label" class="cft-login-row">
                        <label class="cft-login-label">{{ label.toUpperCase().replace(/_/g, ' ') }}</label>
                        <input
                            v-model="answerInputs[label]"
                            class="cft-login-input"
                            type="text"
                            @keydown.enter="onSolve"
                        />
                    </div>
                    <button class="cft-login-btn" :disabled="solving" @click="onSolve">
                        {{ solving ? '...' : 'SUBMIT' }}
                    </button>
                    <div v-if="solveMsg" class="cft-solve-msg" :class="solveMsgClass">{{ solveMsg }}</div>

                    <div v-if="page?.leads?.length" class="cft-leads">
                        <div class="cft-leads-label">REFERENCED SOURCES —</div>
                        <button v-for="lead in page.leads" :key="lead.slug" class="cft-lead-btn" @click="goTo(lead.slug)">
                            → {{ lead.title }}
                        </button>
                    </div>
                </template>

                <template v-else>
                    <div class="cft-solved-banner">
                        Access granted.
                        <span v-if="lastReward">
                            +{{ lastReward.reward_creds || 0 }}₡
                            <span v-if="lastReward.reward_tech_points">/ +{{ lastReward.reward_tech_points }} tech</span>
                        </span>
                    </div>
                    <pre v-if="page?.unlocked_body" class="cft-unlocked-body">{{ page.unlocked_body }}</pre>
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

const SLUG = 'codex-copperhead-freight-manifest';
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
            solveMsgClass.value = 'cft-solve-msg--hit';
            lastReward.value     = result;
            page.value = { ...page.value, solved: true, unlocked_body: result.unlocked_body ?? page.value.unlocked_body };
            await fetchState();
        } else {
            solveMsg.value      = '[ ACCESS DENIED — NO MATCH ]';
            solveMsgClass.value = 'cft-solve-msg--miss';
        }
    } catch (e) {
        solveMsg.value      = `[ ERROR ] ${e?.response?.data?.message ?? e.message}`;
        solveMsgClass.value = 'cft-solve-msg--miss';
    } finally {
        solving.value = false;
    }
}
</script>

<style scoped>
.cft-page {
    font-family: 'JetBrains Mono', monospace;
    background: #1a1310;
    color: #d8b898;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.cft-header {
    padding: 14px 18px;
    border-bottom: 4px solid #b8541c;
}
.cft-brand-name { font-size: 12px; font-weight: 700; letter-spacing: 0.05em; color: #e8792c; }
.cft-brand-sub  { font-size: 8px; color: #9a7050; margin-top: 2px; }

.cft-loading, .cft-locked, .cft-error { padding: 30px; text-align: center; font-size: 11px; color: #9a7050; }
.cft-locked { color: #ff8844; }
.cft-error  { color: #ff6666; }
.cft-loading-cursor { animation: cft-blink 1s step-end infinite; }
@keyframes cft-blink { 50% { opacity: 0; } }

.cft-body {
    margin: 0;
    padding: 16px 18px 8px;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #d8b898;
}

.cft-login { margin: 8px 18px 24px; padding: 12px; border: 1px solid #4a3020; background: rgba(184,84,28,0.04); }
.cft-login-header { font-size: 9px; color: #e8792c; letter-spacing: 0.08em; margin-bottom: 4px; }
.cft-login-user   { font-size: 10px; color: #9a7050; margin-bottom: 8px; }
.cft-login-row    { display: flex; flex-direction: column; gap: 2px; margin-bottom: 8px; }
.cft-login-label  { font-size: 8px; color: #9a7050; letter-spacing: 0.08em; }
.cft-login-input {
    background: rgba(0,0,0,0.3); border: 1px solid #4a3020; color: #e8792c;
    font-family: inherit; font-size: 10px; padding: 5px 8px;
}
.cft-login-input:focus { outline: none; border-color: #e8792c; }
.cft-login-btn {
    background: none; border: 1px solid #6a4028; color: #e8792c;
    font-family: inherit; font-size: 9px; padding: 5px 12px; cursor: pointer; letter-spacing: 0.1em;
}
.cft-login-btn:hover:not(:disabled) { border-color: #e8792c; background: rgba(184,84,28,0.1); }
.cft-login-btn:disabled { opacity: 0.4; cursor: default; }

.cft-solve-msg { font-size: 9px; margin-top: 6px; letter-spacing: 0.05em; }
.cft-solve-msg--hit  { color: #00ff9d; }
.cft-solve-msg--miss { color: #ff6666; }

.cft-solved-banner { font-size: 10px; color: #00ff9d; letter-spacing: 0.05em; }
.cft-unlocked-body {
    margin-top: 10px; padding: 8px 10px; border-left: 2px solid #00ff9d;
    background: rgba(0,255,157,0.04); font-size: 10px; line-height: 1.6;
    color: #d6e8e0; white-space: pre-wrap;
}

.cft-leads { margin-top: 10px; display: flex; flex-direction: column; gap: 3px; }
.cft-leads-label { font-size: 8px; color: #7a5838; letter-spacing: 0.1em; margin-bottom: 2px; }
.cft-lead-btn {
    background: none; border: none; color: #c8905a; text-align: left;
    font-family: inherit; font-size: 10px; padding: 3px 0; cursor: pointer;
}
.cft-lead-btn:hover { color: #e8792c; }
</style>
