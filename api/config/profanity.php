<?php

/**
 * DOC hub chat profanity / harassment blocklist.
 *
 * Consumed by App\Services\ProfanityFilterService. Single-word entries are
 * matched exactly, after lowercasing and light leetspeak normalization, so
 * innocent words that merely contain a blocked word as a substring aren't
 * flagged (e.g. "classic", "assassin", "Scunthorpe"). Entries containing a
 * space are treated as phrases and matched as a substring of the whole
 * (whitespace-collapsed) message instead, since phrase-level harassment
 * ("kill yourself") isn't a single dirty word.
 *
 * This is a starter list, not an exhaustive one — tuned to avoid flagging
 * common mild words (idiot, moron, dumb, etc.) that show up constantly in
 * harmless banter, since a word filter can't tell "you're an idiot" from
 * "I felt like an idiot" apart. For broader slur/profanity coverage, merge
 * in a maintained public list such as LDNOOBW
 * (https://github.com/LDNOOBW/List-of-Dirty-Naughty-Obscene-and-Otherwise-Bad-Words)
 * — just append terms to the array below, one word or phrase per entry.
 */
return [
    'blocked' => [
        // General profanity
        'fuck', 'shit', 'ass', 'asshole', 'bitch', 'bastard', 'crap',
        'dick', 'piss', 'cunt', 'cock', 'pussy', 'whore', 'slut', 'damn',

        // Ableist slur commonly used as an insult
        'retard', 'retarded',

        // Slurs — kept minimal here; extend via a curated list for broader coverage
        'faggot', 'fag', 'tranny', 'nigger', 'nigga', 'spic', 'chink', 'kike',

        // Harassment / self-harm-incitement phrases
        'kill yourself', 'kys', 'go die',
    ],
];
