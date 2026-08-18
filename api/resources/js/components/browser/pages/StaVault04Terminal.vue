<template>
    <div class="sv4-page">
        <header class="sv4-header">
            <span class="sv4-icon">◫</span>
            <div>
                <div class="sv4-brand-name">S.T.A. — SERVICE TUNNEL VAULT 04</div>
                <div class="sv4-brand-sub">Monroe Cut Access // Maintenance Gateway</div>
            </div>
        </header>

        <div v-if="loading" class="sv4-loading">
            <span class="sv4-loading-cursor">▌</span> pinging maintenance gateway...
        </div>

        <div v-else-if="locked" class="sv4-locked">
            [ ACCESS DENIED ] Terminal credentials unknown or not yet unlocked.
            Resolve a Codex key at the Codex Archive to gain access.
        </div>

        <div v-else-if="errorMsg" class="sv4-error">[ CONNECTION FAILED ] {{ errorMsg }}</div>

        <template v-else>
            <pre class="sv4-body">{{ page?.body }}</pre>

            <div class="sv4-login">
                <div class="sv4-login-header">
                    [ {{ page?.solved ? 'HYDRAULIC SEAL RELEASED — ACCESS GRANTED' : 'MAINTENANCE OVERRIDE REQUIRED' }} ]
                </div>
                <div class="sv4-login-user">TERMINAL ID: {{ page?.login_username || 'unknown' }}</div>

                <template v-if="!page?.solved">
                    <div v-for="label in page?.credential_labels ?? []" :key="label" class="sv4-login-row">
                        <label class="sv4-login-label">{{ label.toUpperCase().replace(/_/g, ' ') }}</label>
                        <input
                            v-model="answerInputs[label]"
                            class="sv4-login-input"
                            type="text"
                            @keydown.enter="onSolve"
                        />
                    </div>
                    <button class="sv4-login-btn" :disabled="solving" @click="onSolve">
                        {{ solving ? '...' : 'OVERRIDE' }}
                    </button>
                    <div v-if="solveMsg" class="sv4-solve-msg" :class="solveMsgClass">{{ solveMsg }}</div>

                    <div v-if="page?.leads?.length" class="sv4-leads">
                        <div class="sv4-leads-label">REFERENCED SOURCES —</div>
                        <button v-for="lead in page.leads" :key="lead.slug" class="sv4-lead-btn" @click="goTo(lead.slug)">
                            → {{ lead.title }}
                        </button>
                    </div>
                </template>

                <template v-else>
                    <div class="sv4-solved-banner">
                        Access granted.
                        <span v-if="lastReward">
                            +{{ lastReward.reward_creds || 0 }}₡
                            <span v-if="lastReward.reward_tech_points">/ +{{ lastReward.reward_tech_points }} tech</span>
                        </span>
                    </div>
                    <pre v-if="page?.unlocked_body" class="sv4-unlocked-body">{{ page.unlocked_body }}</pre>
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

const SLUG = 'codex-sta-tunnel-vault-04';
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
            solveMsgClass.value = 'sv4-solve-msg--hit';
            lastReward.value     = result;
            page.value = { ...page.value, solved: true, unlocked_body: result.unlocked_body ?? page.value.unlocked_body };
            await fetchState();
        } else {
            solveMsg.value      = '[ ACCESS DENIED — NO MATCH ]';
            solveMsgClass.value = 'sv4-solve-msg--miss';
        }
    } catch (e) {
        solveMsg.value      = `[ ERROR ] ${e?.response?.data?.message ?? e.message}`;
        solveMsgClass.value = 'sv4-solve-msg--miss';
    } finally {
        solving.value = false;
    }
}
</script>

<style scoped>
.sv4-page {
    font-family: 'JetBrains Mono', monospace;
    background: #0a1420;
    color: #b8d0e0;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.sv4-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    background: #0e1c2e;
    border-bottom: 2px solid #2a7ac0;
}
.sv4-icon { font-size: 18px; color: #ff9838; }
.sv4-brand-name { font-size: 12px; font-weight: 700; letter-spacing: 0.06em; color: #6ab0e8; }
.sv4-brand-sub  { font-size: 8px; color: #5a7a92; }

.sv4-loading, .sv4-locked, .sv4-error { padding: 30px; text-align: center; font-size: 11px; color: #5a7a92; }
.sv4-locked { color: #ff8844; }
.sv4-error  { color: #ff6666; }
.sv4-loading-cursor { animation: sv4-blink 1s step-end infinite; }
@keyframes sv4-blink { 50% { opacity: 0; } }

.sv4-body {
    margin: 0;
    padding: 16px 18px 8px;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #9ab8cc;
}

.sv4-login { margin: 8px 18px 24px; padding: 12px; border: 1px solid #1a3a58; background: rgba(42,122,192,0.04); }
.sv4-login-header { font-size: 9px; color: #6ab0e8; letter-spacing: 0.08em; margin-bottom: 4px; }
.sv4-login-user   { font-size: 10px; color: #5a7a92; margin-bottom: 8px; }
.sv4-login-row    { display: flex; flex-direction: column; gap: 2px; margin-bottom: 8px; }
.sv4-login-label  { font-size: 8px; color: #5a7a92; letter-spacing: 0.08em; }
.sv4-login-input {
    background: rgba(0,0,0,0.3); border: 1px solid #1a3a58; color: #6ab0e8;
    font-family: inherit; font-size: 10px; padding: 5px 8px;
}
.sv4-login-input:focus { outline: none; border-color: #6ab0e8; }
.sv4-login-btn {
    background: none; border: 1px solid #2a5a82; color: #6ab0e8;
    font-family: inherit; font-size: 9px; padding: 5px 12px; cursor: pointer; letter-spacing: 0.1em;
}
.sv4-login-btn:hover:not(:disabled) { border-color: #6ab0e8; background: rgba(42,122,192,0.1); }
.sv4-login-btn:disabled { opacity: 0.4; cursor: default; }

.sv4-solve-msg { font-size: 9px; margin-top: 6px; letter-spacing: 0.05em; }
.sv4-solve-msg--hit  { color: #00ff9d; }
.sv4-solve-msg--miss { color: #ff6666; }

.sv4-solved-banner { font-size: 10px; color: #00ff9d; letter-spacing: 0.05em; }
.sv4-unlocked-body {
    margin-top: 10px; padding: 8px 10px; border-left: 2px solid #00ff9d;
    background: rgba(0,255,157,0.04); font-size: 10px; line-height: 1.6;
    color: #d6e8e0; white-space: pre-wrap;
}

.sv4-leads { margin-top: 10px; display: flex; flex-direction: column; gap: 3px; }
.sv4-leads-label { font-size: 8px; color: #3a5a72; letter-spacing: 0.1em; margin-bottom: 2px; }
.sv4-lead-btn {
    background: none; border: none; color: #7ab0d0; text-align: left;
    font-family: inherit; font-size: 10px; padding: 3px 0; cursor: pointer;
}
.sv4-lead-btn:hover { color: #6ab0e8; }
</style>
