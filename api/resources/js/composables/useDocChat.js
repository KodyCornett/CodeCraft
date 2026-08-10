/**
 * useDocChat
 *
 * Owns one DOC hub's live chat room: joins/leaves the Reverb private channel
 * (doc-chat.{hubCanvasId}), fetches history, and posts new messages.
 *
 * Generic across all 5 CyberDocs — the page component passes in which hub to
 * connect to and whether the room should currently be live. Access is
 * server-enforced (see DocChatService::playerIsAtHub / routes/channels.php);
 * this composable just reflects whatever the server allows.
 *
 * Mirrors the join/leave lifecycle useNodePresence.js already uses for
 * per-node Reverb presence channels.
 *
 * @param {import('vue').Ref<string|null>}  hubCanvasIdRef  which hub's room to connect to
 * @param {import('vue').Ref<string|null>}  playerIdRef     current player id
 * @param {import('vue').Ref<boolean>}      enabledRef      true when the room should be joined
 *                                                           (e.g. enableChat prop AND atCyberDoc confirmed)
 */

import { ref, watch, onUnmounted } from 'vue';
import axios from 'axios';

export function useDocChat(hubCanvasIdRef, playerIdRef, enabledRef) {
    const messages = ref([]);
    const loading   = ref(false);
    const sending   = ref(false);
    const error     = ref(null);

    let _currentHub = null;

    function _resetState() {
        messages.value = [];
        error.value    = null;
    }

    function _upsert(message) {
        if (!message?.id) return;
        if (messages.value.some(m => m.id === message.id)) return;
        messages.value = [...messages.value, message];
    }

    async function fetchHistory(hubCanvasId) {
        loading.value = true;
        try {
            const res = await axios.get(`/api/doc-chat/${hubCanvasId}/messages`);
            messages.value = res.data.messages ?? [];
        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Failed to load transmissions.';
        } finally {
            loading.value = false;
        }
    }

    function leaveChannel() {
        if (_currentHub && window.Echo) {
            window.Echo.leave(`doc-chat.${_currentHub}`);
        }
        _currentHub = null;
    }

    function joinChannel(hubCanvasId) {
        if (!hubCanvasId || hubCanvasId === _currentHub || !window.Echo) return;
        leaveChannel();
        _currentHub = hubCanvasId;

        window.Echo.private(`doc-chat.${hubCanvasId}`)
            .listen('.doc-chat.message', (payload) => _upsert(payload))
            .error((err) => {
                console.warn('[useDocChat] Channel error:', err);
                _currentHub = null;
            });
    }

    async function send(body) {
        const trimmed = (body ?? '').trim();
        if (!trimmed || !_currentHub || sending.value) return;

        sending.value = true;
        error.value   = null;
        try {
            const res = await axios.post(`/api/doc-chat/${_currentHub}/messages`, { body: trimmed });
            // Optimistic push from the response — the broadcast echo will also
            // arrive via the channel listener, but _upsert() dedupes on id.
            _upsert(res.data.message);
        } catch (e) {
            error.value = e?.response?.data?.message ?? 'Message failed to send.';
        } finally {
            sending.value = false;
        }
    }

    watch([hubCanvasIdRef, enabledRef, playerIdRef], ([hub, enabled, pid]) => {
        if (enabled && hub && pid) {
            if (hub === _currentHub) return;
            _resetState();
            joinChannel(hub);
            fetchHistory(hub);
        } else {
            leaveChannel();
            _resetState();
        }
    }, { immediate: true });

    onUnmounted(leaveChannel);

    return { messages, loading, sending, error, send };
}
