<template>
    <QuestMinigameChrome v-bind="chrome">

        <div class="ts-canvas">

            <!-- ══════════════════════════════════════════════════════════════
                 Container 1 — Top bar: Node Integrity | Rig | Environment
            ══════════════════════════════════════════════════════════════ -->
            <div class="ts-top">
                <div class="ts-meter-group">
                    <span class="ts-meter-lbl">NODE INTEGRITY</span>
                    <div class="ts-meter-track">
                        <div class="ts-meter-fill ts-fill--ni"
                             :style="{ width: nodeIntegrity + '%' }"
                             :class="niClass" />
                        <div v-for="t in [15,30,45,60,75,90]" :key="t"
                             class="ts-thresh-mark"
                             :style="{ left: t + '%' }" />
                    </div>
                    <span class="ts-meter-val" :class="niClass">{{ Math.round(nodeIntegrity) }}%</span>
                </div>
                <div class="ts-meter-group">
                    <span class="ts-meter-lbl">RIG</span>
                    <div class="ts-meter-track">
                        <div class="ts-meter-fill ts-fill--rig"
                             :style="{ width: rig + '%' }"
                             :class="rigClass" />
                    </div>
                    <span class="ts-meter-val" :class="rigClass">{{ Math.round(rig) }}%</span>
                </div>
                <div class="ts-meter-group">
                    <span class="ts-meter-lbl">ENVIRONMENT</span>
                    <div class="ts-meter-track">
                        <div class="ts-meter-fill ts-fill--env"
                             :style="{ width: environment + '%' }"
                             :class="envClass" />
                    </div>
                    <span class="ts-meter-val" :class="envClass">{{ Math.round(environment) }}%</span>
                </div>
            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 Containers 2 / 3 / 4 — Middle panels
            ══════════════════════════════════════════════════════════════ -->
            <div class="ts-middle">

                <!-- Container 2 — Data fields with I/O throughput -->
                <div class="ts-panel ts-fields-panel">
                    <div class="ts-ph">DATA FIELDS</div>
                    <div class="ts-io-header">
                        <span class="ts-fid-col" />
                        <span class="ts-io-hdr">OUTPUT</span>
                        <span class="ts-io-sep">|</span>
                        <span class="ts-io-hdr">INPUT</span>
                    </div>
                    <div v-for="fid in activeFieldIds" :key="fid"
                         class="ts-field-row"
                         :class="{ 'ts-field-row--active': expandedFields.has(fid), 'ts-field-row--bleed': fieldHasBleed(fid) }"
                         @click="toggleField(fid)">
                        <span class="ts-fid">{{ fid }}</span>
                        <span class="ts-io-val" :class="ioOutClass(fid)">{{ ioOut(fid) }}</span>
                        <span class="ts-io-sep">|</span>
                        <span class="ts-io-val" :class="ioInClass(fid)">{{ ioIn(fid) }}</span>
                    </div>
                </div>

                <!-- Container 3 — File explorer -->
                <div class="ts-panel ts-explorer-panel">
                    <div class="ts-ph">DATA_FIELDS :: FILE EXPLORER</div>
                    <div class="ts-explorer">
                        <div class="ts-root-path">/splice/sys/node_{{ iceLevel }}/</div>
                        <div v-for="fid in activeFieldIds" :key="fid" class="ts-folder-group">

                            <!-- Folder row -->
                            <div class="ts-folder-row"
                                 :class="{ 'ts-folder--open': expandedFields.has(fid), 'ts-folder--bleed': fieldHasBleed(fid) }"
                                 @click="toggleField(fid)">
                                <span class="ts-fold-arrow">{{ expandedFields.has(fid) ? '▼' : '▶' }}</span>
                                <span class="ts-fold-icon">{{ expandedFields.has(fid) ? '📂' : '📁' }}</span>
                                <span class="ts-fold-name">{{ fid }}/</span>
                                <span v-if="fieldHasBleed(fid)" class="ts-fold-warn">⚠ DATA BLEED</span>
                            </div>

                            <!-- File rows -->
                            <template v-if="expandedFields.has(fid)">
                                <div v-for="entry in getFieldPackets(fid)" :key="entry.id"
                                     class="ts-file-row"
                                     :class="{ 'ts-file--bleed': entry.isBleed, 'ts-file--home': entry.isHome }">
                                    <span class="ts-file-tree">{{ '│  ' }}</span>
                                    <span class="ts-file-flag">{{ entry.isBleed ? '[!]' : '   ' }}</span>
                                    <span class="ts-file-name">{{ entry.displayName }}.dat</span>
                                    <span class="ts-file-aff" :class="'ts-aff--' + entry.affinity">
                                        {{ entry.affinity === 'rig' ? '◈ RIG' : '◈ ENV' }}
                                    </span>
                                    <span class="ts-file-status">
                                        {{ entry.isHome ? '✓ HOME' : '⟶ ' + entry.homeField }}
                                    </span>
                                </div>
                                <!-- Rig noise — phantom files -->
                                <template v-if="flickerPhantom && fid === focusedFieldId && phantomEntries.length">
                                    <div v-for="(ph, i) in phantomEntries" :key="'ph' + i"
                                         class="ts-file-row ts-file--phantom">
                                        <span class="ts-file-tree">│  </span>
                                        <span class="ts-file-flag">[?]</span>
                                        <span class="ts-file-name">{{ ph }}.dat</span>
                                        <span class="ts-file-aff">◈ ???</span>
                                        <span class="ts-file-status">⟶ [UNKNOWN]</span>
                                    </div>
                                </template>
                            </template>

                        </div>
                    </div>
                </div>

                <!-- Container 4 — Field specification / sync status -->
                <div class="ts-panel ts-expected-panel">
                    <div class="ts-ph">
                        {{ focusedFieldId ? 'FIELD SPEC :: ' + focusedFieldId : 'FIELD SPECIFICATION' }}
                    </div>
                    <template v-if="focusedFieldId">
                        <div class="ts-spec-summary">
                            <span class="ts-spec-label">SYNC STATUS</span>
                            <span class="ts-spec-count" :class="syncCountClass">
                                {{ syncedCount(focusedFieldId) }}/{{ expectedPackets.length }} SYNCHRONIZED
                            </span>
                        </div>
                        <div class="ts-spec-col-headers">
                            <span>COMMON_DATA</span>
                            <span>LOCATION</span>
                        </div>
                        <div v-for="name in expectedPackets" :key="name"
                             class="ts-spec-row"
                             :class="isPacketHome(name, focusedFieldId) ? 'ts-spec--synced' : 'ts-spec--missing'">
                            <span class="ts-spec-name">{{ name }}</span>
                            <span class="ts-spec-loc">
                                <template v-if="isPacketHome(name, focusedFieldId)">
                                    <span class="ts-loc--home">✓ HOME</span>
                                </template>
                                <template v-else>
                                    <span class="ts-loc--away">✗ IN {{ findPacketLocation(name, focusedFieldId) }}</span>
                                </template>
                            </span>
                        </div>
                    </template>
                    <div v-else class="ts-empty">// OPEN A FOLDER TO VIEW SPECIFICATION</div>
                </div>

            </div>

            <!-- ══════════════════════════════════════════════════════════════
                 Containers 5 / 6 — Bottom bar
            ══════════════════════════════════════════════════════════════ -->
            <div class="ts-bottom">

                <!-- Container 5 — SPLICE interface -->
                <div class="ts-panel ts-splice-panel">
                    <div class="ts-ph">SPLICE INTERFACE</div>
                    <div class="ts-splice-chain">

                        <div class="ts-splice-step">
                            <label class="ts-slbl">SOURCE FIELD</label>
                            <select class="ts-sel" v-model="spliceFieldId" @change="onSrcFieldChange">
                                <option value="">-- SELECT --</option>
                                <option v-for="fid in activeFieldIds" :key="fid" :value="fid">{{ fid }}</option>
                            </select>
                        </div>

                        <span class="ts-chain-sep">-</span>

                        <div class="ts-splice-step">
                            <label class="ts-slbl">COMMON_DATA</label>
                            <select class="ts-sel" v-model="splicePacketId"
                                    @change="onSrcPacketChange"
                                    :disabled="!spliceFieldId">
                                <option value="">-- SELECT --</option>
                                <option v-for="p in spliceSourcePackets" :key="p.id" :value="p.id">
                                    {{ p.name }}
                                </option>
                            </select>
                        </div>

                        <span class="ts-chain-sep">>>>></span>

                        <div class="ts-splice-step">
                            <label class="ts-slbl">DESTINATION FIELD</label>
                            <select class="ts-sel" v-model="spliceDestFieldId"
                                    @change="onDestFieldChange"
                                    :disabled="!splicePacketId">
                                <option value="">-- SELECT --</option>
                                <option v-for="fid in spliceDestFields" :key="fid" :value="fid">{{ fid }}</option>
                            </select>
                        </div>

                        <span class="ts-chain-sep">>>>></span>

                        <div class="ts-splice-step">
                            <label class="ts-slbl">POSITION</label>
                            <select class="ts-sel" v-model="spliceDestSlot"
                                    :disabled="!spliceDestFieldId">
                                <option value="">-- SELECT --</option>
                                <option v-for="idx in spliceDestEmptySlots" :key="idx" :value="idx">
                                    SLOT {{ idx + 1 }}
                                </option>
                            </select>
                        </div>

                        <button class="ts-splice-btn"
                                :disabled="!canSplice"
                                @click="executeSplice">
                            [ EXECUTE SPLICE ]
                        </button>

                    </div>
                </div>

                <!-- Container 6 — SOAK / VENT / PURGE buttons -->
                <div class="ts-panel ts-action-panel">
                    <div class="ts-ph">DISCHARGE // PURGE</div>
                    <div class="ts-action-row">

                        <button class="ts-act-btn ts-btn--soak"
                                :disabled="!canDischarge"
                                @click="soak">
                            <span class="ts-abl">SOAK</span>
                            <span class="ts-asub">→ RIG</span>
                            <span v-if="dischargeCooldown > 0" class="ts-cool">{{ dischargeCooldown }}s</span>
                        </button>

                        <button class="ts-act-btn ts-btn--vent"
                                :disabled="!canDischarge"
                                @click="vent">
                            <span class="ts-abl">VENT</span>
                            <span class="ts-asub">→ ENV</span>
                            <span v-if="dischargeCooldown > 0" class="ts-cool">{{ dischargeCooldown }}s</span>
                        </button>

                        <button class="ts-act-btn ts-btn--purge"
                                :disabled="!canPurge"
                                @click="purge">
                            <span class="ts-abl">PURGE</span>
                            <span class="ts-asub" :class="{ 'ts-asub--ready': canPurge }">
                                {{ canPurge ? '◈ SYNC COMPLETE' : 'SYNC PENDING' }}
                            </span>
                        </button>

                    </div>
                </div>

            </div>

        </div>

    </QuestMinigameChrome>
