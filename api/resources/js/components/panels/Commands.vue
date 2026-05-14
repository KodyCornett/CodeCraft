<template>
    <div class="panel">
        <div class="panel-header">[COMMANDS]</div>

        <div class="panel-body">

            <!-- Loadout info -->
            <div class="slot-bar">
                <span class="slot-label">LOADOUT SLOTS</span>
                <span class="slot-val" :class="activeCommands.length >= maxSlots ? 'val--amber' : ''">
                    {{ activeCommands.length }} / {{ maxSlots }} ACTIVE
                </span>
            </div>

            <div class="lock-notice">
                ⚠ LOCKED — CHANGE AT NEXT STREET DOC
            </div>

            <!-- Active loadout -->
            <div class="section">
                <div class="section-title">[ACTIVE LOADOUT]</div>

                <div
                    v-for="cmd in activeCommands"
                    :key="cmd.id"
                    class="cmd-row cmd-row--active"
                    :class="{ 'cmd-row--active-def': cmd.type === 'defensive', 'cmd-row--expanded': expandedId === cmd.id }"
                    @click="toggleExpand(cmd.id)"
                >
                    <div class="cmd-main">
                        <span class="cmd-arrow">▶</span>
                        <span class="cmd-name">[{{ cmd.name.toUpperCase() }}]</span>
                        <span class="cmd-type" :class="cmd.type === 'defensive' ? 'type--def' : 'type--off'">
                            {{ cmd.type === 'defensive' ? 'DEF' : 'OFF' }}
                        </span>
                    </div>
                    <Transition name="desc-slide">
                        <div v-if="expandedId === cmd.id" class="cmd-desc">
                            {{ cmd.description }}
                        </div>
                    </Transition>
                </div>

                <!-- Empty slots -->
                <div v-for="i in emptySlots" :key="'empty-' + i" class="cmd-row cmd-row--empty">
                    <span class="slot-empty">[SLOT {{ activeCommands.length + i }}] — EMPTY —</span>
                </div>
            </div>

            <!-- Full library -->
            <div v-if="inactiveCommands.length" class="section">
                <div class="section-title">[FULL LIBRARY]</div>

                <div
                    v-for="cmd in inactiveCommands"
                    :key="cmd.id"
                    class="cmd-row cmd-row--library"
                    :class="{ 'cmd-row--expanded': expandedId === cmd.id }"
                    @click="toggleExpand(cmd.id)"
                >
                    <div class="cmd-main">
                        <span class="cmd-name cmd-name--dim">[{{ cmd.name.toUpperCase() }}]</span>
                        <span class="cmd-type cmd-type--dim" :class="cmd.type === 'defensive' ? 'type--def-dim' : ''">
                            {{ cmd.type === 'defensive' ? 'DEF' : 'OFF' }}
                        </span>
                    </div>
                    <Transition name="desc-slide">
                        <div v-if="expandedId === cmd.id" class="cmd-desc">
                            {{ cmd.description }}
                        </div>
                    </Transition>
                </div>
            </div>

        </div>

        <button class="panel-close" @click="$emit('close')">[CLOSE]</button>
    </div>
</template>

<script setup>
import { ref, computed } from 'vue';

const props = defineProps({
    player:    { type: Object, required: true },
    rig:       { type: Object, required: true },
    commands:  { type: Array,  required: true },
    inventory: { type: Array,  default: () => [] },
});

defineEmits(['close']);

const expandedId = ref(null);

const maxSlots = computed(() => (props.rig.ram ?? 3) + 1);
const activeCommands   = computed(() => props.commands.filter(c => c.active));
const inactiveCommands = computed(() => props.commands.filter(c => !c.active));
const emptySlots       = computed(() => Math.max(0, maxSlots.value - activeCommands.value.length));

function toggleExpand(id) {
    expandedId.value = expandedId.value === id ? null : id;
}
</script>

<style scoped>
.panel {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: rgba(5, 5, 5, 0.97);
    font-family: 'JetBrains Mono', monospace;
    color: #00FFFF;
}

.panel-header {
    padding: 14px 18px;
    font-size: 12px;
    letter-spacing: 0.08em;
    border-bottom: 1px solid rgba(0, 255, 255, 0.2);
    flex-shrink: 0;
}

