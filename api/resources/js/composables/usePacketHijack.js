/**
 * usePacketHijack
 *
 * Manages client-side state for a Packet Hijack PvP match.
 *
 * Responsibilities:
 *   - Terminal command history
 *   - Phase state (1 = recon, 2 = port intrusion)
 *   - Port status matrix
 *   - Honeypot lock countdown
 *   - Laravel Echo WebSocket listeners for all PH events
 *   - Command dispatch to POST /api/packet-hijack/{match}/command
 *
 * No game math lives here. All computation is server-authoritative.
 * Components receive refs and call submitCommand() — that is all.
 */

import { ref, readonly, computed } from 'vue';
import axios from 'axios';

export function usePacketHijack(playerId) {

    // ── State ─────────────────────────────────────────────────────────────────

    const matchId            = ref(null);
    const role               = ref(null);       // 'challenger' | 'defender'
    const phase              = ref(1);
    const commandHistory     = ref([]);         // [{ input, lines[], ts }]
    const ports              = ref([]);          // Phase 2 port status matrix
    const targetIp           = ref(null);        // revealed on Phase 2 start
    const isLocked           = ref(false);
    const lockUntil          = ref(null);        // Date object
    const lockCountdown      = ref(0);           // seconds remaining
    const defenderAlertActive = ref(false);
    const matchResult        = ref(null);        // { isWinner, credsStolen } on complete
    const busy               = ref(false);
    const usedRigCommands    = ref([]);     // slugs of rig commands fired this match
    const echoChannel        = ref(null);

    let lockTimer = null;

    // ── Derived ───────────────────────────────────────────────────────────────

    const isComplete = computed(() => matchResult.value !== null);

    // ── Init — called when PacketHijackStarted event fires ───────────────────

    function init(mId, playerRole, ipPoolSample) {
        matchId.value  = mId;
        role.value     = playerRole;
        phase.value    = 1;

        // Show the opening netstat sample as the first terminal output
        _appendHistory(
            'netstat --active',
            _formatNetstatLines(ipPoolSample)
        );

        _subscribeToMatch(mId);
    }

    // ── Echo listeners ────────────────────────────────────────────────────────

    function _subscribeToMatch(mId) {
        if (!window.Echo) return;

        const pid = typeof playerId === 'object' ? playerId.value : playerId;
        if (!pid) return;

        echoChannel.value = window.Echo.private(`player.${pid}`)
            .listen('.packet-hijack.command-result', _onCommandResult)
            .listen('.packet-hijack.phase-transition', _onPhaseTransition)
            .listen('.packet-hijack.complete', _onMatchComplete);
    }

    function unsubscribe() {
        // Stop listening to PH events on the player channel, but do NOT call
        // Echo.leave() — Game.vue holds a packet-hijack.started listener on the
        // same channel and must stay alive for subsequent matches.
        const pid = typeof playerId === 'object' ? playerId.value : playerId;
        if (pid && window.Echo && echoChannel.value) {
            window.Echo.private(`player.${pid}`)
                .stopListening('.packet-hijack.command-result')
                .stopListening('.packet-hijack.phase-transition')
                .stopListening('.packet-hijack.complete');
        }
        echoChannel.value = null;
    }

    function _onCommandResult(data) {
        if (data.match_id !== matchId.value) return;

        _appendHistory(data.command, data.output_lines ?? []);

        if (data.updated_ports) {
            ports.value = data.updated_ports;
        }

        if (data.lock_until) {
            _applyLock(new Date(data.lock_until));
        }

        if (data.phase_advanced) {
            phase.value = 2;
        }
    }

    function _onPhaseTransition(data) {
        if (data.match_id !== matchId.value) return;

        if (data.alert_only) {
            // Opponent has entered Phase 2 — show the defender alert
            defenderAlertActive.value = true;
            setTimeout(() => { defenderAlertActive.value = false; }, 6000);
        } else {
            // We advanced to Phase 2
            phase.value    = 2;
            ports.value    = data.ports ?? [];
            targetIp.value = data.target_ip ?? null;

            _appendHistory('SYSTEM', [
                '[ALERT]: PIVOTING TO TARGET DEFENSIVE TOPOLOGY... CHASSIS LOCKED.',
                '[PORT STATUS MATRIX INITIALISED]',
            ]);
        }
    }

    function _onMatchComplete(data) {
        if (data.match_id !== matchId.value) return;

        matchResult.value = {
            isWinner:    data.is_winner,
            credsStolen: data.creds_stolen,
            winnerId:    data.winner_id,
            loserId:     data.loser_id,
        };
    }

    // ── Command dispatch ──────────────────────────────────────────────────────

    /**
     * Submit a raw terminal input string to the server.
     * Client-side pre-validation catches obvious typos so the player gets
     * instant feedback without a round-trip for clearly bad input.
     */
    async function submitCommand(rawInput) {
        if (!matchId.value || busy.value || isLocked.value) return;

        const trimmed = rawInput.trim();
        if (!trimmed) return;

        // Client-side pre-validation: check first token is a known command
        const knownCommands = ['netstat', 'sniff', 'isolate', 'inject', 'scan', 'exploit', 'malware'];
        const firstToken    = trimmed.split(/\s+/)[0].toLowerCase();

        if (!knownCommands.includes(firstToken)) {
            _appendHistory(trimmed, [`[ERROR]: COMMAND NOT FOUND: ${firstToken.toUpperCase()}`]);
            return;
        }

        busy.value = true;
        try {
            await axios.post(`/api/packet-hijack/${matchId.value}/command`, {
                input: trimmed,
            });
            // Output arrives via WebSocket — no response body processing needed
        } catch (e) {
            const status = e?.response?.status;
            if (status === 409) {
                _appendHistory(trimmed, ['[ERROR]: TARGET ALREADY PURGED']);
            } else if (status === 429) {
                // Locked — server returned remaining lock time
                const lockData = e?.response?.data;
                if (lockData?.lock_until) _applyLock(new Date(lockData.lock_until));
            } else {
                _appendHistory(trimmed, ['[SYSTEM ERROR]: CONNECTION INTERRUPTED']);
            }
        } finally {
            busy.value = false;
        }
    }

    /**
     * Fire an equipped rig command by slug.
     * Tracks used slugs locally so the UI can grey out spent commands immediately.
     * Actual output arrives via WebSocket.
     */
    async function submitRigCommand(slug) {
        if (!matchId.value || busy.value || isLocked.value) return;
        if (usedRigCommands.value.includes(slug)) return;

        const displaySlug = slug.replace(/_/g, ' ').toUpperCase();
        _appendHistory(`[RIG] ${displaySlug}`, [`[ DEPLOYING: ${displaySlug} ]`]);

        // Optimistic mark — prevents double-fire before server confirms
        usedRigCommands.value = [...usedRigCommands.value, slug];

        busy.value = true;
        try {
            await axios.post(`/api/packet-hijack/${matchId.value}/command`, {
                rig_command: slug,
            });
            // Output arrives via WebSocket
        } catch (e) {
            const status = e?.response?.status;
            if (status === 409) {
                _appendHistory(`[RIG] ${displaySlug}`, ['[ERROR]: COMMAND ALREADY DEPLOYED THIS MATCH']);
            } else if (status === 422) {
                // Validation failed (command not equipped, wrong context, etc.)
                const msg = e?.response?.data?.message ?? 'COMMAND UNAVAILABLE';
                _appendHistory(`[RIG] ${displaySlug}`, [`[ERROR]: ${msg.toUpperCase()}`]);
                // Revert optimistic mark so player can retry if it was a transient error
                usedRigCommands.value = usedRigCommands.value.filter(s => s !== slug);
            } else {
                _appendHistory(`[RIG] ${displaySlug}`, ['[SYSTEM ERROR]: CONNECTION INTERRUPTED']);
                usedRigCommands.value = usedRigCommands.value.filter(s => s !== slug);
            }
        } finally {
            busy.value = false;
        }
    }

    // ── Lock management ───────────────────────────────────────────────────────

    function _applyLock(until) {
        lockUntil.value = until;
        isLocked.value  = true;
        _tickLock();
    }

    function _tickLock() {
        clearInterval(lockTimer);
        lockTimer = setInterval(() => {
            const remaining = Math.ceil((lockUntil.value - Date.now()) / 1000);
            if (remaining <= 0) {
                lockCountdown.value = 0;
                isLocked.value      = false;
                lockUntil.value     = null;
                clearInterval(lockTimer);
            } else {
                lockCountdown.value = remaining;
            }
        }, 200);
    }

    // ── History helpers ───────────────────────────────────────────────────────

    /**
     * Push a new history entry and stream its lines in with ~80ms spacing
     * to produce a typewriter effect in the terminal pane.
     */
    function _appendHistory(input, lines) {
        const entry = { input, lines: [], ts: Date.now() };
        commandHistory.value.push(entry);
        _streamLines(entry, lines ?? []);
    }

    function _streamLines(entry, lines) {
        let i = 0;
        function tick() {
            if (i >= lines.length) return;
            entry.lines.push(lines[i]);
            i++;
            setTimeout(tick, 80);
        }
        tick();
    }

    function _formatNetstatLines(sample) {
        const lines = [
            '[PROCESSING SCAN UTILITY...]',
            `[SUCCESS]: INITIAL SAMPLING ISOLATED ${sample.length} RELEVANT IP TARGETS:`,
        ];
        const chunks = [];
        for (let i = 0; i < sample.length; i += 4) {
            chunks.push(sample.slice(i, i + 4).map(ip => ip.padEnd(16)).join('   '));
        }
        return [...lines, ...chunks];
    }

    // ── Cleanup ───────────────────────────────────────────────────────────────

    function destroy() {
        clearInterval(lockTimer);
        unsubscribe();
    }

    return {
        // State (readonly to components)
        matchId:             readonly(matchId),
        role:                readonly(role),
        phase:               readonly(phase),
        commandHistory:      readonly(commandHistory),
        ports:               readonly(ports),
        targetIp:            readonly(targetIp),
        isLocked:            readonly(isLocked),
        lockCountdown:       readonly(lockCountdown),
        defenderAlertActive: readonly(defenderAlertActive),
        matchResult:         readonly(matchResult),
        isComplete,
        busy:                readonly(busy),
        usedRigCommands:     readonly(usedRigCommands),
        // Actions
        init,
        submitCommand,
        submitRigCommand,
        destroy,
    };
}
