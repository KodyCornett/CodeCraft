<template>
    <div class="panel">
        <div class="panel-header">[INVENTORY]</div>

        <div class="panel-body">

            <!-- Slot overview -->
            <div class="section section--first">
                <div class="row">
                    <span class="label">LOOT SLOTS</span>
                    <span class="val" :class="inventory.length >= lootCapacity ? 'val--amber' : ''">
                        {{ inventory.length }} / {{ lootCapacity }} USED
                    </span>
                </div>
                <div class="row">
                    <span class="label">PORT SLOTS</span>
                    <span class="val" :class="portSlotsUsed >= (rig.portSlots ?? 4) ? 'val--amber' : ''">
                        {{ portSlotsUsed }} / {{ rig.portSlots ?? 4 }} USED
                    </span>
                </div>
                <div v-if="inventory.length >= lootCapacity" class="cap-warning">
                    ⚠ LOOT SLOTS FULL — ITEMS AT RISK ON NEXT HACK
                </div>
            </div>

            <!-- Hardware Encrypts (carry) -->
            <div class="section">
                <div class="section-title">[HARDWARE ENCRYPTS — CARRY]</div>

                <div v-if="!inventory.length" class="empty-msg">
                    // INVENTORY CLEAR — NO HARDWARE ENCRYPTS
                </div>

                <div
                    v-for="item in inventory"
                    :key="item.id"
                    class="item-row"
                >
                    <span class="item-arrow">▸</span>
                    <span class="item-name">{{ item.name }}</span>
                    <span class="item-boost">+{{ item.boost }} {{ (item.stat ?? '').toUpperCase() }}</span>
                    <span class="item-rarity" :style="{ color: rarityColor(item.rarity) }">
                        [{{ (item.rarity ?? 'common').toUpperCase() }}]
                    </span>
                </div>

                <div v-if="inventory.length" class="install-hint">
                    Install at next Street Doc to activate.
                </div>
            </div>

            <!-- Installed Peripherals -->
            <div class="section">
                <div class="section-title">[INSTALLED PERIPHERALS]</div>

                <div v-if="!installedPeripherals.length" class="empty-msg">
                    // NO PERIPHERALS INSTALLED
                </div>

                <div
                    v-for="p in installedPeripherals"
                    :key="p.id"
                    class="item-row"
                >
                    <span class="item-arrow">▸</span>
                    <span class="item-name">{{ p.name }}</span>
                    <span class="item-boost">+{{ p.boost }} {{ (p.stat ?? '').toUpperCase() }}</span>
                    <span class="item-status" :class="p.damaged ? 'status--damaged' : 'status--bonded'">
                        {{ p.damaged ? '[DAMAGED]' : '[BONDED]' }}
                    </span>
                </div>
            </div>

        </div>

        <button class="panel-close" @click="$emit('close')">[CLOSE]</button>
    </div>
</template>

<script setup>
import { computed } from 'vue';

const props = defineProps({
    player:    { type: Object, required: true },
    rig:       { type: Object, required: true },
    commands:  { type: Array,  default: () => [] },
    inventory: { type: Array,  required: true },
});

defineEmits(['close']);

const lootCapacity = computed(() => (props.rig.storage ?? 3) + 1);

const portSlotsUsed = computed(() =>
    (props.rig.peripherals ?? []).filter(p => p.installed).length,
);

const installedPeripherals = computed(() =>
    (props.rig.peripherals ?? []).filter(p => p.installed),
);

const RARITY_COLOR = {
    common:    'rgba(0, 255, 255, 0.45)',
    uncommon:  '#00FF88',
    rare:      '#8B5CF6',
    legendary: '#FFB300',
};

function rarityColor(rarity) {
    return RARITY_COLOR[rarity] ?? RARITY_COLOR.common;
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
}

/* ── Sections ─────────────────────────────────────────────────────────────────── */
.section {
    padding-top: 12px;
    margin-top: 12px;
    border-top: 1px solid rgba(0, 255, 255, 0.1);
}

.section--first {
    padding-top: 0;
    margin-top: 0;
    border-top: none;
}

.section-title {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.35);
    letter-spacing: 0.08em;
    margin-bottom: 9px;
}

/* ── Basic rows ───────────────────────────────────────────────────────────────── */
.row {
    display: flex;
    justify-content: space-between;
    align-items: center;
    font-size: 11px;
    margin-bottom: 5px;
}

.label { color: rgba(0, 255, 255, 0.45); }
.val   { color: #00FFFF; }
.val--amber { color: #FFB300; }

.cap-warning {
    font-size: 9px;
    color: #FFB300;
    border-left: 2px solid #FFB300;
    padding: 4px 8px;
    margin-top: 6px;
    background: rgba(255, 179, 0, 0.05);
    letter-spacing: 0.03em;
}

/* ── Item rows ────────────────────────────────────────────────────────────────── */
.item-row {
    display: flex;
    align-items: center;
    gap: 6px;
    font-size: 10px;
    margin-bottom: 5px;
}

.item-arrow  { color: rgba(0, 255, 255, 0.4); flex-shrink: 0; }
.item-name   { color: rgba(0, 255, 255, 0.85); flex: 1; min-width: 0; overflow: hidden; text-overflow: ellipsis; white-space: nowrap; }
.item-boost  { color: #00FFFF; flex-shrink: 0; min-width: 50px; }
.item-rarity { font-size: 9px; flex-shrink: 0; }
.item-status { font-size: 9px; flex-shrink: 0; }

.status--bonded  { color: rgba(0, 255, 255, 0.5); }
.status--damaged { color: #FF3333; }

.empty-msg {
    font-size: 10px;
    color: rgba(0, 255, 255, 0.25);
    margin-bottom: 4px;
}

.install-hint {
    font-size: 9px;
    color: rgba(0, 255, 255, 0.3);
    font-style: italic;
    margin-top: 6px;
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
