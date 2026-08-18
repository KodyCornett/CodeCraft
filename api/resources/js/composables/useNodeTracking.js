/**
 * useNodeTracking
 *
 * Backs the Splice Site map page (splice://maps.spk) and the tracked-marker
 * layer on HexMapCanvas.vue. State is module-level and shared across every
 * caller — same pattern as useCodex.js — so a pin placed from the browser
 * page is immediately visible on the map with no prop drilling between
 * InGameBrowser and Game.vue.
 *
 * Session-only by design: trackedMarkers lives in memory only and clears on
 * reload/logout. No new backend table or endpoint — this is a pure
 * client-side convenience layer on top of data the server already exposes
 * via GET /api/nodes. If persistence is wanted later, that's a separate
 * follow-up (a small player_tracked_nodes table + endpoint), not a change
 * to this file's shape.
 *
 * Maintains its own small node cache rather than reading Game.vue's
 * useMapData() instance: useMapData's state is created fresh per call (not
 * a singleton), and SPLICE browser pages have no prop path down from
 * Game.vue — every other page that needs server data fetches it itself
 * (see useCodex.js). Duplicating the GET /api/nodes call is cheap (228
 * small rows, no adjacency/geometry needed here) and keeps this feature
 * fully self-contained — nothing else in the codebase has to change for it
 * to work.
 *
 * Also backs QuestLog.vue's "LEAD:" line — once a stage with a
 * node_canvas_id goes active, the Mission Log shows that node's name and
 * address (read-only, no auto-track button) so the player has to actually
 * go to Splice Maps and search it themselves rather than one-clicking it.
 */

import { ref, readonly } from 'vue';
import axios from 'axios';
import { getNodeIdentity, searchNodes as searchNodeList } from './useNodeIdentity.js';

// Cap on simultaneously tracked pins — keeps the map legible. Oldest pin
// drops off when a new one pushes past the cap. Easy to raise later if it
// turns out to feel too tight in play.
const MAX_TRACKED = 8;

// Pin color — deliberately distinct from docColors.js's quest-marker
// palette so a self-found lead never reads as a quest objective at a glance.
const TRACK_COLOR = '#00E5FF';

const nodeCache      = ref([]);   // lazily-fetched, cached copy of the node list
const cacheLoading   = ref(false);
const cacheError     = ref(null);
const trackedMarkers = ref([]);   // [{ canvasId, color, label }] — feeds HexMapCanvas

export function useNodeTracking() {
    async function ensureNodesLoaded() {
        if (nodeCache.value.length || cacheLoading.value) return;
        cacheLoading.value = true;
        cacheError.value   = null;
        try {
            const res = await axios.get('/api/nodes');
            nodeCache.value = (res.data.nodes ?? []).map(n => ({
                id:            n.id,
                canvasId:      n.canvas_id,
                type:          n.type,
                district:      n.district,
                spliceAddress: n.splice_address ?? null,
            }));
        } catch (e) {
            cacheError.value = e?.response?.data?.message ?? e.message ?? 'Node directory unavailable';
            console.warn('[useNodeTracking] node fetch failed:', cacheError.value);
        } finally {
            cacheLoading.value = false;
        }
    }

    /**
     * Search the cached node list by address or name (see
     * useNodeIdentity.js's searchNodes for the matching rules). Loads the
     * cache lazily on first call. CyberDoc hubs are excluded — they have
     * their own identity/UI and aren't something a player "tracks".
     *
     * Returns [] while the cache is loading or on fetch failure — callers
     * should check cacheLoading/cacheError to distinguish "no matches"
     * from "not ready yet".
     */
    async function search(query) {
        await ensureNodesLoaded();
        if (cacheError.value) return [];
        return searchNodeList(nodeCache.value, query)
            .filter(({ node }) => node.type === 'action')
            .slice(0, 20);
    }

    /**
     * Resolve a single node's identity by canvas ID — used by QuestLog.vue
     * to show a stage's target node as a "LEAD: <name> — <address>" line.
     * Loads the cache lazily on first call, same as search(). Returns null
     * while loading, on fetch failure, or if the canvasId doesn't match
     * anything (shouldn't happen for a real stage target, but a quest
     * pointing at a since-removed/renamed node shouldn't crash the log).
     */
    async function getIdentityByCanvasId(canvasId) {
        await ensureNodesLoaded();
        if (cacheError.value || !canvasId) return null;
        const node = nodeCache.value.find(n => n.canvasId === canvasId);
        return node ? { node, identity: getNodeIdentity(node) } : null;
    }

    function isTracked(canvasId) {
        return trackedMarkers.value.some(m => m.canvasId === canvasId);
    }

    /**
     * Pin a node on the map. label is whatever the player searched by
     * (shown in the tracked list on the map page) — purely cosmetic.
     */
    function trackNode(node, label) {
        if (isTracked(node.canvasId)) return;
        const next = [
            ...trackedMarkers.value,
            { canvasId: node.canvasId, color: TRACK_COLOR, label: label ?? node.canvasId },
        ];
        trackedMarkers.value = next.length > MAX_TRACKED
            ? next.slice(next.length - MAX_TRACKED)
            : next;
    }

    function untrackNode(canvasId) {
        trackedMarkers.value = trackedMarkers.value.filter(m => m.canvasId !== canvasId);
    }

    function clearTracked() {
        trackedMarkers.value = [];
    }

    return {
        trackedMarkers: readonly(trackedMarkers),
        cacheLoading:   readonly(cacheLoading),
        cacheError:     readonly(cacheError),
        maxTracked:     MAX_TRACKED,
        search,
        getIdentityByCanvasId,
        trackNode,
        untrackNode,
        isTracked,
        clearTracked,
    };
}