</template>

<script setup>
import { ref, computed, onMounted, onUnmounted } from 'vue';
import QuestMinigameChrome from './chrome/QuestMinigameChrome.vue';

// ── Field / packet data ────────────────────────────────────────────────────────

const FIELD_DEFS = {
    AI_WASTE: {
        affinity: 'environment',
        packets: ['Core_Frags','Ghost_Process','Logic_Residue','Kernel_Shards','Thread_Stacks','Personality_Seeds','Loop_Artifacts','Heuristic_Maps'],
    },
    SENSORIUM_DATA: {
        affinity: 'rig',
        packets: ['Visual_Capture','Audio_Stream','Motion_Trace','Haptic_Record','Presence_Snapshot','Scent_Profile','Location_History','Biometric_Readings'],
    },
    AFFECT_PROFILE: {
        affinity: 'rig',
        packets: ['Mood_Vector','Threat_Index','Reward_Profile','Attention_Trace','Preference_Map','Social_Weight','Engagement_Pattern','Impulse_Record'],
    },
    DISPLAY_WASTE: {
        affinity: 'environment',
        packets: ['Screen_Waste','Pixel_Decay','Color_Burn','Compression_Artifacts','Scanlines','CRT_Noise','HUD_Residue','Render_Fragments'],
    },
    AD_RESIDUE: {
        affinity: 'environment',
        packets: ['Banner_Ads','Popup_Spam','Tracking_Data','Product_Profiles','Engagement_Metrics','Recommendation_Feed','Purchase_History','Sponsored_Content'],
    },
    SYSTEM_NOISE: {
        affinity: 'environment',
        packets: ['Packet_Loss','Null_Values','Dead_Links','Clock_Drift','Cache_Errors','Sync_Failure','Protocol_Timeout','Desync'],
    },
    NETWORK_ECHOES: {
        affinity: 'environment',
        packets: ['Presence_Data','Session_Keys','Friend_Lists','Avatar_Profiles','Message_Threads','Shared_Channels','Access_Tokens','User_Handles'],
    },
    ARCHIVE_DECAY: {
        affinity: 'rig',
        packets: ['Legacy_Formats','Corrupted_Backups','Dead_Indexes','Timestamp_Records','Deleted_Pointers','Version_History','Deprecated_Protocols','Data_Fossils'],
    },
};

