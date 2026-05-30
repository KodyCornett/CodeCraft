/**
 * useNodePresence
 *
 * Subscribes to a Reverb presence channel (node.{canvasId}) when the player
 * is standing on a node. The here/joining/leaving callbacks replace the
 * former 3-second polling loop.
 *
 * The channel auth in routes/channels.php returns a member object with the
 * same shape that NodeInfoBlock expects, so no transformation is needed here.
 *
 * Used by NodeInfoBlock to show the PLAYERS section and [HACK] button.
 */

import { ref, watch, onUnmounted } from 'vue';

export function useNodePresence(currentNodeIdRef, playerIdRef) {
    const nodePlayers = ref([]);
    const polling     = ref(false); // kept for API compatibility — always false with Reverb

    let _channel        = null;
    let _currentCanvasId = null;

    function leaveChannel() {
        if (_currentCanvasId) {
            if (window.Echo) window.Echo.leave(`node.${_currentCanvasId}`);
            _channel         = null;
            _currentCanvasId = null;
        }
        nodePlayers.value = [];
    }

    function joinChannel(canvasId) {
        if (!canvasId || !playerIdRef?.value) return;
        if (canvasId === _currentCanvasId)    return;
        if (!window.Echo)                     return;

        leaveChannel();
        _currentCanvasId = canvasId;

        _channel = window.Echo.join(`node.${canvasId}`)
            .here((members) => {
                // Full member list on initial join — exclude self
                nodePlayers.value = members.filter(m => m.id !== playerIdRef.value);
            })
            .joining((member) => {
                // Another player arrived — add if not already present
                if (member.id === playerIdRef.value) return;
                if (!nodePlayers.value.find(p => p.id === member.id)) {
                    nodePlayers.value = [...nodePlayers.value, member];
                }
            })
            .leaving((member) => {
                // A player left — remove them
                nodePlayers.value = nodePlayers.value.filter(p => p.id !== member.id);
            })
            .listen('.combat.state.changed', ({ player_id, in_combat }) => {
                // Patch in_combat live so the [HACK] button hides during active fights
                nodePlayers.value = nodePlayers.value.map(p =>
                    p.id === player_id ? { ...p, in_combat } : p
                );
            })
            .error((error) => {
                console.warn('[useNodePresence] Channel error:', error);
                // Reset so a subsequent node move can retry the join
                _channel         = null;
                _currentCanvasId = null;
                nodePlayers.value = [];
            });
    }

    // Watch both refs together so that if currentNodeId is already set when
    // playerId becomes available (common on fresh spawn), the join still fires.
    watch([currentNodeIdRef, playerIdRef], ([newId, pid]) => {
        if (newId && pid) joinChannel(newId);
        else if (!newId)  leaveChannel();
    });

    onUnmounted(leaveChannel);

    return { nodePlayers, polling };
}
