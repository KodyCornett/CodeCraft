/**
 * usePacketHijack — client-side state for all three phases of Packet Hijack.
 *
 * Phase 1: Suspect investigation (netstat, ping, traceroute, arp, whois, sniff, flush, inject)
 * Phase 2: Exploit chain investigation (scan, probe, trace, exploit, breach + auth)
 * Phase 3: Filesystem extraction (ls, cd, extract)
 */

import { ref, computed, reactive } from 'vue';
import axios from 'axios';

export function usePacketHijack(playerId) {

    // ── Core state ────────────────────────────────────────────────────────────

    const matchId             = ref(null);
    const role                = ref(null);
    const phase               = ref(1);
    const commandHistory      = ref([]);
    const busy                = ref(false);
    const isLocked            = ref(false);
    const lockUntil           = ref(null);
    const lockCountdown       = ref(0);
    const defenderAlertActive = ref(false);
    const matchResult         = ref(null);
    const usedRigCommands     = ref([]);
    const echoChannel         = ref(null);

    // ── Phase 1 state ─────────────────────────────────────────────────────────

    const suspects            = ref([]);
    const octetClue           = ref(null);
    const boardReady          = ref(false);

    // ── Phase 2 state (redesigned) ────────────────────────────────────────────

    const portPool            = ref([]);       // array of { port, service, probed, shattered, is_exfil }
    const chainConfirmed      = ref({});       // map of port number → true when trace confirmed as chain link
    const traceAttemptsLeft   = ref(0);        // remaining trace attempts
    const credentialState     = ref({ hostname: null, os: null });  // fills as chain progresses
    const awaitingAuth        = ref(false);    // true after successful breach
    const boardScanned        = ref(false);    // true after first scan command
    const targetIp            = ref(null);     // locked target IP revealed at phase 1 → 2 transition

    // ── Phase 3 — bank screen state ───────────────────────────────────────────

    const bankAccess          = ref(false);   // true once auth succeeds and bank screen opens
    const bankBalance         = ref(0);       // opponent's pocket_creds snapshot
    const transferring        = ref(false);   // true while xfer API call is in flight

    // ── Phase 3 — filesystem state (legacy, kept for compat) ─────────────────

    const currentPath         = ref('/');
    const directoryEntries    = ref([]);
    const exploredPaths       = ref([]);

    let lockTimer = null;

    // ── Derived ───────────────────────────────────────────────────────────────

    const isComplete         = computed(() => matchResult.value !== null);
    const activeSuspectCount = computed(() => suspects.value.filter(s => !s.flushed).length);

    // ── Init ──────────────────────────────────────────────────────────────────

    function init(mId, playerRole) {
        matchId.value             = mId;
        role.value                = playerRole;
        phase.value               = 1;
        commandHistory.value      = [];
        suspects.value            = [];
        octetClue.value           = null;
        boardReady.value          = false;
        portPool.value            = [];
        chainConfirmed.value      = {};
        traceAttemptsLeft.value   = 0;
        credentialState.value     = { hostname: null, os: null };
        awaitingAuth.value        = false;
        boardScanned.value        = false;
        bankAccess.value          = false;
        bankBalance.value         = 0;
        transferring.value        = false;
        currentPath.value         = '/';
        directoryEntries.value    = [];
        exploredPaths.value       = [];
        isLocked.value            = false;
        lockUntil.value           = null;
        lockCountdown.value       = 0;
        defenderAlertActive.value = false;
        matchResult.value         = null;
        usedRigCommands.value     = [];
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

        // ── Phase 1 updates ───────────────────────────────────────────────────
        if (data.updated_suspects) {
            suspects.value = data.updated_suspects;
            boardReady.value = true;
        }
        if (data.suspect_update) _mergeSuspectUpdate(data.suspect_update);
        if (data.arp_scan_result) _mergeArpResult(data.arp_scan_result);
        if (data.octet_clue) octetClue.value = data.octet_clue;
        if (data.flushed_ip) {
            suspects.value = suspects.value.map(s =>
                s.ip === data.flushed_ip ? { ...s, flushed: true } : s
            );
        }

        // ── Rig command: opponent port board update ───────────────────────────
        if (data.updated_ports) {
            portPool.value = data.updated_ports;
        }

        // ── Phase 2 updates ───────────────────────────────────────────────────

        // scan: populate the port board
        if (data.port_scan_result) {
            portPool.value  = data.port_scan_result;
            boardScanned.value = true;
        }

        // probe: mark a port as probed in the local pool
        if (data.port_probed != null) {
            portPool.value = portPool.value.map(p =>
                p.port === data.port_probed ? { ...p, probed: true } : p
            );
        }

        // trace: update chain confirmed map + trace attempt counter
        // trace_confirmed = [port1, port2] means port1 → port2 is a confirmed chain link
        // trace_partial   = [port1, port2] means the pair is adjacent but order is reversed (flip them)
        if (data.trace_confirmed) {
            const [p1, p2] = data.trace_confirmed;
            chainConfirmed.value = { ...chainConfirmed.value, [p1]: true, [p2]: true };
        }
        if (data.trace_partial) {
            const [p1, p2] = data.trace_partial;
            // Only set 'partial' if not already fully confirmed
            chainConfirmed.value = {
                ...chainConfirmed.value,
                [p1]: chainConfirmed.value[p1] || 'partial',
                [p2]: chainConfirmed.value[p2] || 'partial',
            };
        }
        if (data.trace_attempts != null) {
            traceAttemptsLeft.value = data.trace_attempts;
        }

        // exploit: mark port shattered + update credential strip
        if (data.port_shattered != null) {
            portPool.value = portPool.value.map(p =>
                p.port === data.port_shattered ? { ...p, shattered: true } : p
            );
        }
        if (data.credential_state) {
            credentialState.value = {
                hostname: data.credential_state.hostname ?? credentialState.value.hostname,
                os:       data.credential_state.os       ?? credentialState.value.os,
            };
        }

        // breach: open auth prompt
        if (data.awaiting_auth) awaitingAuth.value = true;
        if (data.auth_failed)   awaitingAuth.value = false;

        // auth success → bank screen
        if (data.bank_access) {
            bankAccess.value  = true;
            bankBalance.value = data.bank_balance ?? 0;
        }

        // ── Phase 3 updates ───────────────────────────────────────────────────
        if (data.filesystem_update) {
            if (data.filesystem_update.current_path !== undefined) {
                currentPath.value = data.filesystem_update.current_path;
                if (!exploredPaths.value.includes(data.filesystem_update.current_path)) {
                    exploredPaths.value.push(data.filesystem_update.current_path);
                }
            }
            if (data.filesystem_update.entries !== undefined) {
                directoryEntries.value = data.filesystem_update.entries;
            }
        }

        // ── Common ────────────────────────────────────────────────────────────
        if (data.lock_until)    _applyLock(new Date(data.lock_until));
        if (data.phase_advanced) {
            phase.value++;
            awaitingAuth.value = false;
        }
    }

    function _onPhaseTransition(data) {
        if (data.match_id !== matchId.value) return;

        if (data.alert_only) {
            defenderAlertActive.value = true;
            setTimeout(() => { defenderAlertActive.value = false; }, 6000);
        } else {
            phase.value = 2;
            if (data.target_ip) targetIp.value = data.target_ip;
            // Seed the port board from the transition payload if provided
            if (data.ports && data.ports.length) {
                portPool.value = data.ports;
                boardScanned.value = true;
            }
            _appendHistory('SYSTEM', [
                '[ALERT]: RECON COMPLETE — TARGET TERMINAL COMPROMISED',
                '[PIVOTING TO PHASE 2: EXPLOIT CHAIN INVESTIGATION]',
                '[TYPE: scan ' + (data.target_ip ?? '<target-ip>') + ' TO INITIALISE PORT BOARD]',
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
        suspects.value = suspects.value.map(s =>
            s.ip !== update.ip ? s : { ...s, ...update }
        );
    }

    function _mergeArpResult(arpResult) {
        const map = {};
        arpResult.forEach(e => { map[e.ip] = e.last_seen_seconds; });
        suspects.value = suspects.value.map(s =>
            map[s.ip] === undefined ? s : { ...s, last_seen_seconds: map[s.ip], _arp_revealed: true }
        );
    }

    // ── Command dispatch ──────────────────────────────────────────────────────

    async function submitCommand(rawInput) {
        if (!matchId.value || busy.value || isLocked.value) return;
        const trimmed = rawInput.trim();
        if (!trimmed) return;

        const knownCommands = [
            'netstat', 'ping', 'traceroute', 'arp', 'whois', 'sniff', 'flush', 'inject',
            'scan', 'probe', 'trace', 'exploit', 'breach',
            'ls', 'cd', 'extract',
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

    async function submitAuth(username, password) {
        if (!matchId.value || busy.value) return;
        busy.value = true;
        try {
            await axios.post(`/api/packet-hijack/${matchId.value}/command`, {
                auth_user: username,
                auth_pass: password,
            });
        } catch (e) {
            _appendHistory('[AUTH]', ['[SYSTEM ERROR]: AUTHENTICATION REQUEST FAILED']);
        } finally {
            busy.value = false;
        }
    }

    async function submitTransfer() {
        if (!matchId.value || transferring.value) return;
        transferring.value = true;
        try {
            await axios.post(`/api/packet-hijack/${matchId.value}/transfer`);
        } catch (e) {
            const status = e?.response?.status;
            if (status === 409) {
                // Match already resolved — matchResult will arrive via WS
            } else {
                _appendHistory('[XFER]', ['[SYSTEM ERROR]: TRANSFER CONNECTION INTERRUPTED']);
                transferring.value = false;
            }
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
        // Use the reactive proxy from the array, not the raw object.
        // _streamLines mutates entry.lines via setTimeout — if we pass the raw
        // object those pushes bypass Vue's Proxy and never trigger re-renders.
        // The item at the tail of commandHistory.value IS the reactive proxy.
        _streamLines(commandHistory.value[commandHistory.value.length - 1], lines ?? []);
    }

    function _streamLines(entry, lines) {
        let i = 0;
        function tick() {
            if (i >= lines.length) return;
            entry.lines.push(lines[i]);
            i++;
            setTimeout(tick, 60);
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
        busy,
        isLocked,
        lockCountdown,
        defenderAlertActive,
        matchResult,
        isComplete,
        usedRigCommands,
        // Phase 1
        suspects,
        octetClue,
        boardReady,
        activeSuspectCount,
        // Phase 2
        portPool,
        chainConfirmed,
        traceAttemptsLeft,
        credentialState,
        awaitingAuth,
        boardScanned,
        targetIp,
        // Phase 3 — bank screen
        bankAccess,
        bankBalance,
        transferring,
        // Phase 3 — filesystem (legacy)
        currentPath,
        directoryEntries,
        exploredPaths,
        // Actions
        init,
        submitCommand,
        submitAuth,
        submitRigCommand,
        submitTransfer,
        destroy,
    });
}
