<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

/**
 * CleanHandle
 *
 * Rejects handles that:
 *   1. Match reserved system names (admin, system, moderator, etc.)
 *   2. Contain profanity or slurs (substring match, case-insensitive)
 *   3. Use common leet-speak substitutions to bypass the filter
 *      (e.g. "@" → "a", "3" → "e", "0" → "o", "1" → "i/l", "$" → "s")
 *
 * Add words to $blocklist freely — matching is done against a normalised
 * version of the handle so basic obfuscation is caught automatically.
 */
class CleanHandle implements ValidationRule
{
    /**
     * Reserved names that should never be player handles.
     * Exact match (after normalisation), case-insensitive.
     */
    private const RESERVED = [
        'admin', 'administrator', 'moderator', 'mod', 'system',
        'root', 'superuser', 'staff', 'support', 'god',
        'splice', 'codecraft', 'server', 'null', 'undefined',
        'anonymous', 'anon', 'guest', 'bot', 'ai',
    ];

    /**
     * Blocked substrings.
     * Matched against the normalised handle (leet-speak decoded, lowercase).
     * Keep alphabetical within each group for easy maintenance.
     */
    private const BLOCKLIST = [
        // ── Slurs (racial / ethnic / sexuality / gender) ─────────────────
        'chink', 'cracker', 'dyke', 'fag', 'faggot', 'gook', 'kike',
        'nigga', 'nigger', 'nig', 'paki', 'retard', 'spic', 'towelhead',
        'tranny', 'wetback', 'zipperhead',

        // ── Sexual ────────────────────────────────────────────────────────
        'anus', 'ass', 'asshole', 'ballsack', 'blowjob', 'boner',
        'boob', 'butt', 'butthole', 'cock', 'cum', 'cunt',
        'dick', 'dildo', 'foreskin', 'fuck', 'jackoff', 'jerkoff',
        'jizz', 'masturbat', 'milf', 'nudes', 'penis', 'piss',
        'porn', 'pussy', 'rectum', 'scrotum', 'sex', 'shit',
        'slut', 'smegma', 'testicle', 'tit', 'twat', 'vagina',
        'vulva', 'whore',

        // ── Violence / hate ───────────────────────────────────────────────
        'genocide', 'hitler', 'holocaust', 'isis', 'jihad',
        'klan', 'lynch', 'massacre', 'nazi', 'nonce', 'pedo',
        'pedophile', 'rape', 'rapist', 'shooting', 'suicide',
        'terrorist', 'torture',
    ];

    /**
     * Leet-speak character substitution map used during normalisation.
     * Catches simple obfuscation like "f4gg0t" or "@55h0le".
     */
    private const LEET = [
        '@' => 'a', '4' => 'a',
        '3' => 'e',
        '1' => 'i', '!' => 'i', '|' => 'i',
        '0' => 'o',
        '$' => 's', '5' => 's',
        '+' => 't', '7' => 't',
        'v' => 'v',   // not a substitution — here as a no-op placeholder
    ];

    // -------------------------------------------------------------------------

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $normalised = $this->normalise((string) $value);

        // 1. Reserved names — exact match
        if (in_array($normalised, self::RESERVED, strict: true)) {
            $fail('That handle is reserved and cannot be used.');
            return;
        }

        // 2. Blocked substrings
        foreach (self::BLOCKLIST as $word) {
            if (str_contains($normalised, $word)) {
                $fail('That handle contains a word that is not allowed.');
                return;
            }
        }
    }

    /**
     * Decode leet-speak and lowercase so a single blocklist entry covers
     * variants like "F4GG0T", "f@ggot", etc.
     */
    private function normalise(string $handle): string
    {
        $handle = strtolower($handle);
        return strtr($handle, self::LEET);
    }
}
