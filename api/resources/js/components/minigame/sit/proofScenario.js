/**
 * proofScenario — hand-written content for SIT's (Splice Interface Terminal)
 * first proof slice. No generation, no AI, no randomness yet — the point of this file
 * is to prove the CORE LOOP (explore a filesystem by typing, read a few
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

export function buildProofScenario() {
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

    function checkWin(user, password) {
        if (!user || !password) return false;
        return user.toLowerCase() === TARGET_USER.toLowerCase()
            && password.toUpperCase() === TARGET_PASSWORD;
    }

    return {
        root,
        checkWin,
        objective:    'Locate valid VPN credentials and authenticate as an employee.',
        timeLimitSec: 240, // generous on purpose — this proof slice is about reading, not racing a clock
    };
}