.panel-body {
    flex: 1;
    overflow-y: auto;
    padding: 14px 18px;
    display: flex;
    flex-direction: column;
    gap: 0;
}

/* ── Slot bar ─────────────────────────────────────────────────────────────────── */
.slot-bar {
    display: flex;
    justify-content: space-between;
    font-size: 10px;
    margin-bottom: 6px;
}

.slot-label { color: rgba(0, 255, 255, 0.4); }
.slot-val   { color: #00FFFF; }
.val--amber { color: #FFB300; }

/* ── Lock notice ──────────────────────────────────────────────────────────────── */
.lock-notice {
    font-size: 9px;
    color: #FFB300;
    background: rgba(255, 179, 0, 0.07);
    border-left: 3px solid #FFB300;
    padding: 5px 8px;
    letter-spacing: 0.03em;
    margin-bottom: 2px;
}

/* ── Sections ─────────────────────────────────────────────────────────────────── */
.section {
    padding-top: 12px;
    margin-top: 12px;
    border-top: 1px solid rgba(0, 255, 255, 0.1);
}

.section-title {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.35);
    letter-spacing: 0.08em;
    margin-bottom: 8px;
}

/* ── Command rows ─────────────────────────────────────────────────────────────── */
.cmd-row {
    margin-bottom: 3px;
    cursor: pointer;
    transition: background 0.12s;
}

.cmd-main {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 8px;
    font-size: 10px;
}

/* Active loadout */
.cmd-row--active .cmd-main {
    border: 1px solid rgba(0, 255, 255, 0.3);
    background: rgba(0, 255, 255, 0.03);
}

.cmd-row--active:hover .cmd-main {
    background: rgba(0, 255, 255, 0.06);
}

.cmd-row--active-def .cmd-main {
    border-color: rgba(255, 0, 204, 0.3);
    background: rgba(255, 0, 204, 0.03);
}

.cmd-row--active-def:hover .cmd-main {
    background: rgba(255, 0, 204, 0.06);
}

/* Empty slots */
.cmd-row--empty .cmd-main,
.cmd-row--empty {
    padding: 6px 8px;
    border: 1px dashed rgba(0, 255, 255, 0.12);
    font-size: 10px;
    cursor: default;
}

/* Library (inactive) */
.cmd-row--library .cmd-main {
    padding: 5px 8px;
}

.cmd-row--library:hover .cmd-main {
    background: rgba(0, 255, 255, 0.03);
}

.cmd-arrow { color: rgba(0, 255, 255, 0.6); flex-shrink: 0; }

.cmd-name     { color: #00FFFF; flex: 1; letter-spacing: 0.03em; }
.cmd-name--dim { color: rgba(0, 255, 255, 0.4); flex: 1; }

.cmd-type       { font-size: 8px; flex-shrink: 0; }
.type--off      { color: rgba(0, 255, 255, 0.5); }
.type--def      { color: rgba(255, 0, 204, 0.8); }
.cmd-type--dim  { font-size: 8px; flex-shrink: 0; }
.type--def-dim  { color: rgba(255, 0, 204, 0.4); }

.slot-empty { color: rgba(0, 255, 255, 0.2); font-size: 10px; }

/* Description expand */
.cmd-desc {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.6);
    padding: 5px 10px 7px 28px;
    border-left: 2px solid rgba(0, 255, 255, 0.15);
    margin-left: 8px;
    line-height: 1.5;
}

.desc-slide-enter-active,
.desc-slide-leave-active {
    transition: opacity 0.15s ease, max-height 0.2s ease;
    overflow: hidden;
    max-height: 80px;
}

.desc-slide-enter-from,
.desc-slide-leave-to {
    opacity: 0;
    max-height: 0;
}

/* ── Close ────────────────────────────────────────────────────────────────────── */
.panel-close {
    margin: 10px 18px;
    padding: 7px 14px;
    align-self: flex-start;
    background: transparent;
    border: 1px solid rgba(0, 255, 255, 0.35);
    color: #00FFFF;
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.05em;
    cursor: pointer;
    flex-shrink: 0;
    transition: background 0.15s;
}

.panel-close:hover { background: rgba(0, 255, 255, 0.07); }
</style>
