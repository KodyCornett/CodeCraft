<template>
    <div class="sys-page">
        <header class="sys-header">
            <span class="sys-title">▣ INVENTORY</span>
            <span class="sys-sub">{{ allItems.length }} ITEM{{ allItems.length !== 1 ? 'S' : '' }}</span>
        </header>

        <div class="sys-body">
            <div v-if="allItems.length === 0" class="inv-empty">
                // INVENTORY CLEAR — NO ITEMS CARRIED
            </div>

            <div v-else class="inv-list">
                <div
                    v-for="item in allItems"
                    :key="item.id"
                    class="inv-row"
                    :class="`inv-row--${item.category}`"
                >
                    <div class="inv-row-top">
                        <span class="inv-tag">{{ categoryLabel(item.category) }}</span>
                        <span class="inv-name">{{ item.name }}</span>
                        <span class="inv-qty">×{{ item.quantity }}</span>
                    </div>
                    <div class="inv-desc">{{ item.detail }}</div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { inject, ref, computed } from 'vue';

defineProps({ url: { type: String, default: '' } });

const gameState = inject('gameState', null);
const inventory = gameState?.inventory ?? ref({ hardware: [], consumables: [] });

/**
 * Flatten both inventory buckets into a single list with a uniform display shape:
 *   id        — unique key for v-for
 *   category  — 'hardware' | 'software' | 'repair'
 *   name      — display name
 *   detail    — stat boost / duration summary line
 *   quantity  — stack count (hardware always 1 per encrypt)
 */
const allItems = computed(() => {
    const hardware = (inventory.value.hardware ?? []).map(h => ({
        id:       h.encrypt_id,
        category: 'hardware',
        name:     h.name,
        detail:   `${(h.stat ?? '').toUpperCase()} +${h.boost}  ·  ${h.port_cost} PORT${h.port_cost !== 1 ? 'S' : ''}  ·  ${(h.rarity ?? '').toUpperCase()}`,
        quantity: 1,
    }));

    const consumables = (inventory.value.consumables ?? []).map(c => {
        const durationNote = c.duration_moves ? `  ·  ${c.duration_moves} MOVES` : '  ·  INSTANT';
        return {
            id:       c.consumable_id,
            category: c.category,      // 'software' | 'repair'
            name:     c.name,
            detail:   `${(c.stat ?? '').toUpperCase()} +${c.boost}${durationNote}  ·  ${(c.rarity ?? '').toUpperCase()}`,
            quantity: c.quantity,
        };
    });

    return [...hardware, ...consumables];
});

function categoryLabel(cat) {
    if (cat === 'hardware') return 'HARDWARE';
    if (cat === 'software') return 'SOFTWARE';
    if (cat === 'repair')   return 'REPAIR';
    return (cat ?? 'ITEM').toUpperCase();
}
</script>

<style scoped>
.sys-page {
    display: flex;
    flex-direction: column;
    height: 100%;
    background: #06060d;
    font-family: 'JetBrains Mono', monospace;
    overflow: hidden;
}

.sys-header {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 14px 24px 12px;
    border-bottom: 1px solid rgba(0,255,255,0.1);
    flex-shrink: 0;
}
.sys-title { font-size: 13px; color: #00FFFF; letter-spacing: 0.1em; text-shadow: 0 0 10px rgba(0,255,255,0.3); }
.sys-sub   { font-size: 9px;  color: rgba(0,255,255,0.3); letter-spacing: 0.1em; }

.sys-body {
    flex: 1;
    overflow-y: auto;
    padding: 16px 24px;
}
.sys-body::-webkit-scrollbar       { width: 2px; }
.sys-body::-webkit-scrollbar-track { background: transparent; }
.sys-body::-webkit-scrollbar-thumb { background: rgba(0,255,255,0.1); }

.inv-empty {
    font-size: 10px;
    color: rgba(0,255,255,0.25);
    letter-spacing: 0.08em;
    padding: 40px 0;
    text-align: center;
}

.inv-list {
    display: flex;
    flex-direction: column;
    gap: 8px;
}

.inv-row {
    padding: 12px 16px;
    border: 1px solid rgba(0,255,255,0.1);
    background: rgba(0,255,255,0.02);
    display: flex;
    flex-direction: column;
    gap: 6px;
    transition: border-color 0.15s;
}
.inv-row:hover { border-color: rgba(0,255,255,0.22); }

/* Category tints */
.inv-row--hardware  { border-color: rgba(255,179,0,0.12); }
.inv-row--hardware:hover  { border-color: rgba(255,179,0,0.3); }
.inv-row--hardware  .inv-tag { color: rgba(255,179,0,0.7); border-color: rgba(255,179,0,0.3); }

.inv-row--software  { border-color: rgba(125,249,255,0.12); }
.inv-row--software:hover  { border-color: rgba(125,249,255,0.3); }
.inv-row--software  .inv-tag { color: rgba(125,249,255,0.7); border-color: rgba(125,249,255,0.3); }

.inv-row--repair    { border-color: rgba(0,255,136,0.12); }
.inv-row--repair:hover    { border-color: rgba(0,255,136,0.3); }
.inv-row--repair    .inv-tag { color: rgba(0,255,136,0.6); border-color: rgba(0,255,136,0.3); }

/* Legacy alias kept for backward compat */
.inv-row--consumable { border-color: rgba(0,255,136,0.12); }
.inv-row--consumable:hover { border-color: rgba(0,255,136,0.3); }

.inv-row-top {
    display: flex;
    align-items: center;
    gap: 10px;
}

.inv-tag {
    font-size: 7px;
    letter-spacing: 0.14em;
    color: rgba(0,255,136,0.5);
    border: 1px solid rgba(0,255,136,0.25);
    padding: 1px 5px;
    flex-shrink: 0;
}
.inv-row--consumable .inv-tag { color: rgba(0,255,136,0.6); border-color: rgba(0,255,136,0.3); }

.inv-name {
    font-size: 11px;
    color: rgba(255,255,255,0.85);
    letter-spacing: 0.04em;
    flex: 1;
}

.inv-qty {
    font-size: 12px;
    color: #00FFFF;
    letter-spacing: 0.06em;
    flex-shrink: 0;
}
.inv-stack {
    font-size: 9px;
    color: rgba(0,255,255,0.35);
}

.inv-desc {
    font-size: 9px;
    color: rgba(0,255,255,0.4);
    letter-spacing: 0.03em;
    line-height: 1.5;
}
</style>
