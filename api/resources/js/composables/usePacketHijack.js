/**
 * usePacketHijack
 *
 * Manages client-side state for a Packet Hijack PvP match.
 *
 * Responsibilities:
 *   - Terminal command history
 *   - Phase state (1 = recon, 2 = port intrusion)
 *   - Suspects case file (Phase 1 board)
 *   - Port status matrix (Phase 2)
 *   - Honeypot lock countdown
 *   - Laravel Echo WebSocket listeners for all PH events
 *   - Command dispatch to POST /api/packet-hijack/{match}/command
 *
 * No game math lives here. All computation is server-authoritative.
 */

import { ref, readonly, computed, reactive } from 'vue';
import axios from 'axios';

export function usePacketHijack(playerId) {

    // ── State ─────────────────────────────────────────────────────────────────

    const matchId             = ref(null);
    const role                = ref(null);
    const phase               = ref(1);
    const commandHistory      = ref([]);
    const suspects            = ref([]);       // Phase 1 case file — array of suspect objects
    const octetClue           = ref(null);     // latest sniff result e.g. '.4.'
    const ports               = ref([]);       // Phase 2 port matrix
    const targetIp            = ref(null);     // revealed on Phase 2 start
    const isLocked            = ref(false);
    const lockUntil           = ref(null);
    const lockCountdown       = ref(0);
    const defenderAlertActive = ref(false);
    const matchResult         = ref(null);
    const busy                = ref(false);
    const usedRigCommands     = ref([]);
    const echoChannel         = ref(null);
    const boardReady          = ref(false);    // true after netstat populates suspects

    let lockTimer = null;

    // ── Derived ───────────────────────────────────────────────────────────────

    const isComplete        = computed(() => matchResult.value !== null);
    const activeSuspectCount = computed(() =>
        suspects.value.filter(s => !s.flushed).length
    );

    // ── Init ──────────────────────────────────────────────────────────────────

    function init(mId, playerRole) {
        matchId.value             = mId;
        role.value                = playerRole;
        phase.value               = 1;
        commandHistory.value      = [];
        suspects.value            = [];
        octetClue.value           = null;
        ports.value               = [];
        targetIp.value            = null;
        isLocked.value            = false;
        lockUntil.value           = null;
        lockCountdown.value       = 0;
        defenderAlertActive.value = false;
        matchResult.value         = null;
        usedRigCommands.value     = [];
        boardReady.value          = false;
        clearInterval(lockTimer);

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

        // Netstat — full suspect board populated
        if (data.updated_suspects) {
            suspects.value = data.updated_suspects;
            boardReady.value = true;
        }

        // Single suspect attribute revealed (ping/traceroute/whois)
        if (data.suspect_update) {
            _mergeSuspectUpdate(data.suspect_update);
        }

        // ARP scan — last_seen_seconds for all suspects
        if (data.arp_scan_result) {
            _mergeArpResult(data.arp_scan_result);
        }

        // Sniff octet clue
        if (data.octet_clue) {
            octetClue.value = data.octet_clue;
        }

        // Flush — mark suspect as flushed on case file
        if (data.flushed_ip) {
            suspects.value = suspects.value.map(s =>
                s.ip === data.flushed_ip ? { ...s, flushed: true } : s
            );
        }

        // Phase 2 port updates (exploit/decode)
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
            defenderAlertActive.value = true;
            setTimeout(() => { defenderAlertActive.value = false; }, 6000);
        } else {
            phase.value    = 2;
            ports.value    = data.ports ?? [];
            targetIp.value = data.target_ip ?? null;

            _appendHistory('SYSTEM', [
                '[ALERT]: RECON COMPLETE — PIVOTING TO TARGET DEFENSIVE TOPOLOGY',
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

    // ── Suspect helpers ───────────────────────────────────────────────────────

    function _mergeSuspectUpdate(update) {
        suspects.value = suspects.value.map(s => {
            if (s.ip !== update.ip) return s;
            return { ...s, ...update };
        });
    }

    function _mergeArpResult(arpResult) {
        const arpMap = {};
        arpResult.forEach(e => { arpMap[e.ip] = e.last_seen_seconds; });
        suspects.value = suspects.value.map(s => {
            if (arpMap[s.ip] === undefined) return s;
            return { ...s, last_seen_seconds: arpMap[s.ip], _arp_revealed: true };
        });
    }

    // ── Command dispatch ──────────────────────────────────────────────────────

    async function submitCommand(rawInput) {
        if (!matchId.value || busy.value || isLocked.value) return;

        const trimmed = rawInput.trim();
        if (!trimmed) return;

        const knownCommands = [
            'netstat', 'ping', 'traceroute', 'arp', 'whois',
            'sniff', 'flush', 'inject',
            'probe', 'exploit', 'decode', 'breach',
        ];
        const firstToken = trimmed.split(/\s+/)[0].toLowerCase();

        if (!knownCommands.includes(firstToken)) {
            _appendHistory(trimmed, [`[ERROR]: COMMAND NOT FOUND: ${firstToken.toUpperCase()}`]);
            return;
        }

        busy.value = true;
        try {
            await axios.post(`/api/packet-hijack/${matchId.value}/command`, { input: trimmed });
        } catch (e) {
            const status = e?.response?.status;
            if (status === 409) {
                _appendHistory(trimmed, ['[ERROR]: TARGET ALREADY PURGED']);
            } else if (status === 429) {
                const lockData = e?.response?.data;
                if (lockData?.lock_until) _applyLock(new Date(lockData.lock_until));
            } else {
                _appendHistory(trimmed, ['[SYSTEM ERROR]: CONNECTION INTERRUPTED']);
            }
        } finally {
            busy.value = false;
        }
    }

    async function submitRigCommand(slug) {
        if (!matchId.value || busy.value || isLocked.value) return;
        if (usedRigCommands.value.includes(slug)) return;

        const displaySlug = slug.replace(/_/g, ' ').toUpperCase();
        _appendHistory(`[RIG] ${displaySlug}`, [`[ DEPLOYING: ${displaySlug} ]`]);
        usedRigCommands.value = [...usedRigCommands.value, slug];

        busy.value = true;
        try {
            await axios.post(`/api/packet-hijack/${matchId.value}/command`, { rig_command: slug });
        } catch (e) {
            const status = e?.response?.status;
            if (status === 409) {
                _appendHistory(`[RIG] ${displaySlug}`, ['[ERROR]: COMMAND ALREADY DEPLOYED THIS MATCH']);
            } else if (status === 422) {
                const msg = e?.response?.data?.message ?? 'COMMAND UNAVAILABLE';
                _appendHistory(`[RIG] ${displaySlug}`, [`[ERROR]: ${msg.toUpperCase()}`]);
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

    // ── Cleanup ───────────────────────────────────────────────────────────────

    function destroy() {
        clearInterval(lockTimer);
        unsubscribe();
    }

    return reactive({
        matchId,
        role,
        phase,
        commandHistory,
        suspects,
        octetClue,
        activeSuspectCount,
        boardReady,
        ports,
        targetIp,
        isLocked,
        lockCountdown,
        defenderAlertActive,
        matchResult,
        isComplete,
        busy,
        usedRigCommands,
        init,
        submitCommand,
        submitRigCommand,
        destroy,
    });
}