const ALL_FIELD_IDS = Object.keys(FIELD_DEFS);

const NOISE_CHARS = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ[]{}|\\<>!@#%&0123456789'.split('');

// ── Game constants ─────────────────────────────────────────────────────────────

const DISCHARGE_RELIEF    = 20;   // % reduction to node integrity per discharge
const DISCHARGE_DMG_MATCH = 12;   // % damage with matching affinity
const DISCHARGE_DMG_WRONG = 22;   // % damage with wrong affinity
const DISCHARGE_COOLDOWN  = 10;   // seconds shared cooldown
const TICK_INTERVAL_MS    = 3000; // ms between node integrity ticks
const BASE_TIME           = 210;  // seconds (3.5 minutes)

const TICK_RATES = [
    { threshold: 90, rate: 4.0 },
    { threshold: 75, rate: 3.5 },
    { threshold: 60, rate: 3.0 },
    { threshold: 45, rate: 2.5 },
    { threshold: 30, rate: 2.0 },
    { threshold: 15, rate: 1.5 },
    { threshold: 0,  rate: 1.0 },
];

// ── Props / emits ──────────────────────────────────────────────────────────────

const props = defineProps({ skin: { type: Object, required: true } });
const emit  = defineEmits(['complete', 'fail']);

// ── ICE level ─────────────────────────────────────────────────────────────────

const iceLevel = computed(() =>
    Math.min(8, Math.max(3, props.skin.iceLevel ?? props.skin.difficulty ?? 3))
);

// ── Game meters ────────────────────────────────────────────────────────────────

const nodeIntegrity   = ref(0);
const rig             = ref(0);
const environment     = ref(0);
const timeLeft        = ref(BASE_TIME + (props.skin.ramBonus ?? 0));
const gameResult      = ref(null);   // null | 'success' | 'fail'
const failReason      = ref('');
const dischargeCooldown = ref(0);

// ── Puzzle state ───────────────────────────────────────────────────────────────

const activeFieldIds   = ref([]);   // string[]
const fieldSlots       = ref({});   // { [fieldId]: (string|null)[] }
const packetDefs       = ref({});   // { [id]: { id, name, homeField, currentField, affinity } }
const fieldConnections = ref({});   // { [fieldId]: string[] }
const openSlotFieldId  = ref('');

// ── UI state ───────────────────────────────────────────────────────────────────

const expandedFields    = ref(new Set());  // fieldIds open in the explorer
const focusedFieldId    = ref(null);       // drives right panel expected contents
const spliceFieldId     = ref('');
const splicePacketId    = ref('');
const spliceDestFieldId = ref('');
const spliceDestSlot    = ref('');  // holds slot index (number) or ''

// ── Noise state ────────────────────────────────────────────────────────────────

const flickerPhantom = ref(false);
const flickerLabel   = ref(false);
const corruptSeed    = ref(0);
const ioValues       = ref({});     // { [fieldId]: { out: number, in: number } }

// ── Utilities ──────────────────────────────────────────────────────────────────

function randInt(min, max) { return Math.floor(Math.random() * (max - min + 1)) + min; }

function shuffle(arr) {
    const a = [...arr];
    for (let i = a.length - 1; i > 0; i--) {
        const j = randInt(0, i);
        [a[i], a[j]] = [a[j], a[i]];
    }
    return a;
}

function uid() { return Math.random().toString(36).slice(2, 9); }

function hasEmptySlot(fieldId) {
    return (fieldSlots.value[fieldId] ?? []).some(s => s === null);
}

function fieldHasBleed(fieldId) {
    return (fieldSlots.value[fieldId] ?? []).some(id => {
        if (!id) return false;
        const p = packetDefs.value[id];
        return p && p.homeField !== fieldId;
    });
}

// ── Puzzle generation ──────────────────────────────────────────────────────────

