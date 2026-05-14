<template>
    <div class="boot-screen">
        <div class="boot-inner">
            <div class="boot-logo">CODECRAFT</div>

            <div class="boot-lines">
                <div
                    v-for="(line, i) in visibleLines"
                    :key="i"
                    class="boot-line"
                    :class="{ 'boot-line--cursor': i === visibleLines.length - 1 && !done }"
                >{{ line.text }}{{ i === visibleLines.length - 1 && !done ? cursor : '' }}</div>
            </div>

            <div v-if="done" class="boot-ready">// CONNECTION ESTABLISHED</div>
        </div>
    </div>
</template>

<script setup>
import { ref, onMounted, onUnmounted } from 'vue';

const emit = defineEmits(['done']);

const LINES = [
    { text: 'CODECRAFT OS v2.0',           delay: 300  },
    { text: 'INITIALIZING...',              delay: 700  },
    { text: 'AUTHENTICATING HANDLE...',     delay: 1100 },
    { text: 'LOADING CITY NETWORK...',      delay: 1700 },
    { text: 'LOCATING STREET DOC...',       delay: 2300 },
    { text: 'CONNECTION ESTABLISHED.',      delay: 2900 },
];

const DONE_EMIT_DELAY = 4000; // fades after this

const visibleLines = ref([]);
const cursor       = ref('█');
const done         = ref(false);
const timers       = [];

// Cursor blink
let cursorTimer = setInterval(() => {
    cursor.value = cursor.value === '█' ? ' ' : '█';
}, 530);

onMounted(() => {
    LINES.forEach((line, i) => {
        timers.push(setTimeout(() => {
            visibleLines.value.push(line);
            if (i === LINES.length - 1) {
                done.value = true;
                clearInterval(cursorTimer);
            }
        }, line.delay));
    });

    timers.push(setTimeout(() => emit('done'), DONE_EMIT_DELAY));
});

onUnmounted(() => {
    timers.forEach(clearTimeout);
    clearInterval(cursorTimer);
});
</script>

<style scoped>
.boot-screen {
    position: absolute;
    inset: 0;
    z-index: 100;
    background: #050505;
    display: flex;
    align-items: center;
    justify-content: center;
    font-family: 'JetBrains Mono', monospace;
}

.boot-inner {
    display: flex;
    flex-direction: column;
    gap: 24px;
    max-width: 500px;
    width: 90%;
}

.boot-logo {
    font-size: 32px;
    letter-spacing: 0.25em;
    color: #FF00CC;
    text-shadow:
        0 0 20px rgba(255, 0, 204, 0.6),
        0 0 40px rgba(255, 0, 204, 0.2);
    animation: logo-flicker 6s steps(1) infinite;
}

@keyframes logo-flicker {
    0%   { opacity: 1; }
    92%  { opacity: 1; }
    93%  { opacity: 0.4; }
    94%  { opacity: 1; }
    97%  { opacity: 1; }
    98%  { opacity: 0.6; }
    100% { opacity: 1; }
}

.boot-lines {
    display: flex;
    flex-direction: column;
    gap: 6px;
    border-left: 2px solid rgba(0, 255, 255, 0.2);
    padding-left: 16px;
}

.boot-line {
    font-size: 13px;
    color: rgba(0, 255, 255, 0.8);
    letter-spacing: 0.06em;
    animation: line-appear 0.15s ease-out forwards;
}

.boot-line--cursor {
    color: #00FFFF;
}

@keyframes line-appear {
    from { opacity: 0; transform: translateX(-6px); }
    to   { opacity: 1; transform: translateX(0);    }
}

.boot-ready {
    font-size: 11px;
    color: #00FF88;
    letter-spacing: 0.08em;
    text-shadow: 0 0 10px rgba(0, 255, 136, 0.5);
    animation: ready-pulse 1s ease-in-out infinite;
}

@keyframes ready-pulse {
    0%, 100% { opacity: 0.7; }
    50%       { opacity: 1;   }
}
</style>
