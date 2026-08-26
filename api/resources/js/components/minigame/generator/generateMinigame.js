/**
 * generateMinigame — the node-hack minigame generator.
 *
 * This is deliberately built like a procedural map generator: given a
 * context (which node, which resource), it decides WHICH template plays
 * and returns a plain data spec — not a rendered game. It never imports
 * Vue, never touches gameplay state, and knows nothing about how any
 * template actually looks or plays. HackMinigame.vue is the separate
 * "renderer" half — it reads the key this returns, resolves it against the
 * pool registry, and mounts the matching component.
 *
 * Selection is safe to run purely client-side: reward math never looks at
 * which template ran. NodeController::deplete() only ever sees `resource`
 * and the completionPct a template reports when it finishes — the same
 * contract GridBreach already honors — so which pool entry got picked
 * isn't an authority boundary worth spending a server round-trip on.
 */
import { MINIGAME_POOL } from './pool.js';

// Last key picked this session, module-level on purpose — one hack flow
// runs per client at a time. Used only to avoid an immediate back-to-back
// repeat once the pool has more than one real choice to make.
let lastKey = null;

function weightedPick(entries) {
    const total = entries.reduce((sum, entry) => sum + (entry.weight ?? 1), 0);
    let roll = Math.random() * total;
    for (const entry of entries) {
        roll -= (entry.weight ?? 1);
        if (roll <= 0) return entry;
    }
    return entries[entries.length - 1];
}

/**
 * @param {Object} ctx
 * @param {Object} ctx.node      Node being hacked — already carries the
 *                                effective `ice` Game.vue computed for it.
 * @param {String} ctx.resource  'creds' | 'tech' | 'uplink'
 * @returns {{ key: String, node: Object, resource: String }}
 *          A generation spec — HackMinigame.vue's input, not a game.
 */
export function generateMinigame({ node, resource }) {
    if (MINIGAME_POOL.length === 0) {
        throw new Error('[minigame-generator] Pool is empty — nothing to generate.');
    }

    // Once there's a real choice, don't hand back the same template twice
    // in a row. With a single-entry pool this filter is a no-op.
    const candidates = MINIGAME_POOL.length > 1
        ? MINIGAME_POOL.filter(entry => entry.key !== lastKey)
        : MINIGAME_POOL;

    const picked = weightedPick(candidates);
    lastKey = picked.key;

    return { key: picked.key, node, resource };
}
