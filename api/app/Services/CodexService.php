<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerCodexActivation;
use App\Models\PlayerDocumentKey;
use App\Models\PlayerSpliceUnlock;
use App\Models\QuestStage;
use App\Models\SplicePage;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * CodexService
 *
 * Owns the whole optional "investigative treasure hunt" system. Completing a
 * mission activates a codex thread for the player — not tied to any
 * specific node. From there the player plays Archive Extraction anywhere on
 * the map; each win has a chance of dropping a key. A key resolved at the
 * Codex Archive always produces a document from the pool tied to the
 * player's active threads — either the real codex-tier target or a flavor
 * "red herring" — unless that pool is already fully explored, which reads
 * as "nothing left to find" rather than a miss. Codex-tier pages carry one
 * or more missing credentials, findable among other unlocked documents
 * (including deliberate decoys); solving pays a bonus reward.
 *
 * No timers, no requirements, no penalties anywhere in this system — every
 * entry point here is something the player opted into.
 */
class CodexService
{
    // Chance a win of Archive Extraction (played anywhere, while a codex
    // thread is active) drops a key. Tunable — starting point, not a
    // balanced number yet.
    private const KEY_DROP_CHANCE = 0.35;

    // ─────────────────────────────────────────────────────────────────────────
    // Thread activation — triggered by quest stage completion
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Activate a codex thread for a player. Idempotent — safe to call even
     * if already active. Called from QuestService::completeStage() when the
     * completed stage sets codex_thread_key.
     */
    public function activateThreadForStage(Player $player, QuestStage $stage): ?PlayerCodexActivation
    {
        if (!$stage->codex_thread_key) {
            return null;
        }

        return PlayerCodexActivation::firstOrCreate([
            'player_id'  => $player->id,
            'thread_key' => $stage->codex_thread_key,
        ], [
            'source_quest_stage_id' => $stage->id,
        ]);
    }

    /**
     * True if this player has at least one active codex thread — gates
     * whether Archive Extraction wins are even eligible to roll a key drop.
     */
    public function hasActiveCodex(Player $player): bool
    {
        return PlayerCodexActivation::where('player_id', $player->id)->exists();
    }

