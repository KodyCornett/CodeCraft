/**
 * vpnCredentials — SIT's first hand-written scenario (IronVale Corp VPN
 * credentials). Formerly proofScenario.js; migrated into the scenarios/
 * folder and refactored onto the generic `verbs` dispatch shape so
 * fsInterpreter.js no longer hardcodes `login` — see fsInterpreter.js's
 * docblock for why.
 *
 * No generation, no AI, no randomness — the point of this file is still
 * to prove the CORE LOOP (explore a filesystem by typing, read a few
 * documents, combine two separate facts into one answer, authenticate)
 * actually feels good before any content-generation work happens on top
 * of it.
 *
 * The password isn't sitting in one obvious file — the two emails only
 * describe the FORMULA ("project codename + last two digits of the ship
 * year"), and the two actual facts it needs live in two unrelated-looking
 * documents elsewhere in the tree. Getting the answer means reading
 * multiple things and connecting them, not keyword-scanning for a
 * password string.
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

export function buildVpnCredentialsScenario() {
    const root = dir('/', {
        'readme.txt': file('readme.txt', [
            'IRONVALE CORP — INTERNAL WORKSTATION',
            'Unauthorized access is a violation of network policy.',
            '',
            'OBJECTIVE: locate valid VPN credentials for S.WALKER and authenticate.',
            'Use: login <username> <password>',
            '',
            'Credentials are rarely written down in one place — check personal',
            'files, mail, and system logs.',
        ]),
        'home': dir('home', {
            's.walker': dir('s.walker', {
                'notes.txt': file('notes.txt', [
                    'reminder to self: renew badge before it expires next month.',
                    'also stop leaving stickies with wifi codes on the monitor,',
                    'karen will kill me.',
                    'still waiting on k.ramos to send over the final project',
                    'sign-off, need to ping her again',
                ]),
                'mail': dir('mail', {
                    'inbox.eml': file('inbox.eml', [
                        'From: k.ramos@ironvale-corp.net',
                        'Subject: re: vpn access',
                        '',
                        'hey, IT reset my creds again and I keep forgetting the scheme.',
                        "it's still the project codename plus the last two digits of",
                        'the year we shipped it, right? dont want to lock myself out again',
                    ]),
                    'sent.eml': file('sent.eml', [
                        'From: s.walker@ironvale-corp.net',
                        'Subject: re: re: vpn access',
                        '',
                        'yep, still project codename + shipped year. same as always,',
                        "dont write it down anywhere obvious lol. if you blank on the",
                        'year again its on the deploy logs, ops keeps those forever',
                    ]),
                }),
            }),
            'k.ramos': dir('k.ramos', {
                'notes.txt': file('notes.txt', [
                    'status update — THRESHOLD is still on track for the year-end',
                    'release, ops wants sign-off by friday',
                ]),
            }),
        }),
        'var': dir('var', {
            'logs': dir('logs', {
                'deploy.log': file('deploy.log', [
                    '[DEPLOY LOG — ARCHIVE]',
                    '2019-11-02  v1.0 shipped to production',
                    '2021-03-14  routine patch cycle',
                    '2022-07-30  routine patch cycle',
                ]),
            }),
        }),
    });

    const TARGET_USER     = 's.walker';
    const TARGET_PASSWORD = 'THRESHOLD19';

    return {
        root,
        objective: 'Locate valid VPN credentials and authenticate as an employee.',
        // Deliberately very generous right now — the point of this proof slice is
        // to find out whether exploring + reading + combining facts feels good on
        // its own. A 240s budget cut a real first playthrough off mid-investigation,
        // which just confuses "is this fun" with "did I read fast enough." Whatever
        // real time pressure eventually looks like here (earlier discussion leaned
        // toward a trace/suspicion mechanic rather than a countdown anyway) is a
        // separate tuning pass for after the core loop itself is proven.
        timeLimitSec: 900,
        verbs: {
            login: {
                usage: 'login <user> <pass>     attempt to authenticate',
                run(args) {
                    const [user, password] = args;
                    if (!user || !password) {
                        return { output: ['login: usage — login <user> <password>'], solved: false };
                    }
                    const ok = user.toLowerCase() === TARGET_USER.toLowerCase()
                        && password.toUpperCase() === TARGET_PASSWORD;
                    if (ok) {
                        return { output: ['ACCESS GRANTED.'], solved: true };
                    }
                    return { output: ['ACCESS DENIED — invalid credentials.'], solved: false };
                },
            },
        },
    };
}