function buildPuzzle() {
    const ice = iceLevel.value;

    // 1. Select fields
    const selected = shuffle([...ALL_FIELD_IDS]).slice(0, ice);
    activeFieldIds.value = selected;

    // 2. Connection topology — spanning tree + extra edges
    const conns = {};
    selected.forEach(id => { conns[id] = []; });

    const tree = shuffle([...selected]);
    for (let i = 1; i < tree.length; i++) {
        const a = tree[i];
        const b = tree[randInt(0, i - 1)];
        if (!conns[a].includes(b)) { conns[a].push(b); conns[b].push(a); }
    }
    const extraEdges = Math.floor(ice * 0.6);
    for (let e = 0; e < extraEdges; e++) {
        const a = selected[randInt(0, selected.length - 1)];
        const b = selected[randInt(0, selected.length - 1)];
        if (a !== b && !conns[a].includes(b)) { conns[a].push(b); conns[b].push(a); }
    }
    fieldConnections.value = conns;

    // 3. Seed packets — ICE packets per field from correct pool
    const pkts  = {};
    const slots = {};

    selected.forEach(fieldId => {
        const def   = FIELD_DEFS[fieldId];
        const names = shuffle([...def.packets]).slice(0, ice);
        slots[fieldId] = [];
        names.forEach(name => {
            const id = `${fieldId}__${name}__${uid()}`;
            pkts[id] = { id, name, homeField: fieldId, currentField: fieldId, affinity: def.affinity };
            slots[fieldId].push(id);
        });
    });

    // 4. Open slot — add one null to a random field
    const openField = selected[randInt(0, selected.length - 1)];
    openSlotFieldId.value = openField;
    slots[openField].push(null);

    // 5. Seed bleeds via pair swaps (max 2 per field)
    const bleedCounts = {};
    selected.forEach(id => { bleedCounts[id] = 0; });

    let placed = 0;
    const target = ice;

    // Generate candidate pairs
    const pairs = [];
    for (let i = 0; i < selected.length; i++) {
        for (let j = i + 1; j < selected.length; j++) {
            pairs.push([selected[i], selected[j]]);
        }
    }

    for (const [a, b] of shuffle(pairs)) {
        if (placed >= target) break;
        if (bleedCounts[a] >= 2 || bleedCounts[b] >= 2) continue;

        // Find a home packet from a currently sitting in a, and vice versa
        const pFromA = slots[a].find(id => id && pkts[id].homeField === a);
        const pFromB = slots[b].find(id => id && pkts[id].homeField === b);
        if (!pFromA || !pFromB) continue;

        const ia = slots[a].indexOf(pFromA);
        const ib = slots[b].indexOf(pFromB);

        slots[a][ia] = pFromB;
        slots[b][ib] = pFromA;
        pkts[pFromA].currentField = b;
        pkts[pFromB].currentField = a;

        bleedCounts[a]++;
        bleedCounts[b]++;
        placed += 2;
    }

    // Handle odd bleed count: move one more packet into the open slot field
    if (placed < target) {
        for (const src of shuffle([...selected])) {
            if (src === openField) continue;
            if (bleedCounts[src] >= 2 || bleedCounts[openField] >= 2) continue;

            const pId    = slots[src].find(id => id && pkts[id].homeField === src);
            const nullIdx = slots[openField].indexOf(null);
            const srcIdx  = pId ? slots[src].indexOf(pId) : -1;
            if (!pId || nullIdx === -1 || srcIdx === -1) continue;

            slots[openField][nullIdx] = pId;
            slots[src][srcIdx] = null;
            pkts[pId].currentField = openField;
            openSlotFieldId.value = src;
            bleedCounts[openField]++;
            placed++;
            break;
        }
    }

    packetDefs.value = pkts;
    fieldSlots.value  = slots;

    initIoValues();
}

// ── I/O display values ─────────────────────────────────────────────────────────

function initIoValues() {
    const v = {};
    activeFieldIds.value.forEach(fid => {
        if (fieldHasBleed(fid)) {
            v[fid] = { out: 14 + Math.random() * 4, in: 8 + Math.random() * 3 };
        } else {
            v[fid] = { out: 10 + Math.random() * 3, in: 11 + Math.random() * 4 };
        }
    });
    ioValues.value = v;
}

function updateIoForField(fieldId) {
    const hasBleed = fieldHasBleed(fieldId);
    const cur = ioValues.value[fieldId];
    if (!cur) return;
    if (hasBleed && cur.in >= cur.out) {
        ioValues.value = { ...ioValues.value, [fieldId]: { out: 14 + Math.random() * 4, in: 8 + Math.random() * 3 } };
    } else if (!hasBleed && cur.out > cur.in) {
        ioValues.value = { ...ioValues.value, [fieldId]: { out: 10 + Math.random() * 3, in: 11 + Math.random() * 4 } };
    }
}

function fluctuateIo() {
    if (gameResult.value) return;
    const updated = {};
    activeFieldIds.value.forEach(fid => {
        const v = ioValues.value[fid];
        if (!v) return;
        const delta = (Math.random() - 0.5) * 0.5;
        let newOut = Math.max(0.5, v.out + delta);
        let newIn  = Math.max(0.5, v.in  + delta * 0.7);
        // Environment noise can corrupt readings
        const corrupt = environment.value > 50 && Math.random() < 0.12;
        updated[fid] = { out: corrupt ? -1 : newOut, in: newIn };
    });
    ioValues.value = { ...ioValues.value, ...updated };
}

function ioOut(fid) {
    const v = ioValues.value[fid];
    if (!v) return '--.-- TH/s';
    if (v.out < 0) return '??.?? TH/s';
    return v.out.toFixed(1) + ' TH/s';
}

function ioIn(fid) {
    const v = ioValues.value[fid];
    if (!v) return '--.-- TH/s';
    return v.in.toFixed(1) + ' TH/s';
}

function ioOutClass(fid) {
    const v = ioValues.value[fid];
    if (!v) return '';
    if (v.out < 0) return 'ts-io--corrupt';
    return v.out > v.in ? 'ts-io--red' : 'ts-io--green';
}

function ioInClass(fid) {
    const v = ioValues.value[fid];
    if (!v) return '';
    return v.out > v.in ? 'ts-io--yellow' : 'ts-io--green';
}

// ── Noise helpers ──────────────────────────────────────────────────────────────

function corruptedName(name) {
    if (environment.value < 20 || !flickerLabel.value) return name;
    const chance = Math.min(0.5, (environment.value - 20) / 80 * 0.5);
    const seed = corruptSeed.value;
    return name.split('').map((c, i) => {
        if (c === '_') return c;
        const h = ((seed * 31 + i * 17) % 100) / 100;
        if (h < chance) return NOISE_CHARS[(seed + i) % NOISE_CHARS.length];
        return c;
    }).join('');
}

const phantomEntries = computed(() => {
    if (!flickerPhantom.value || !focusedFieldId.value || rig.value < 25) return [];
    const count = Math.min(3, Math.floor(rig.value / 25));
    const def   = FIELD_DEFS[focusedFieldId.value];
    if (!def) return [];
    const seed = corruptSeed.value;
    return def.packets.slice(0, count).map((name, pi) =>
        name.split('').map((c, i) => {
            if (c === '_') return c;
            return ((seed + pi * 7 + i * 3) % 4 === 0)
                ? NOISE_CHARS[(seed + i) % NOISE_CHARS.length]
                : c;
        }).join('')
    );
});

// ── Computed — packet display ──────────────────────────────────────────────────

