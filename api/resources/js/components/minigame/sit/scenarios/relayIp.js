/**
 * relayIp — SIT's second hand-written scenario. Built to prove the shell
 * generalizes to a genuinely different puzzle SHAPE, not just different
 * flavor text: instead of reading two emails for a password formula, the
 * player reconstructs a full IP address one octet-group at a time from
 * three documents, then authenticates with a different verb entirely
 * (connect <ip> instead of login <user> <pass>) — proof that a scenario
 * can define its own objective verb without fsInterpreter.js changing.
 *
 * Same discoverability rule this project keeps re-learning: every fact
 * has to be findable AND connectable, not just technically present.
 * Each document below explicitly points at where the next piece lives,
 * and the deliberate decoy IP (10.44.6.12) is labeled as wrong/
 * decommissioned right where it appears, twice, so a player who
 * pattern-matches too early gets corrected instead of stuck.
 *
 * Deliberately self-contained: no imports from ArchiveExtraction.vue,
 * PacketHijack.vue, BankHeist.vue, or their Codex/browser-pages system.
 */

function file(name, contentLines) {
    return { type: 'file', name, content: contentLines.join('\n') };
}

function dir(name, children) {
    return { type: 'dir', name, children };
}

export function buildRelayIpScenario() {
    const root = dir('/', {
        'readme.txt': file('readme.txt', [
            'RELAY DIAGNOSTIC SHELL — NETOPS ACCESS',
            'This session grants read access to internal handoff and incident records.',
            '',
            "OBJECTIVE: identify the CURRENT backup relay server's IP address and connect to it.",
            'Use: connect <ip>',
            '',
            "The full address isn't recorded in one place — cross-reference the",
            'netops handoff notes, the open deskside ticket, and the security',
            'audit log.',
        ]),
        'home': dir('home', {
            'netops': dir('netops', {
                'handoff.txt': file('handoff.txt', [
                    'SHIFT HANDOFF — NETOPS',
                    '',
                    'All backup relay infrastructure lives on the 10.44.x.x range this',
                    'quarter (migrated off the old 10.12.x.x block in March).',
                    '',
                    'Open item: deskside has an unresolved ticket about the CURRENT',
                    'backup relay not responding to ping — see ticket_4821 before',
                    'paging anyone.',
                ]),
            }),
            'deskside': dir('deskside', {
                'ticket_4821.txt': file('ticket_4821.txt', [
                    'TICKET #4821 — "backup relay unreachable"',
                    'Reported by: j.aoki',
                    'Status: OPEN',
                    '',
                    'user reports backup relay timing out on manual failover test.',
                    '',
                    'note from triage: this is NOT the old box (10.44.6.12 — that one',
                    "was decommissioned in Q1, don't waste time pinging it). the",
                    'replacement is still in the 10.44.19.x subnet per the netops',
                    'migration notes.',
                    '',
                    'escalated to secops — check the audit log for the connection',
                    'attempt, should show the exact host.',
                ]),
            }),
            'secops': dir('secops', {
                'audit.log': file('audit.log', [
                    '[SECURITY AUDIT LOG]',
                    '2026-08-11 14:02:07  connection attempt   10.44.19.231  relay-backup-02   ALLOWED',
                    '2026-08-11 14:02:41  connection attempt   10.44.6.12    relay-backup-old  DENIED (decommissioned)',
                    '2026-08-12 09:15:03  routine healthcheck  10.44.19.231  relay-backup-02   OK',
                ]),
            }),
        }),
    });

    const TARGET_IP = '10.44.19.231';

    return {
        root,
        objective: 'Identify the current backup relay server and connect to it.',
        // Same generous budget as vpnCredentials, for the same reason — this
        // slice exists to test whether the puzzle SHAPE works, not to test
        // reading speed. See vpnCredentials.js's comment on this.
        timeLimitSec: 900,
        verbs: {
            connect: {
                usage: 'connect <ip>            attempt to connect to a host',
                run(args) {
                    const [ip] = args;
                    if (!ip) {
                        return { output: ['connect: usage — connect <ip>'], solved: false };
                    }
                    if (ip === TARGET_IP) {
                        return { output: ['CONNECTION ESTABLISHED.'], solved: true };
                    }
                    return { output: [`connect: no route to host — ${ip} did not respond`], solved: false };
                },
            },
        },
    };
}
