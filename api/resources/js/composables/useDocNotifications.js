/**
 * useDocNotifications
 *
 * Watches the quest log for new events and queues HUD notifications.
 * Each notification is a brief identifiable alert — distinct from the Watcher interrupt.
 *
 * Notification shape:
 * {
 *   id:       string,       // unique key
 *   docName:  string,       // e.g. "Knuckle's Med-Wagon"
 *   handle:   string,       // e.g. "KNUCKLE"
 *   color:    string,       // doc accent colour
 *   message:  string,       // short display line
 *   type:     string,       // 'arc_unlocked' | 'referral' | 'stage_active'
 * }
 *
 * Notifications auto-dismiss after DISPLAY_MS milliseconds.
 */

import { ref, readonly } from 'vue';
import { docColorByName } from '@/constants/docColors.js';

const DISPLAY_MS = 5000;

export function useDocNotifications() {
    const queue   = ref([]);  // active notifications shown in HUD
    const seen    = new Set(); // event IDs already notified this session

    /**
     * Feed new quest log events in.
     * Called after fetchQuestLog() resolves with fresh data.
     * Compares against seen set to only surface truly new events.
     */
    function processEvents(events) {
        for (const event of events) {
            if (seen.has(event.id)) continue;
            seen.add(event.id);

            const note = buildNotification(event);
            if (!note) continue;

            queue.value.push(note);
            setTimeout(() => dismiss(note.id), DISPLAY_MS);
        }
    }

    function dismiss(id) {
        queue.value = queue.value.filter(n => n.id !== id);
    }

    function buildNotification(event) {
        const { event_type, payload, id } = event;

        if (event_type === 'arc_unlocked') {
            const handle = docHandleFromName(payload.doc_name);
            return {
                id,
                docName: payload.doc_name,
                handle,
                color:   colorFromName(payload.doc_name),
                message: `${payload.arc_title} — NEW MISSION`,
                type:    'arc_unlocked',
            };
        }

        if (event_type === 'referral') {
            return {
                id,
                docName: payload.referral_doc_name,
                handle:  docHandleFromName(payload.referral_doc_name),
                color:   colorFromName(payload.referral_doc_name),
                message: payload.referral_text,
                type:    'referral',
            };
        }

        // No notification for stage_complete, branch_choice, watcher_signal —
        // those are handled by other systems (archive log, Watcher interrupt).
        return null;
    }

    // ── Helpers ───────────────────────────────────────────────────────────────

    function docHandleFromName(name) {
        // Extract first word and uppercase — "Knuckle's Med-Wagon" → "KNUCKLE"
        const match = name?.match(/^([A-Za-z]+)/);
        return match ? match[1].toUpperCase() : name?.toUpperCase() ?? 'UNKNOWN';
    }

    function colorFromName(name) {
        return docColorByName(name);
    }

    return {
        queue:         readonly(queue),
        processEvents,
        dismiss,
    };
}
