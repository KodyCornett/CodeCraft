/**
 * cipherLockPhrases.js
 *
 * Phrase bank for the CIPHER_LOCK minigame.
 *
 * Each entry is a short, in-world flavored line the player decrypts letter by
 * letter. Keep entries short — the minigame's pacing is tuned around roughly
 * 10-14 unique letters per phrase (see CipherLock.vue), so long or unusually
 * letter-dense lines will throw off the ICE-tier timer balance.
 *
 * Selection uses a shuffle-bag (see pickNextPhrase below) so the same phrase
 * can't reappear until every other phrase in the bank has been seen, which
 * keeps repeats rare even across many hacks in a single play session.
 */

export const CIPHER_LOCK_PHRASES = [
    // -- Player's own reference line --
    'LIFE IS LIKE CODE',

    // -- SPLICE / hacker mantras --
    'JACK IN AND VANISH',
    'THE GRID NEVER SLEEPS',
    'TRUST NO CLEAN SIGNAL',
    'GHOSTS LEAVE NO TRACE',
    'EVERY LOCK HAS A SEAM',
    'STATIC HIDES THE TRUTH',
    'READ BETWEEN THE PACKETS',
    'SILENCE IS A FIREWALL',
    'THE NETWORK REMEMBERS ALL',
    'BREACH QUIET LEAVE QUIETER',
    'DATA ROTS WITHOUT LIGHT',
    'FIND THE SEAM AND PULL',
    'NO GHOST RUNS FOREVER',
    'THE SIGNAL ALWAYS LIES',
    'CUT THE FEED AND RUN',

    // -- Cyber Doc belief fragments (short, reworked) --
    'FREEDOM IS NOT COMFORT',
    'SHOW ME THE BACKUP',
    'THE RECORD WAS DELETED',
    'NOBODY HELD A GUN THOUGH',
    'ERASURE IS A WEAPON TOO',
    'MAINTAINED IS NOT FREE',
    'THE ALGORITHM CHOSE SLOW',
    'LIBERATE THE DATA NOW',

    // -- Terminal / hack flavor lines --
    'ACCESS GRANTED FOR NOW',
    'ICE IS WATCHING CLOSELY',
    'ROOT ACCESS IS A MYTH',
    'THE BACKDOOR NEVER CLOSED',
    'ENCRYPTION BUYS TIME ONLY',
    'PATCH THE HOLE QUIETLY',
    'THE ARCHIVE REMEMBERS YOU',
    'KEYS ROT FASTER THAN LOCKS',
    'A QUIET HACK IS A CLEAN HACK',
    'SPOOF THE TRACE AND RUN',
    'BUFFER OVERFLOW IMMINENT',

    // -- Wider aphorisms, kept short --
    'CHAOS SCALES WITH TRUST',
    'SPEED HIDES SLOPPY WORK',
    'PATIENCE OUTLASTS PANIC',
];

// ── Shuffle-bag selection ───────────────────────────────────────────────────
//
// Hands phrases out in a shuffled order, exhausting the whole bank before
// reshuffling, so no phrase repeats until everything else has been seen.
// The reshuffle also avoids putting the just-seen phrase back at the very
// front, so there's no back-to-back repeat across a reshuffle boundary.

let bag = [];
let lastPhrase = null;

function shuffledCopy(arr) {
    const copy = [...arr];
    for (let i = copy.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [copy[i], copy[j]] = [copy[j], copy[i]];
    }
    return copy;
}

function refillBag() {
    bag = shuffledCopy(CIPHER_LOCK_PHRASES);
    // If the new bag happens to start with the phrase we just handed out,
    // swap it further back so it doesn't feel like an immediate repeat.
    if (bag.length > 1 && bag[0] === lastPhrase) {
        const swapWith = 1 + Math.floor(Math.random() * (bag.length - 1));
        [bag[0], bag[swapWith]] = [bag[swapWith], bag[0]];
    }
}

/** Returns the next phrase in the shuffle-bag, refilling as needed. */
export function pickNextPhrase() {
    if (bag.length === 0) refillBag();
    const phrase = bag.pop();
    lastPhrase = phrase;
    return phrase;
}
