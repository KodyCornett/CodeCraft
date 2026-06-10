<template>
    <div class="login-screen">

        <!-- Scanline overlay -->
        <div class="login-scanline" />

        <!-- Corner decorations -->
        <span class="corner corner--tl" />
        <span class="corner corner--tr" />
        <span class="corner corner--bl" />
        <span class="corner corner--br" />

        <div class="login-panel">

            <!-- Header -->
            <div class="lp-header">
                <div class="lp-logo">
                    <span class="lp-logo-mark">◈</span>
                    <span class="lp-logo-text">SPLICE</span>
                </div>
                <div class="lp-subtitle">NETWORK ACCESS TERMINAL</div>
                <div class="lp-version">v2.4.1 // SECURE</div>
            </div>

            <!-- Status strip -->
            <div class="lp-status">
                <span class="lp-status-dot" />
                <span class="lp-status-text">AWAITING AUTHENTICATION</span>
            </div>

            <!-- Form -->
            <form class="lp-form" @submit.prevent="submit">

                <div class="lp-field">
                    <label class="lp-label" for="email">HANDLE — EMAIL</label>
                    <input
                        id="email"
                        v-model="form.email"
                        class="lp-input"
                        :class="{ 'lp-input--err': errors.email }"
                        type="email"
                        autocomplete="email"
                        placeholder="runner@splice.net"
                        autofocus
                    />
                    <span v-if="errors.email" class="lp-error">{{ errors.email }}</span>
                </div>

                <div class="lp-field">
                    <label class="lp-label" for="password">ACCESS CODE</label>
                    <input
                        id="password"
                        v-model="form.password"
                        class="lp-input"
                        :class="{ 'lp-input--err': errors.password }"
                        type="password"
                        autocomplete="current-password"
                        placeholder="••••••••••••"
                    />
                    <span v-if="errors.password" class="lp-error">{{ errors.password }}</span>
                </div>

                <button class="lp-submit" type="submit" :disabled="busy">
                    <span v-if="busy" class="lp-submit-busy">
                        <span class="lp-dots"><span></span><span></span><span></span></span>
                        AUTHENTICATING
                    </span>
                    <span v-else>[ AUTHENTICATE ]</span>
                </button>

            </form>

            <!-- Footer -->
            <div class="lp-footer">
                <span class="lp-footer-line">New runner?
                    <a href="/register" class="lp-link">[ REGISTER ]</a>
                </span>
            </div>

        </div>

        <!-- Live clock -->
        <div class="login-clock">{{ time }}</div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';
import { useForm, usePage }            from '@inertiajs/vue3';

const page   = usePage();
const errors = ref(page.props.errors ?? {});

const form = useForm({
    email:    '',
    password: '',
});

const busy = ref(false);

function submit() {
    busy.value = true;
    errors.value = {};
    form.post('/login', {
        onError:  (e) => { errors.value = e; },
        onFinish: () => { busy.value = false; },
    });
}

// Live clock
const time = ref('');
let _tick;
onMounted(() => {
    const update = () => {
        time.value = new Date().toLocaleTimeString('en-US', { hour12: false });
    };
    update();
    _tick = setInterval(update, 1000);
});
onUnmounted(() => clearInterval(_tick));
</script>

