/**
 * Minigame pool registry — the node-hack generator's source of truth for
 * which templates exist and how they're weighted.
 *
 * Each entry:
 *   key       string    Stable identifier for this template. This is the
 *                        seam a future server-side picker would key off if
 *                        selection ever moves server-side — nothing about
 *                        the shape below has to change for that.
 *   component Function  Async import — keeps every template out of the main
 *                        bundle until the generator actually picks it.
 *   label     string    Human-readable name — dev/debug display only, never
 *                        shown to players.
 *   weight    number    Relative selection weight in generateMinigame()
 *                        (higher = picked more often). Defaults to 1.
 *
 * Adding a template is adding one entry here — nothing else in the pool
 * layer (generateMinigame.js, HackMinigame.vue) needs to change.
 */
export const MINIGAME_POOL = [
    {
        key:       'grid_breach',
        component: () => import('@/components/minigame/GridBreach.vue'),
        label:     'Grid Breach',
        weight:    1,
    },
    {
        key:       'checksum_breach',
        component: () => import('./templates/ChecksumBreach.vue'),
        label:     'Checksum Breach',
        weight:    1,
    },
    {
        key:       'cipher_breach',
        component: () => import('./templates/CipherBreach.vue'),
        label:     'Cipher Breach',
        weight:    1,
    },
];

/** Look up a pool entry by key. Returns null if the key isn't registered. */
export function findPoolEntry(key) {
    return MINIGAME_POOL.find(entry => entry.key === key) ?? null;
}