function getFieldPackets(fieldId) {
    return (fieldSlots.value[fieldId] ?? [])
        .filter(id => id !== null)
        .map(id => {
            const p = packetDefs.value[id];
            if (!p) return null;
            return {
                id:          p.id,
                name:        p.name,
                displayName: corruptedName(p.name),
                homeField:   p.homeField,
                affinity:    p.affinity,
                isBleed:     p.homeField !== fieldId,
                isHome:      p.homeField === fieldId,
            };
        })
        .filter(Boolean);
}

const expectedPackets = computed(() => {
    if (!focusedFieldId.value) return [];
    return Object.values(packetDefs.value)
        .filter(p => p.homeField === focusedFieldId.value)
        .map(p => p.name);
});

function isPacketHome(name, fieldId) {
    return Object.values(packetDefs.value).some(
        p => p.name === name && p.homeField === fieldId && p.currentField === fieldId
    );
}

function findPacketLocation(name, homeFieldId) {
    const p = Object.values(packetDefs.value).find(
        p => p.name === name && p.homeField === homeFieldId
    );
    return p ? p.currentField : '???';
}

function syncedCount(fieldId) {
    return Object.values(packetDefs.value)
        .filter(p => p.homeField === fieldId && p.currentField === fieldId)
        .length;
}

const syncCountClass = computed(() => {
    if (!focusedFieldId.value) return '';
    const total  = expectedPackets.value.length;
    const synced = syncedCount(focusedFieldId.value);
    if (synced === total) return 'ts-sync--full';
    if (synced === 0)     return 'ts-sync--none';
    return 'ts-sync--partial';
});

// ── Computed — SPLICE dropdowns ────────────────────────────────────────────────

const spliceSourcePackets = computed(() => {
    if (!spliceFieldId.value) return [];
    return (fieldSlots.value[spliceFieldId.value] ?? [])
        .filter(id => id !== null)
        .map(id => packetDefs.value[id])
        .filter(Boolean);
});

const spliceDestFields = computed(() => {
    if (!splicePacketId.value) return [];
    const pkt = packetDefs.value[splicePacketId.value];
    if (!pkt) return [];
    const connected = fieldConnections.value[pkt.currentField] ?? [];
    return connected.filter(fid => hasEmptySlot(fid));
});

const spliceDestEmptySlots = computed(() => {
    if (!spliceDestFieldId.value) return [];
    const slots = fieldSlots.value[spliceDestFieldId.value] ?? [];
    return slots.map((s, i) => i).filter(i => slots[i] === null);
});

const canSplice = computed(() =>
    !!spliceFieldId.value &&
    !!splicePacketId.value &&
    !!spliceDestFieldId.value &&
    spliceDestSlot.value !== ''
);

const canDischarge = computed(() => dischargeCooldown.value === 0 && !gameResult.value);

const canPurge = computed(() => {
    const pkts = Object.values(packetDefs.value);
    return pkts.length > 0 && pkts.every(p => p.currentField === p.homeField);
});

// ── CSS state classes ──────────────────────────────────────────────────────────

const niClass = computed(() => {
    if (nodeIntegrity.value >= 90) return 'ts-val--crit';
    if (nodeIntegrity.value >= 60) return 'ts-val--warn';
    return '';
});

const rigClass = computed(() => {
    if (rig.value >= 80) return 'ts-val--crit';
    if (rig.value >= 55) return 'ts-val--warn';
    return '';
});

const envClass = computed(() => {
    if (environment.value >= 80) return 'ts-val--crit';
    if (environment.value >= 55) return 'ts-val--warn';
    return '';
});

// ── Actions ────────────────────────────────────────────────────────────────────

function toggleField(fieldId) {
    const next = new Set(expandedFields.value);
    if (next.has(fieldId)) {
        next.delete(fieldId);
    } else {
        next.add(fieldId);
        focusedFieldId.value = fieldId;
    }
    expandedFields.value = next;
}

function getActiveAffinity() {
    if (splicePacketId.value) {
        return packetDefs.value[splicePacketId.value]?.affinity ?? 'environment';
    }
    if (focusedFieldId.value) {
        return FIELD_DEFS[focusedFieldId.value]?.affinity ?? 'environment';
    }
    return 'environment';
}

function discharge(target) {
    if (!canDischarge.value) return;

    const activeAff   = getActiveAffinity();
    const matching    = (target === 'rig' && activeAff === 'rig') ||
                        (target === 'environment' && activeAff === 'environment');
    const damage      = matching ? DISCHARGE_DMG_MATCH : DISCHARGE_DMG_WRONG;

    nodeIntegrity.value = Math.max(0, nodeIntegrity.value - DISCHARGE_RELIEF);

    if (target === 'rig') {
        rig.value = Math.min(100, rig.value + damage);
    } else {
        environment.value = Math.min(100, environment.value + damage);
    }

    dischargeCooldown.value = DISCHARGE_COOLDOWN;
    checkFailConditions();
}

function soak() { discharge('rig'); }
function vent()  { discharge('environment'); }

function onSrcFieldChange() {
    splicePacketId.value    = '';
    spliceDestFieldId.value = '';
    spliceDestSlot.value    = '';
}

function onSrcPacketChange() {
    spliceDestFieldId.value = '';
    spliceDestSlot.value    = '';
}

function onDestFieldChange() {
    spliceDestSlot.value = '';
}

function executeSplice() {
    if (!canSplice.value || gameResult.value) return;

    const pkt    = packetDefs.value[splicePacketId.value];
    if (!pkt) return;

    const srcSlots = fieldSlots.value[spliceFieldId.value];
    const dstSlots = fieldSlots.value[spliceDestFieldId.value];
    const dstIdx   = Number(spliceDestSlot.value);

    const srcIdx = srcSlots.indexOf(splicePacketId.value);
    if (srcIdx === -1 || dstSlots[dstIdx] !== null) return;

    srcSlots[srcIdx]   = null;
    dstSlots[dstIdx]   = splicePacketId.value;
    pkt.currentField   = spliceDestFieldId.value;

    updateIoForField(spliceFieldId.value);
    updateIoForField(spliceDestFieldId.value);

    // Reset SPLICE UI
    spliceFieldId.value     = '';
    splicePacketId.value    = '';
    spliceDestFieldId.value = '';
    spliceDestSlot.value    = '';
}