<style scoped>
/* ── Full-screen container ─────────────────────────────────────────────────── */
.login-screen {
    position: fixed;
    inset: 0;
    background: #03060a;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

/* Scanline */
.login-scanline {
    position: absolute;
    inset: 0;
    background: repeating-linear-gradient(
        0deg,
        transparent,
        transparent 2px,
        rgba(0,255,255,0.012) 2px,
        rgba(0,255,255,0.012) 4px
    );
    pointer-events: none;
    z-index: 0;
}

/* Corner brackets */
.corner {
    position: absolute;
    width: 24px;
    height: 24px;
    border-color: rgba(0,255,255,0.18);
    border-style: solid;
}
.corner--tl { top: 16px; left: 16px;  border-width: 1px 0 0 1px; }
.corner--tr { top: 16px; right: 16px; border-width: 1px 1px 0 0; }
.corner--bl { bottom: 16px; left: 16px;  border-width: 0 0 1px 1px; }
.corner--br { bottom: 16px; right: 16px; border-width: 0 1px 1px 0; }

/* Live clock */
.login-clock {
    position: absolute;
    bottom: 20px;
    right: 24px;
    font-size: 9px;
    color: rgba(0,255,255,0.2);
    letter-spacing: 0.1em;
}

/* ── Panel ─────────────────────────────────────────────────────────────────── */
.login-panel {
    position: relative;
    z-index: 1;
    width: 380px;
    background: rgba(4,8,14,0.97);
    border: 1px solid rgba(0,255,255,0.14);
    display: flex;
    flex-direction: column;
    gap: 0;
    box-shadow: 0 0 60px rgba(0,255,255,0.04), inset 0 0 40px rgba(0,0,0,0.4);
}

/* ── Header ─────────────────────────────────────────────────────────────────── */
.lp-header {
    padding: 28px 28px 20px;
    border-bottom: 1px solid rgba(0,255,255,0.08);
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.lp-logo {
    display: flex;
    align-items: center;
    gap: 10px;
}
.lp-logo-mark {
    font-size: 18px;
    color: #00FFFF;
    text-shadow: 0 0 16px rgba(0,255,255,0.6);
    animation: logo-pulse 4s ease-in-out infinite;
}
@keyframes logo-pulse { 0%,100%{opacity:1} 50%{opacity:0.6} }
.lp-logo-text {
    font-size: 20px;
    color: #00FFFF;
    letter-spacing: 0.3em;
    text-shadow: 0 0 12px rgba(0,255,255,0.3);
}
.lp-subtitle {
    font-size: 8px;
    color: rgba(0,255,255,0.3);
    letter-spacing: 0.22em;
}
.lp-version {
    font-size: 7px;
    color: rgba(0,255,255,0.15);
    letter-spacing: 0.14em;
}

/* ── Status strip ────────────────────────────────────────────────────────────── */
.lp-status {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 8px 28px;
    background: rgba(0,255,255,0.02);
    border-bottom: 1px solid rgba(0,255,255,0.06);
}
.lp-status-dot {
    width: 5px; height: 5px; border-radius: 50%;
    background: #FFB300;
    box-shadow: 0 0 6px rgba(255,179,0,0.6);
    animation: status-blink 1.4s steps(1) infinite;
    flex-shrink: 0;
}
@keyframes status-blink { 0%,49%{opacity:1} 50%,100%{opacity:0.2} }
.lp-status-text {
    font-size: 7px;
    color: rgba(255,179,0,0.5);
    letter-spacing: 0.18em;
}

/* ── Form ────────────────────────────────────────────────────────────────────── */
.lp-form {
    padding: 24px 28px;
    display: flex;
    flex-direction: column;
    gap: 18px;
}

.lp-field {
    display: flex;
    flex-direction: column;
    gap: 6px;
}
.lp-label {
    font-size: 7px;
    color: rgba(0,255,255,0.3);
    letter-spacing: 0.18em;
}
.lp-input {
    background: rgba(0,255,255,0.03);
    border: 1px solid rgba(0,255,255,0.14);
    color: #00FFFF;
    font-family: 'JetBrains Mono', monospace;
    font-size: 11px;
    letter-spacing: 0.06em;
    padding: 10px 12px;
    outline: none;
    transition: border-color 0.15s, box-shadow 0.15s;
    width: 100%;
    box-sizing: border-box;
}
.lp-input::placeholder { color: rgba(0,255,255,0.18); }
.lp-input:focus {
    border-color: rgba(0,255,255,0.45);
    box-shadow: 0 0 12px rgba(0,255,255,0.06);
}
.lp-input--err {
    border-color: rgba(255,51,51,0.5);
}
.lp-input--err:focus {
    border-color: rgba(255,51,51,0.8);
    box-shadow: 0 0 12px rgba(255,51,51,0.08);
}
.lp-error {
    font-size: 7px;
    color: #FF3333;
    letter-spacing: 0.08em;
}

/* Submit button */
.lp-submit {
    margin-top: 4px;
    width: 100%;
    padding: 12px;
    background: transparent;
    border: 1px solid rgba(0,255,255,0.35);
    color: rgba(0,255,255,0.8);
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.22em;
    cursor: pointer;
    transition: all 0.15s;
}
.lp-submit:hover:not(:disabled) {
    background: rgba(0,255,255,0.06);
    border-color: rgba(0,255,255,0.7);
    color: #00FFFF;
    box-shadow: 0 0 16px rgba(0,255,255,0.08);
}
.lp-submit:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

/* Busy state dots */
.lp-submit-busy {
    display: flex;
    align-items: center;
    justify-content: center;
    gap: 10px;
}
.lp-dots {
    display: flex;
    gap: 4px;
}
.lp-dots span {
    width: 4px; height: 4px; border-radius: 50%;
    background: currentColor;
    animation: dot-bounce 1.1s ease-in-out infinite;
}
.lp-dots span:nth-child(2) { animation-delay: 0.18s; }
.lp-dots span:nth-child(3) { animation-delay: 0.36s; }
@keyframes dot-bounce { 0%,100%{transform:translateY(0);opacity:.4} 50%{transform:translateY(-4px);opacity:1} }

/* ── Footer ───────────────────────────────────────────────────────────────── */
.lp-footer {
    padding: 12px 28px;
    border-top: 1px solid rgba(0,255,255,0.06);
    background: rgba(0,255,255,0.01);
}
.lp-footer-line {
    font-size: 8px;
    color: rgba(0,255,255,0.25);
    letter-spacing: 0.06em;
    line-height: 1.8;
}
.lp-link {
    color: rgba(0,255,255,0.5);
    text-decoration: none;
    letter-spacing: 0.1em;
    transition: color 0.15s;
}
.lp-link:hover { color: #00FFFF; }
</style>