    /**
     * Thread keys currently active for this player. Activations never get
     * removed once granted, so this only grows — a player can always keep
     * hunting a thread even after finding everything currently available
     * for it.
     */
    public function getActiveThreadKeys(Player $player): array
    {
        return PlayerCodexActivation::where('player_id', $player->id)
            ->pluck('thread_key')
            ->unique()
            ->values()
            ->all();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Keys — a chance-based drop from winning Archive Extraction anywhere
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Roll for a key on an Archive Extraction win. Requires at least one
     * active codex thread — otherwise there's nothing to be hunting for.
     * Returns the granted key, or null on a miss (a miss is a normal,
     * expected outcome, not an error).
     *
     * @throws \InvalidArgumentException if the player has no active codex thread at all
     */
    public function grantKeyFromWin(Player $player): ?PlayerDocumentKey
    {
        if (!$this->hasActiveCodex($player)) {
            throw new \InvalidArgumentException('No active codex thread for this player.');
        }

        $hit = (mt_rand() / mt_getrandmax()) < self::KEY_DROP_CHANCE;
        if (!$hit) {
            return null;
        }

        return PlayerDocumentKey::create([
            'player_id' => $player->id,
            'status'    => 'unresolved',
        ]);
    }

    /**
     * Keys this player has earned but not yet taken to the Codex Archive.
     */
    public function getUnresolvedKeys(Player $player): array
    {
        return PlayerDocumentKey::where('player_id', $player->id)
            ->where('status', 'unresolved')
            ->orderBy('created_at')
            ->get()
            ->map(fn (PlayerDocumentKey $key) => [
                'id'         => $key->id,
                'created_at' => $key->created_at->toIso8601String(),
            ])
            ->all();
    }

    /**
     * Resolve a key at the Codex Archive. Picks a random splice_pages row
     * whose thread_key matches any of the player's active threads and that
     * they haven't already unlocked — always producing a document (real
     * target or flavor "red herring") when the pool isn't empty. An empty
     * pool means the player has already found everything currently
     * available for their active threads — not a miss, just nothing left.
     *
     * @throws \InvalidArgumentException if the key doesn't belong to this player or is already resolved
     */
    public function resolveKey(Player $player, string $keyId): array
    {
        // Locked + transactional so two near-simultaneous resolves of the same
        // key (double-click, retried request) can't both pass the status
        // check and each unlock a different page off one key. The second
        // caller blocks on the lock, then sees the already-updated status
        // and throws cleanly instead of racing through to a duplicate grant.
        return DB::transaction(function () use ($player, $keyId) {
            $key = PlayerDocumentKey::where('id', $keyId)
                ->where('player_id', $player->id)
                ->lockForUpdate()
                ->first();

            if ($key === null) {
                throw new \InvalidArgumentException('Key not found.');
            }
            if ($key->status !== 'unresolved') {
                throw new \InvalidArgumentException('This key has already been resolved.');
            }

            $threadKeys = $this->getActiveThreadKeys($player);
            $alreadyUnlockedIds = PlayerSpliceUnlock::where('player_id', $player->id)->pluck('splice_page_id');

            $eligible = SplicePage::whereIn('thread_key', $threadKeys)
                ->whereNotIn('id', $alreadyUnlockedIds)
                ->get();

            if ($eligible->isEmpty()) {
                $key->update(['status' => 'empty', 'resolved_at' => Carbon::now()]);
                return ['outcome' => 'nothing_left'];
            }

            /** @var SplicePage $page */
            $page = $eligible->random();

            $key->update([
                'status'                  => 'resolved',
                'resolved_splice_page_id' => $page->id,
                'resolved_at'             => Carbon::now(),
            ]);

            $unlock = $this->unlockPage($player, $page);

            return [
                'outcome' => 'document',
                'page'    => $this->presentUnlock($unlock, $page),
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // History — the Codex Archive's tracking list
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Every page this player has ever unlocked, newest first.
     */
    public function getHistory(Player $player): array
    {
        return PlayerSpliceUnlock::where('player_id', $player->id)
            ->with('page')
            ->orderByDesc('unlocked_at')
            ->get()
            ->map(fn (PlayerSpliceUnlock $unlock) => $this->presentUnlock($unlock, $unlock->page))
            ->all();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Reading a page
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Fetch a page by slug for reading.
     *
     * - Codex pages must already have an unlock row (only ever reachable by
     *   resolving a key) — a player can't browse straight to an un-earned one.
     * - Flavor pages are freely reachable by slug (following a lead link from
     *   a codex page, or any other in-fiction reference) — first visit
     *   auto-creates the unlock row as immediately 'completed'.
     *
     * @throws \InvalidArgumentException if not found or (codex, unearned) access is denied
     */
    public function getPageBySlug(Player $player, string $slug): array
    {
        // Nonexistent slug and "unearned codex page" both throw the same
        // generic message below — a distinct message for each would let a
        // client enumerate valid codex slugs by probing for the 404 vs 400 text.
        $page = SplicePage::where('slug', $slug)->first();
        if ($page === null) {
            throw new \InvalidArgumentException('Page not found.');
        }

        $unlock = PlayerSpliceUnlock::where('player_id', $player->id)
            ->where('splice_page_id', $page->id)
            ->first();

        if ($unlock === null) {
            if ($page->isCodex()) {
                throw new \InvalidArgumentException('Page not found.');
            }
            $unlock = $this->unlockPage($player, $page);
        }

        $body = $this->presentUnlock($unlock, $page);
        $body['body'] = $page->body;

        if ($page->isCodex()) {
            $body['login_username'] = $page->login_username;
            $body['solved']         = $unlock->status === 'completed';
            $body['credential_labels'] = collect($page->credentials ?? [])
                ->pluck('label')
                ->all();
            $body['leads'] = $this->presentLeads($page);
            // Only reveal the decrypted payload once solved — this is the
            // whole point of the login gate, so it can't ride along on `body`.
            $body['unlocked_body'] = $body['solved'] ? $page->unlocked_body : null;
        }

        return $body;
    }

    /**
     * Attempt to solve a codex page's login with one or more credentials.
     * Each submitted answer is checked as a plain, case-insensitive string
     * match against the corresponding required credential — deliberately
     * not gated behind having "collected" any particular lead, so a
     * sufficiently attentive player could type the right answers the moment
     * they spot them anywhere. All required credentials must match. No
     * penalty for a wrong guess; retry freely.
     *
     * @param array<string,string> $answers keyed by credential label
     * @throws \InvalidArgumentException on bad state (page not unlocked, not a codex page, already solved)
     */
    public function solveLogin(Player $player, string $splicePageId, array $answers): array
    {
        $page = SplicePage::find($splicePageId);
        if ($page === null || !$page->isCodex()) {
            throw new \InvalidArgumentException('Not a Codex page.');
        }

        // Locked + transactional so two near-simultaneous solve attempts for
        // the same page (double-click, retried request) can't both pass the
        // "not yet completed" check and each grant the reward. The second
        // caller blocks on the lock, then sees status already 'completed'
        // and returns the already_solved response instead of paying out twice.
        return DB::transaction(function () use ($player, $page, $answers) {
            $unlock = PlayerSpliceUnlock::where('player_id', $player->id)
                ->where('splice_page_id', $page->id)
                ->lockForUpdate()
                ->first();

            if ($unlock === null) {
                throw new \InvalidArgumentException('This page has not been unlocked.');
            }
            if ($unlock->status === 'completed') {
                return ['solved' => true, 'already_solved' => true];
            }

            $credentials = $page->credentials ?? [];
            $allMatch = !empty($credentials);

            foreach ($credentials as $cred) {
                $label    = $cred['label'] ?? '';
                $expected = trim(mb_strtolower((string) ($cred['answer'] ?? '')));
                $given    = trim(mb_strtolower((string) ($answers[$label] ?? '')));
                if ($expected === '' || $given !== $expected) {
                    $allMatch = false;
                    break;
                }
            }

            if (!$allMatch) {
                return ['solved' => false];
            }

            $unlock->update(['status' => 'completed', 'completed_at' => Carbon::now()]);

            if (($page->reward_creds ?? 0) > 0) {
                $player->increment('wallet_creds', $page->reward_creds);
            }
            if (($page->reward_tech_points ?? 0) > 0) {
                $player->increment('tech_points', $page->reward_tech_points);
            }

            return [
                'solved'             => true,
                'already_solved'     => false,
                'reward_creds'       => $page->reward_creds ?? 0,
                'reward_tech_points' => (float) ($page->reward_tech_points ?? 0),
                'unlocked_body'      => $page->unlocked_body,
            ];
        });
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Internal helpers
    // ─────────────────────────────────────────────────────────────────────────

    private function unlockPage(Player $player, SplicePage $page): PlayerSpliceUnlock
    {
        $isFlavor = !$page->isCodex();

        return PlayerSpliceUnlock::firstOrCreate(
            ['player_id' => $player->id, 'splice_page_id' => $page->id],
            [
                'status'       => $isFlavor ? 'completed' : 'unresolved',
                'unlocked_at'  => Carbon::now(),
                'completed_at' => $isFlavor ? Carbon::now() : null,
            ],
        );
    }

    private function presentLeads(SplicePage $page): array
    {
        $slugs = $page->lead_slugs ?? [];
        if (empty($slugs)) {
            return [];
        }

        return SplicePage::whereIn('slug', $slugs)
            ->get(['slug', 'title'])
            ->map(fn (SplicePage $lead) => ['slug' => $lead->slug, 'title' => $lead->title])
            ->all();
    }

    private function presentUnlock(PlayerSpliceUnlock $unlock, SplicePage $page): array
    {
        return [
            'id'           => $page->id,
            'slug'         => $page->slug,
            'title'        => $page->title,
            'type'         => $page->type,
            'status'       => $unlock->status,
            'unlocked_at'  => $unlock->unlocked_at?->toIso8601String(),
            'completed_at' => $unlock->completed_at?->toIso8601String(),
        ];
    }
}
