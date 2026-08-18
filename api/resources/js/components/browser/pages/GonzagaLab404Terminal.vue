<template>
    <div class="g44-page">
        <header class="g44-header">
            <span class="g44-crest">◆</span>
            <div>
                <div class="g44-brand-name">G.O.N.Z.A.G.A. — SUB-LEVEL RESEARCH LAB 404</div>
                <div class="g44-brand-sub">Faculty &amp; Research Access Only</div>
            </div>
        </header>

        <div v-if="loading" class="g44-loading">
            <span class="g44-loading-cursor">▌</span> loading faculty gateway...
        </div>

        <div v-else-if="locked" class="g44-locked">
            [ ACCESS DENIED ] Terminal credentials unknown or not yet unlocked.
            Resolve a Codex key at the Codex Archive to gain access.
        </div>

        <div v-else-if="errorMsg" class="g44-error">[ CONNECTION FAILED ] {{ errorMsg }}</div>

        <template v-else>
            <pre class="g44-body">{{ page?.body }}</pre>

            <div class="g44-login">
                <div class="g44-login-header">
                    [ {{ page?.solved ? 'FACULTY AUTHENTICATION SUCCESSFUL' : 'FACULTY LOGIN REQUIRED' }} ]
                </div>
                <div class="g44-login-user">NETID: {{ page?.login_username || 'unknown' }}</div>

                <template v-if="!page?.solved">
                    <div v-for="label in page?.credential_labels ?? []" :key="label" class="g44-login-row">
                        <label class="g44-login-label">{{ label.toUpperCase().replace(/_/g, ' ') }}</label>
                        <input
                            v-model="answerInputs[label]"
                            class="g44-login-input"
                            type="text"
                            @keydown.enter="onSolve"
                        />
                    </div>
                    <button class="g44-login-btn" :disabled="solving" @click="onSolve">
                        {{ solving ? '...' : 'LOG IN' }}
                    </button>
                    <div v-if="solveMsg" class="g44-solve-msg" :class="solveMsgClass">{{ solveMsg }}</div>

                    <div v-if="page?.leads?.length" class="g44-leads">
                        <div class="g44-leads-label">REFERENCED SOURCES —</div>
                        <button v-for="lead in page.leads" :key="lead.slug" class="g44-lead-btn" @click="goTo(lead.slug)">
                            → {{ lead.title }}
                        </button>
                    </div>
                </template>

                <template v-else>
                    <div class="g44-solved-banner">
                        Access granted.
                        <span v-if="lastReward">
                            +{{ lastReward.reward_creds || 0 }}₡
                            <span v-if="lastReward.reward_tech_points">/ +{{ lastReward.reward_tech_points }} tech</span>
                        </span>
                    </div>
                    <pre v-if="page?.unlocked_body" class="g44-unlocked-body">{{ page.unlocked_body }}</pre>
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

const SLUG = 'codex-gonzaga-lab-404';
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
            solveMsgClass.value = 'g44-solve-msg--hit';
            lastReward.value     = result;
            page.value = { ...page.value, solved: true, unlocked_body: result.unlocked_body ?? page.value.unlocked_body };
            await fetchState();
        } else {
            solveMsg.value      = '[ ACCESS DENIED — NO MATCH ]';
            solveMsgClass.value = 'g44-solve-msg--miss';
        }
    } catch (e) {
        solveMsg.value      = `[ ERROR ] ${e?.response?.data?.message ?? e.message}`;
        solveMsgClass.value = 'g44-solve-msg--miss';
    } finally {
        solving.value = false;
    }
}
</script>

<style scoped>
.g44-page {
    font-family: 'JetBrains Mono', monospace;
    background: #1c0e10;
    color: #d8c49a;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.g44-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    background: #2a1216;
    border-bottom: 2px solid #b8933a;
}
.g44-crest { font-size: 18px; color: #b8933a; }
.g44-brand-name { font-size: 12px; font-weight: 700; letter-spacing: 0.06em; color: #d8c49a; }
.g44-brand-sub  { font-size: 8px; color: #8a6a4a; }

.g44-loading, .g44-locked, .g44-error { padding: 30px; text-align: center; font-size: 11px; color: #8a6a4a; }
.g44-locked { color: #ff8844; }
.g44-error  { color: #ff6666; }
.g44-loading-cursor { animation: g44-blink 1s step-end infinite; }
@keyframes g44-blink { 50% { opacity: 0; } }

.g44-body {
    margin: 0;
    padding: 16px 18px 8px;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #d8c49a;
}

.g44-login { margin: 8px 18px 24px; padding: 12px; border: 1px solid #4a2a18; background: rgba(184,147,58,0.03); }
.g44-login-header { font-size: 9px; color: #b8933a; letter-spacing: 0.08em; margin-bottom: 4px; }
.g44-login-user   { font-size: 10px; color: #8a6a4a; margin-bottom: 8px; }
.g44-login-row    { display: flex; flex-direction: column; gap: 2px; margin-bottom: 8px; }
.g44-login-label  { font-size: 8px; color: #8a6a4a; letter-spacing: 0.08em; }
.g44-login-input {
    background: rgba(0,0,0,0.3); border: 1px solid #4a2a18; color: #b8933a;
    font-family: inherit; font-size: 10px; padding: 5px 8px;
}
.g44-login-input:focus { outline: none; border-color: #b8933a; }
.g44-login-btn {
    background: none; border: 1px solid #6a4a24; color: #b8933a;
    font-family: inherit; font-size: 9px; padding: 5px 12px; cursor: pointer; letter-spacing: 0.1em;
}
.g44-login-btn:hover:not(:disabled) { border-color: #b8933a; background: rgba(184,147,58,0.08); }
.g44-login-btn:disabled { opacity: 0.4; cursor: default; }

.g44-solve-msg { font-size: 9px; margin-top: 6px; letter-spacing: 0.05em; }
.g44-solve-msg--hit  { color: #00ff9d; }
.g44-solve-msg--miss { color: #ff6666; }

.g44-solved-banner { font-size: 10px; color: #00ff9d; letter-spacing: 0.05em; }
.g44-unlocked-body {
    margin-top: 10px; padding: 8px 10px; border-left: 2px solid #00ff9d;
    background: rgba(0,255,157,0.04); font-size: 10px; line-height: 1.6;
    color: #d6e8e0; white-space: pre-wrap;
}

.g44-leads { margin-top: 10px; display: flex; flex-direction: column; gap: 3px; }
.g44-leads-label { font-size: 8px; color: #6a4a34; letter-spacing: 0.1em; margin-bottom: 2px; }
.g44-lead-btn {
    background: none; border: none; color: #c8a86a; text-align: left;
    font-family: inherit; font-size: 10px; padding: 3px 0; cursor: pointer;
}
.g44-lead-btn:hover { color: #b8933a; }
</style>
