<template>
    <div class="nir-page">
        <header class="nir-header">
            <span class="nir-pulse" />
            <div>
                <div class="nir-brand-name">N.U.L.L. — UNDERGROUND IRC RELAY GATEWAY</div>
                <div class="nir-brand-sub">Private Relay #null-underground // Signal Purists Only</div>
            </div>
        </header>

        <div v-if="loading" class="nir-loading">
            <span class="nir-loading-cursor">▌</span> connecting to relay...
        </div>

        <div v-else-if="locked" class="nir-locked">
            [ ACCESS DENIED ] Terminal credentials unknown or not yet unlocked.
            Resolve a Codex key at the Codex Archive to gain access.
        </div>

        <div v-else-if="errorMsg" class="nir-error">[ CONNECTION FAILED ] {{ errorMsg }}</div>

        <template v-else>
            <pre class="nir-body">{{ page?.body }}</pre>

            <div class="nir-login">
                <div class="nir-login-header">
                    [ {{ page?.solved ? 'CONNECTED TO #null-underground' : 'AUTHENTICATION REQUIRED' }} ]
                </div>
                <div class="nir-login-user">RELAY: {{ page?.login_username || 'unknown' }}</div>

                <template v-if="!page?.solved">
                    <div v-for="label in page?.credential_labels ?? []" :key="label" class="nir-login-row">
                        <label class="nir-login-label">{{ label.toUpperCase().replace(/_/g, ' ') }}</label>
                        <input
                            v-model="answerInputs[label]"
                            class="nir-login-input"
                            type="text"
                            @keydown.enter="onSolve"
                        />
                    </div>
                    <button class="nir-login-btn" :disabled="solving" @click="onSolve">
                        {{ solving ? '...' : 'CONNECT' }}
                    </button>
                    <div v-if="solveMsg" class="nir-solve-msg" :class="solveMsgClass">{{ solveMsg }}</div>

                    <div v-if="page?.leads?.length" class="nir-leads">
                        <div class="nir-leads-label">REFERENCED SOURCES —</div>
                        <button v-for="lead in page.leads" :key="lead.slug" class="nir-lead-btn" @click="goTo(lead.slug)">
                            → {{ lead.title }}
                        </button>
                    </div>
                </template>

                <template v-else>
                    <div class="nir-solved-banner">
                        Access granted.
                        <span v-if="lastReward">
                            +{{ lastReward.reward_creds || 0 }}₡
                            <span v-if="lastReward.reward_tech_points">/ +{{ lastReward.reward_tech_points }} tech</span>
                        </span>
                    </div>
                    <pre v-if="page?.unlocked_body" class="nir-unlocked-body">{{ page.unlocked_body }}</pre>
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

const SLUG = 'codex-null-irc-relay';
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
            solveMsg.value      = '[ MATCH — CONNECTED ]';
            solveMsgClass.value = 'nir-solve-msg--hit';
            lastReward.value     = result;
            page.value = { ...page.value, solved: true, unlocked_body: result.unlocked_body ?? page.value.unlocked_body };
            await fetchState();
        } else {
            solveMsg.value      = '[ ACCESS DENIED — NO MATCH ]';
            solveMsgClass.value = 'nir-solve-msg--miss';
        }
    } catch (e) {
        solveMsg.value      = `[ ERROR ] ${e?.response?.data?.message ?? e.message}`;
        solveMsgClass.value = 'nir-solve-msg--miss';
    } finally {
        solving.value = false;
    }
}
</script>

<style scoped>
.nir-page {
    font-family: 'JetBrains Mono', monospace;
    background: #050805;
    color: #6adc7a;
    min-height: 100%;
    display: flex;
    flex-direction: column;
}

.nir-header {
    display: flex;
    align-items: center;
    gap: 10px;
    padding: 14px 18px;
    border-bottom: 1px solid #1a3a20;
}
.nir-pulse {
    width: 8px; height: 8px; border-radius: 50%;
    background: #6adc7a; box-shadow: 0 0 8px #6adc7a; flex-shrink: 0;
    animation: nir-pulse 1.4s ease-in-out infinite;
}
@keyframes nir-pulse { 0%, 100% { opacity: 1; } 50% { opacity: 0.3; } }
.nir-brand-name { font-size: 12px; font-weight: 700; letter-spacing: 0.06em; color: #6adc7a; }
.nir-brand-sub  { font-size: 8px; color: #3a8a48; }

.nir-loading, .nir-locked, .nir-error { padding: 30px; text-align: center; font-size: 11px; color: #3a8a48; }
.nir-locked { color: #ff8844; }
.nir-error  { color: #ff6666; }
.nir-loading-cursor { animation: nir-blink 1s step-end infinite; }
@keyframes nir-blink { 50% { opacity: 0; } }

.nir-body {
    margin: 0;
    padding: 16px 18px 8px;
    font-size: 10.5px;
    line-height: 1.7;
    white-space: pre-wrap;
    color: #6adc7a;
}

.nir-login { margin: 8px 18px 24px; padding: 12px; border: 1px solid #1a3a20; background: rgba(100,220,120,0.03); }
.nir-login-header { font-size: 9px; color: #6adc7a; letter-spacing: 0.08em; margin-bottom: 4px; }
.nir-login-user   { font-size: 10px; color: #3a8a48; margin-bottom: 8px; }
.nir-login-row    { display: flex; flex-direction: column; gap: 2px; margin-bottom: 8px; }
.nir-login-label  { font-size: 8px; color: #3a8a48; letter-spacing: 0.08em; }
.nir-login-input {
    background: rgba(0,0,0,0.3); border: 1px solid #1a3a20; color: #6adc7a;
    font-family: inherit; font-size: 10px; padding: 5px 8px;
}
.nir-login-input:focus { outline: none; border-color: #6adc7a; }
.nir-login-btn {
    background: none; border: 1px solid #2a5a32; color: #6adc7a;
    font-family: inherit; font-size: 9px; padding: 5px 12px; cursor: pointer; letter-spacing: 0.1em;
}
.nir-login-btn:hover:not(:disabled) { border-color: #6adc7a; background: rgba(100,220,120,0.08); }
.nir-login-btn:disabled { opacity: 0.4; cursor: default; }

.nir-solve-msg { font-size: 9px; margin-top: 6px; letter-spacing: 0.05em; }
.nir-solve-msg--hit  { color: #00ff9d; }
.nir-solve-msg--miss { color: #ff6666; }

.nir-solved-banner { font-size: 10px; color: #00ff9d; letter-spacing: 0.05em; }
.nir-unlocked-body {
    margin-top: 10px; padding: 8px 10px; border-left: 2px solid #00ff9d;
    background: rgba(0,255,157,0.04); font-size: 10px; line-height: 1.6;
    color: #d6e8e0; white-space: pre-wrap;
}

.nir-leads { margin-top: 10px; display: flex; flex-direction: column; gap: 3px; }
.nir-leads-label { font-size: 8px; color: #2a5a32; letter-spacing: 0.1em; margin-bottom: 2px; }
.nir-lead-btn {
    background: none; border: none; color: #4a9a58; text-align: left;
    font-family: inherit; font-size: 10px; padding: 3px 0; cursor: pointer;
}
.nir-lead-btn:hover { color: #6adc7a; }
</style>