function purge() {
    if (!canPurge.value || gameResult.value) return;
    endGame('success', '');
}

// ── Win / fail ─────────────────────────────────────────────────────────────────

function checkFailConditions() {
    if (gameResult.value) return;
    if (nodeIntegrity.value >= 100) {
        endGame('fail', '[CRITICAL NODE FAILURE] — Node integrity threshold exceeded.');
    } else if (rig.value >= 100) {
        endGame('fail', '[RIG COLLAPSE] — Rig absorption capacity exhausted.');
    } else if (environment.value >= 100) {
        endGame('fail', '[ENVIRONMENT COLLAPSE] — Splice Frequency saturation critical.');
    }
}

function endGame(result, reason) {
    if (gameResult.value) return;
    gameResult.value = result;
    failReason.value = reason ?? '';
    clearAllIntervals();
    if (result === 'success') {
        setTimeout(() => emit('complete'), 2200);
    } else {
        setTimeout(() => emit('fail'), 2200);
    }
}

// ── Tick system ────────────────────────────────────────────────────────────────

function getTickRate() {
    const ni = nodeIntegrity.value;
    for (const { threshold, rate } of TICK_RATES) {
        if (ni >= threshold) return rate;
    }
    return 1.0;
}

const _intervals = [];

function startAllIntervals() {
    // Node integrity climbs every 3s
    _intervals.push(setInterval(() => {
        if (gameResult.value) return;
        nodeIntegrity.value = Math.min(100, nodeIntegrity.value + getTickRate());
        checkFailConditions();
    }, TICK_INTERVAL_MS));

    // Timer countdown — 1s
    _intervals.push(setInterval(() => {
        if (gameResult.value) return;
        timeLeft.value = Math.max(0, timeLeft.value - 1);
        if (timeLeft.value <= 0) {
            endGame('fail', '[TIMER EXPIRED] — Node integrity could not be restored in time.');
        }
    }, 1000));

    // Discharge cooldown — 1s
    _intervals.push(setInterval(() => {
        if (dischargeCooldown.value > 0) {
            dischargeCooldown.value = Math.max(0, dischargeCooldown.value - 1);
        }
    }, 1000));

    // I/O fluctuation — 500ms
    _intervals.push(setInterval(fluctuateIo, 500));

    // Noise flicker — 700ms
    _intervals.push(setInterval(() => {
        if (rig.value >= 25) flickerPhantom.value = !flickerPhantom.value;
        if (environment.value >= 20) {
            flickerLabel.value = !flickerLabel.value;
            corruptSeed.value++;
        }
    }, 700));
}

function clearAllIntervals() {
    _intervals.forEach(clearInterval);
    _intervals.length = 0;
}

// ── Chrome ─────────────────────────────────────────────────────────────────────

const chrome = computed(() => ({
    skin:            props.skin,
    timeLeft:        timeLeft.value,
    primaryProgress: 0,
    stability:       1,
    stabilityClass:  '',
    timerClass:      timeLeft.value < 30 ? 'timer--critical' : timeLeft.value < 60 ? 'timer--warn' : '',
    glitchActive:    (rig.value > 55 || environment.value > 55) && !gameResult.value,
    glitchType:      rig.value > 70 ? 'static,bars' : 'scan',
    glitchIntensity: Math.max(rig.value, environment.value) / 300,
    result:          gameResult.value,
    failReason:      failReason.value,
    hideBars:        true,
}));

// ── Lifecycle ──────────────────────────────────────────────────────────────────

onMounted(() => {
    buildPuzzle();
    startAllIntervals();
});

onUnmounted(() => {
    clearAllIntervals();
});
</script>

<style scoped>
/* ══════════════════════════════════════════════════════════════════════════════
   Canvas — CSS Grid: top bar | middle row | bottom bar
══════════════════════════════════════════════════════════════════════════════ */

.ts-canvas {
    width: 1920px;
    height: 100%;
    display: grid;
    grid-template-rows: 64px 1fr 180px;
    font-family: 'JetBrains Mono', monospace;
    background: #04090e;
    color: #00c8f0;
    overflow: hidden;
}

/* ── Top bar ──────────────────────────────────────────────────────────────── */

.ts-top {
    display: flex;
    align-items: center;
    gap: 32px;
    padding: 0 24px;
    background: rgba(0,0,0,0.5);
    border-bottom: 1px solid rgba(0,200,240,0.15);
}

.ts-meter-group {
    display: flex;
    align-items: center;
    gap: 10px;
    flex: 1;
}

.ts-meter-lbl {
    font-size: 9px;
    letter-spacing: 0.18em;
    color: rgba(0,200,240,0.5);
    white-space: nowrap;
    flex-shrink: 0;
    width: 140px;
}

.ts-meter-track {
    flex: 1;
    height: 8px;
    background: rgba(0,200,240,0.08);
    border: 1px solid rgba(0,200,240,0.15);
    position: relative;
    overflow: visible;
}

.ts-meter-fill {
    height: 100%;
    transition: width 0.4s ease;
}

.ts-fill--ni  { background: rgba(0,200,240,0.7); }
.ts-fill--rig { background: rgba(251,146,60,0.7); }
.ts-fill--env { background: rgba(163,230,53,0.7); }

.ts-thresh-mark {
    position: absolute;
    top: -3px;
    bottom: -3px;
    width: 1px;
    background: rgba(255,255,255,0.2);
    pointer-events: none;
}

.ts-meter-val {
    font-size: 11px;
    font-weight: 700;
    letter-spacing: 0.1em;
    min-width: 38px;
    text-align: right;
    color: rgba(0,200,240,0.8);
}

