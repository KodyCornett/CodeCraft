<?php

namespace App\Services;

/**
 * ProfanityFilterService
 *
 * Blocklist-based profanity/harassment filter for DOC hub chat. Normalizes
 * common obfuscation — leetspeak substitutions ("f4ck", "5hit") and
 * repeated-letter stretching ("fuuuuuck") — before matching so casual bypass
 * attempts don't slip through.
 *
 * Single-word blocklist entries are matched against each token in the
 * message via a run-length-aware regex built from the word itself, not a
 * plain substring/equality check. Each letter's existing run length becomes
 * a minimum ("s" in "ass" requires 2+ s's, not just 1+) — this is what lets
 * "fuuuuuck" match "fuck" without also letting the bare word "as" match
 * "ass" (which a naive collapse-then-compare approach would do). It also
 * means innocent words that merely contain a blocked word don't get flagged
 * ("classic", "assassin") since the whole token must match, not a substring.
 *
 * Multi-word phrase entries (e.g. "kill yourself") are matched as a
 * substring of the whitespace-collapsed message instead, since that kind of
 * harassment isn't a single dirty word.
 *
 * Not adversarial-proof — letter-by-letter spacing ("f u c k") isn't caught,
 * since each letter tokenizes separately. That's an accepted tradeoff for a
 * lightweight, low-false-positive filter rather than a full NLP moderation
 * pipeline. Wordlist lives in config/profanity.php so it can be tuned
 * without touching this class.
 */
class ProfanityFilterService
{
    private const LEET_MAP = [
        '0' => 'o', '1' => 'i', '3' => 'e', '4' => 'a',
        '5' => 's', '7' => 't', '@' => 'a', '$' => 's',
    ];

    /** @var array<int, string>|null Lazily loaded, compiled per-word stretch-match regexes */
    private ?array $wordPatterns = null;

    /** @var array<int, string>|null Lazily loaded multi-word phrase blocklist */
    private ?array $phraseList = null;

    /**
     * True if the message contains a blocked word or phrase.
     */
    public function containsProfanity(string $text): bool
    {
        $lower = mb_strtolower($text);
        $this->loadBlocklist();

        // Phrases — substring match against whitespace-collapsed text.
        $collapsed = preg_replace('/\s+/', ' ', trim($lower)) ?? $lower;
        foreach ($this->phraseList as $phrase) {
            if (str_contains($collapsed, $phrase)) {
                return true;
            }
        }

        // Single words — tokenize, swap leetspeak, test each token against
        // every compiled word pattern.
        $tokens = preg_split('/[^a-z0-9@$]+/i', $lower) ?: [];
        foreach ($tokens as $token) {
            if ($token === '') continue;
            $swapped = strtr($token, self::LEET_MAP);

            foreach ($this->wordPatterns as $pattern) {
                if (preg_match($pattern, $swapped) === 1) {
                    return true;
                }
            }
        }

        return false;
    }

    /**
     * Builds a regex that matches the word itself plus any amount of
     * character-stretching, e.g. "fuck" -> /^f+u+c+k+$/ so "fuuuuuck"
     * matches. Runs that are already 2+ in the source word require at
     * least that many repeats, so "ass" -> /^a+s{2,}$/ — this is what stops
     * the bare word "as" from matching "ass".
     */
    private function buildStretchPattern(string $word): string
    {
        $chars = preg_split('//u', $word, -1, PREG_SPLIT_NO_EMPTY) ?: [];
        $runs  = [];
        $i     = 0;
        $n     = count($chars);

        while ($i < $n) {
            $ch  = $chars[$i];
            $len = 1;
            while ($i + $len < $n && $chars[$i + $len] === $ch) {
                $len++;
            }
            $quoted   = preg_quote($ch, '/');
            $runs[]   = $len > 1 ? "{$quoted}{{$len},}" : "{$quoted}+";
            $i       += $len;
        }

        return '/^' . implode('', $runs) . '$/u';
    }

    private function loadBlocklist(): void
    {
        if ($this->wordPatterns !== null) {
            return;
        }

        $terms = array_map(
            fn (string $term) => mb_strtolower(trim($term)),
            config('profanity.blocked', [])
        );

        $this->wordPatterns = [];
        $this->phraseList   = [];

        foreach ($terms as $term) {
            if ($term === '') continue;
            if (str_contains($term, ' ')) {
                $this->phraseList[] = $term;
            } else {
                $this->wordPatterns[] = $this->buildStretchPattern($term);
            }
        }
    }
}
