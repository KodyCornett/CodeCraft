import { ref, readonly } from 'vue';
import axios from 'axios';

// All seeded district names
const DISTRICTS = [
    'Downtown',
    'South Hill',
    'University District',
    "Browne's Addition",
    'North Spokane',
    'Spokane Valley',
];

/**
 * Derive D3 node state from API node data.
 * Priority: street_doc > scorched (both depleted) > hacked (one depleted) > active
 */
function deriveState(node) {
    if (node.is_street_doc)                                                     return 'street_doc';
    if (node.cred_resource_depleted && node.movement_resource_depleted)         return 'scorched';
    if (node.cred_resource_depleted || node.movement_resource_depleted)         return 'hacked';
    return 'active';
}

/**
 * Map a raw API node to the D3 node format MapCanvas expects.
 */
function toD3Node(apiNode) {
    return {
        id:       apiNode.id,
        label:    apiNode.business_name,
        district: apiNode.district,
        tier:     apiNode.tier,
        state:    deriveState(apiNode),
        // Resource state for game logic
        credDepleted:       apiNode.cred_resource_depleted,
        movementDepleted:   apiNode.movement_resource_depleted,
        credValueBase:      apiNode.cred_value_base      ?? 100,
        credLastHackedAt:   apiNode.cred_last_hacked_at  ?? null,
    };
}

export function useMapData() {
    const nodes   = ref([]);
    const links   = ref([]);
    const loading = ref(false);
    const error   = ref(null);

    async function fetchAll() {
        loading.value = true;
        error.value   = null;

        try {
            const results = await Promise.all(
                DISTRICTS.map(d => axios.get(`/api/nodes/${encodeURIComponent(d)}`)),
            );

            const allNodes = [];
            const linkSet  = new Set(); // deduplicate bidirectional edges
            const linkList = [];

            for (const res of results) {
                const districtName = res.data.district;
                for (const apiNode of res.data.nodes) {
                    allNodes.push(toD3Node({ ...apiNode, district: districtName }));

                    for (const adjId of apiNode.adjacent_node_ids) {
                        const key = [apiNode.id, adjId].sort().join('|');
                        if (!linkSet.has(key)) {
                            linkSet.add(key);
                            linkList.push({ source: apiNode.id, target: adjId });
                        }
                    }
                }
            }

            nodes.value = allNodes;
            links.value = linkList;
        } catch (e) {
            error.value = e?.response?.data?.message ?? e.message ?? 'Failed to load map data';
            console.warn('[useMapData] API error — falling back to empty map:', error.value);
        } finally {
            loading.value = false;
        }
    }

    /**
     * Update a single node's state in-place (called on NODE_STATE_CHANGED events).
     */
    function updateNodeState(nodeId, newState) {
        const node = nodes.value.find(n => n.id === nodeId);
        if (node) node.state = newState;
    }

    /**
     * Patch resource fields on a node after a hack (called on deplete API response).
     */
    function updateNodeResources(nodeId, patch) {
        const node = nodes.value.find(n => n.id === nodeId);
        if (!node) return;
        if (patch.credDepleted     !== undefined) node.credDepleted     = patch.credDepleted;
        if (patch.movementDepleted !== undefined) node.movementDepleted = patch.movementDepleted;
        if (patch.credLastHackedAt !== undefined) node.credLastHackedAt = patch.credLastHackedAt;
        // Re-derive visual state
        node.state = deriveState({
            is_street_doc:              node.state === 'street_doc',
            cred_resource_depleted:     node.credDepleted,
            movement_resource_depleted: node.movementDepleted,
        });
    }

    return {
        nodes:   readonly(nodes),
        links:   readonly(links),
        loading: readonly(loading),
        error:   readonly(error),
        fetchAll,
        updateNodeState,
        updateNodeResources,
    };
}