.ts-val--warn { color: #ffaa00 !important; }
.ts-val--crit { color: #ff3333 !important; animation: ts-blink 0.6s step-start infinite; }

/* ── Middle row ───────────────────────────────────────────────────────────── */

.ts-middle {
    display: grid;
    grid-template-columns: 340px 1fr 360px;
    overflow: hidden;
}

/* ── Bottom bar ───────────────────────────────────────────────────────────── */

.ts-bottom {
    display: grid;
    grid-template-columns: 1fr 360px;
    border-top: 1px solid rgba(0,200,240,0.12);
}

/* ── Shared panel ─────────────────────────────────────────────────────────── */

.ts-panel {
    border: 1px solid rgba(251,146,60,0.2);
    display: flex;
    flex-direction: column;
    overflow: hidden;
    min-height: 0;
}

.ts-ph {
    font-size: 9px;
    letter-spacing: 0.2em;
    color: rgba(0,200,240,0.4);
    border-bottom: 1px solid rgba(0,200,240,0.08);
    padding: 6px 12px;
    flex-shrink: 0;
    background: rgba(0,0,0,0.3);
}

/* ── Container 2: Fields panel ────────────────────────────────────────────── */

.ts-fields-panel {
    overflow-y: auto;
}

.ts-io-header {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 6px;
    padding: 4px 12px;
    font-size: 8px;
    letter-spacing: 0.15em;
    color: rgba(0,200,240,0.3);
    border-bottom: 1px solid rgba(0,200,240,0.05);
    flex-shrink: 0;
}

.ts-io-hdr { text-align: right; }

.ts-fid-col { /* spacer */ }

.ts-field-row {
    display: grid;
    grid-template-columns: 1fr auto auto auto;
    gap: 6px;
    align-items: center;
    padding: 7px 12px;
    border-bottom: 1px solid rgba(0,200,240,0.05);
    cursor: pointer;
    transition: background 0.12s;
}

.ts-field-row:hover { background: rgba(0,200,240,0.04); }
.ts-field-row--active { background: rgba(0,200,240,0.08); border-left: 2px solid rgba(0,200,240,0.5); }
.ts-field-row--bleed  { border-left: 2px solid rgba(255,51,51,0.6); }

.ts-fid {
    font-size: 10px;
    letter-spacing: 0.1em;
    color: #00c8f0;
}

.ts-io-val {
    font-size: 9px;
    letter-spacing: 0.06em;
    text-align: right;
    min-width: 80px;
}

.ts-io-sep {
    font-size: 9px;
    color: rgba(0,200,240,0.25);
    padding: 0 2px;
}

.ts-io--green  { color: rgba(0,255,100,0.7); }
.ts-io--yellow { color: rgba(255,200,0,0.7); }
.ts-io--red    { color: rgba(255,60,60,0.8); }
.ts-io--corrupt { color: rgba(255,60,60,0.4); letter-spacing: 0.04em; }

/* ── Container 3: File explorer ───────────────────────────────────────────── */

.ts-explorer-panel { overflow-y: auto; }

.ts-explorer {
    display: flex;
    flex-direction: column;
    padding: 8px 0;
}

.ts-root-path {
    font-size: 9px;
    letter-spacing: 0.12em;
    color: rgba(0,200,240,0.3);
    padding: 4px 14px 8px;
    border-bottom: 1px solid rgba(0,200,240,0.06);
    margin-bottom: 4px;
}

.ts-folder-group { display: flex; flex-direction: column; }

.ts-folder-row {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 6px 14px;
    cursor: pointer;
    transition: background 0.12s;
    border-left: 2px solid transparent;
}
.ts-folder-row:hover { background: rgba(0,200,240,0.04); }
.ts-folder--open  { background: rgba(0,200,240,0.06); border-left-color: rgba(0,200,240,0.4); }
.ts-folder--bleed { border-left-color: rgba(255,51,51,0.6); }
.ts-folder--open.ts-folder--bleed { border-left-color: rgba(255,51,51,0.8); }

.ts-fold-arrow { font-size: 8px; color: rgba(0,200,240,0.4); width: 10px; flex-shrink: 0; }
.ts-fold-icon  { font-size: 11px; flex-shrink: 0; }
.ts-fold-name  { font-size: 10px; letter-spacing: 0.1em; color: #00c8f0; flex: 1; }
.ts-fold-warn  { font-size: 8px; letter-spacing: 0.1em; color: rgba(255,80,80,0.8); flex-shrink: 0; }

.ts-file-row {
    display: flex;
    align-items: center;
    gap: 6px;
    padding: 4px 14px;
    border-bottom: 1px solid rgba(0,200,240,0.03);
    transition: background 0.1s;
}
.ts-file--bleed   { background: rgba(255,51,51,0.03); }
.ts-file--home    { background: rgba(0,255,100,0.02); }
.ts-file--phantom { opacity: 0.4; cursor: default; }

.ts-file-tree  { font-size: 10px; color: rgba(0,200,240,0.2); flex-shrink: 0; font-family: monospace; }
.ts-file-flag  { font-size: 9px; color: rgba(255,80,80,0.8); width: 28px; flex-shrink: 0; letter-spacing: 0; }
.ts-file-name  { font-size: 10px; letter-spacing: 0.06em; color: rgba(0,200,240,0.85); flex: 1; }
.ts-file--bleed .ts-file-name  { color: rgba(255,120,120,0.9); }
.ts-file--home .ts-file-name   { color: rgba(0,255,100,0.75); }
.ts-file--phantom .ts-file-name { color: rgba(255,51,51,0.5); }

.ts-file-aff {
    font-size: 8px;
    letter-spacing: 0.1em;
    white-space: nowrap;
    flex-shrink: 0;
}
.ts-aff--rig         { color: rgba(251,146,60,0.8); }
.ts-aff--environment { color: rgba(163,230,53,0.8); }

.ts-file-status {
    font-size: 8px;
    letter-spacing: 0.08em;
    white-space: nowrap;
    color: rgba(0,200,240,0.4);
    flex-shrink: 0;
    min-width: 140px;
    text-align: right;
}
.ts-file--home .ts-file-status    { color: rgba(0,255,100,0.6); }
.ts-file--bleed .ts-file-status   { color: rgba(255,140,60,0.7); }

/* ── Container 4: Field spec panel ───────────────────────────────────────── */

.ts-expected-panel { overflow-y: auto; }

.ts-spec-summary {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 7px 14px;
    background: rgba(0,0,0,0.3);
    border-bottom: 1px solid rgba(0,200,240,0.08);
    flex-shrink: 0;
}

.ts-spec-label {
    font-size: 8px;
    letter-spacing: 0.18em;
    color: rgba(0,200,240,0.35);
}

.ts-spec-count {
    font-size: 9px;
    font-weight: 700;
    letter-spacing: 0.1em;
}

.ts-sync--full    { color: rgba(0,255,100,0.8); }
.ts-sync--partial { color: rgba(255,170,0,0.8); }
.ts-sync--none    { color: rgba(255,60,60,0.8); }

.ts-spec-col-headers {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    padding: 4px 14px;
    font-size: 8px;
    letter-spacing: 0.16em;
    color: rgba(0,200,240,0.25);
    border-bottom: 1px solid rgba(0,200,240,0.06);
}

.ts-spec-row {
    display: grid;
    grid-template-columns: 1fr auto;
    gap: 8px;
    align-items: center;
    padding: 6px 14px;
    border-bottom: 1px solid rgba(0,200,240,0.04);
}

.ts-spec-name {
    font-size: 10px;
    letter-spacing: 0.06em;
    color: rgba(0,200,240,0.6);
}

.ts-spec--synced .ts-spec-name { color: rgba(0,255,100,0.75); }
.ts-spec--missing .ts-spec-name { color: rgba(0,200,240,0.45); }

.ts-spec-loc {
    font-size: 8px;
    letter-spacing: 0.08em;
    white-space: nowrap;
    text-align: right;
}

.ts-loc--home { color: rgba(0,255,100,0.7); }
.ts-loc--away { color: rgba(255,120,60,0.75); }

.ts-empty {
    padding: 20px 14px;
    font-size: 9px;
    letter-spacing: 0.15em;
    color: rgba(0,200,240,0.2);
}

/* ── Container 5: SPLICE panel ────────────────────────────────────────────── */

.ts-splice-panel {
    padding: 0;
}

.ts-splice-chain {
    display: flex;
    align-items: flex-end;
    gap: 10px;
    padding: 10px 16px;
    flex: 1;
    overflow-x: auto;
}

.ts-splice-step {
    display: flex;
    flex-direction: column;
    gap: 5px;
    flex-shrink: 0;
}

.ts-slbl {
    font-size: 8px;
    letter-spacing: 0.14em;
    color: rgba(0,200,240,0.4);
}

.ts-sel {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.06em;
    background: rgba(0,10,20,0.8);
    border: 1px solid rgba(0,200,240,0.25);
    color: #00c8f0;
    padding: 5px 8px;
    cursor: pointer;
    min-width: 180px;
    outline: none;
    appearance: none;
    -webkit-appearance: none;
}

.ts-sel:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

.ts-sel:focus {
    border-color: rgba(0,200,240,0.6);
}

.ts-sel option {
    background: #04090e;
    color: #00c8f0;
}

.ts-chain-sep {
    font-size: 12px;
    color: rgba(0,200,240,0.3);
    padding-bottom: 8px;
    flex-shrink: 0;
    letter-spacing: 0.1em;
}

.ts-splice-btn {
    font-family: 'JetBrains Mono', monospace;
    font-size: 10px;
    letter-spacing: 0.14em;
    background: transparent;
    border: 1px solid rgba(0,200,240,0.35);
    color: rgba(0,200,240,0.8);
    padding: 7px 18px;
    cursor: pointer;
    flex-shrink: 0;
    align-self: flex-end;
    transition: all 0.15s;
}

.ts-splice-btn:hover:not(:disabled) {
    background: rgba(0,200,240,0.08);
    border-color: rgba(0,200,240,0.7);
    color: #00c8f0;
}

.ts-splice-btn:disabled {
    opacity: 0.25;
    cursor: not-allowed;
}

/* ── Container 6: Action buttons ──────────────────────────────────────────── */

.ts-action-panel {
    border-left: 1px solid rgba(0,200,240,0.1);
}

.ts-action-row {
    display: flex;
    align-items: stretch;
    gap: 0;
    flex: 1;
    padding: 10px 12px;
    gap: 10px;
}

.ts-act-btn {
    font-family: 'JetBrains Mono', monospace;
    flex: 1;
    display: flex;
    flex-direction: column;
    align-items: center;
    justify-content: center;
    gap: 4px;
    border: 1px solid;
    background: transparent;
    cursor: pointer;
    padding: 8px 12px;
    transition: all 0.15s;
    position: relative;
}

.ts-abl {
    font-size: 13px;
    font-weight: 700;
    letter-spacing: 0.2em;
}

.ts-asub {
    font-size: 8px;
    letter-spacing: 0.12em;
    opacity: 0.6;
}

.ts-asub--ready { color: #00ff64; opacity: 1; }

.ts-cool {
    font-size: 9px;
    letter-spacing: 0.1em;
    opacity: 0.5;
    position: absolute;
    bottom: 4px;
    right: 6px;
}

/* SOAK */
.ts-btn--soak {
    color: rgba(251,146,60,0.85);
    border-color: rgba(251,146,60,0.35);
}
.ts-btn--soak:hover:not(:disabled) {
    background: rgba(251,146,60,0.08);
    border-color: rgba(251,146,60,0.7);
}

/* VENT */
.ts-btn--vent {
    color: rgba(163,230,53,0.85);
    border-color: rgba(163,230,53,0.35);
}
.ts-btn--vent:hover:not(:disabled) {
    background: rgba(163,230,53,0.08);
    border-color: rgba(163,230,53,0.7);
}

/* PURGE */
.ts-btn--purge {
    color: rgba(0,200,240,0.6);
    border-color: rgba(0,200,240,0.2);
}
.ts-btn--purge:not(:disabled) {
    color: #00ff64;
    border-color: rgba(0,255,100,0.5);
    animation: ts-purge-pulse 1.2s ease-in-out infinite;
}
.ts-btn--purge:hover:not(:disabled) {
    background: rgba(0,255,100,0.08);
}

.ts-act-btn:disabled {
    opacity: 0.3;
    cursor: not-allowed;
}

/* ── Animations ───────────────────────────────────────────────────────────── */

@keyframes ts-blink {
    0%, 49% { opacity: 1; }
    50%, 100% { opacity: 0.3; }
}

@keyframes ts-purge-pulse {
    0%, 100% { box-shadow: 0 0 0 rgba(0,255,100,0); }
    50%       { box-shadow: 0 0 16px rgba(0,255,100,0.3); }
}
</style>
