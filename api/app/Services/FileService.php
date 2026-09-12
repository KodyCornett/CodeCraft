<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerFile;

/**
 * Backs the File Explorer OS program (see CONTRACTS_AND_OS_REWORK_PLAN.md).
 *
 * Seeds a "Documents" folder (starter reference docs) and an empty
 * "Downloads" folder the first time a player has no files yet, so every
 * player boots into a File Explorer that already has something real in it.
 * The seeded structure — both folders and the starter docs — is marked
 * `protected`: the player can add files into any folder and delete files
 * they've added, but can't move or delete the protected structure itself.
 * There's no move/reorganize feature at all yet, so "can't be moved" is
 * enforced simply by not building that capability.
 */
class FileService
{
    /**
     * Returns the player's whole file tree as a flat list (folders and
     * files together, ordered so a client can group by parent_id). File
     * content is intentionally omitted here — see getFileContent().
     */
    public function getTree(Player $player): array
    {
        $this->ensureSeeded($player);

        return PlayerFile::where('player_id', $player->id)
            ->orderBy('sort_order')
            ->get()
            ->map(fn (PlayerFile $f) => $this->summarize($f))
            ->toArray();
    }

    /**
     * Returns one file's content, or null if it doesn't exist, isn't owned
     * by this player, or is a folder (folders have no content to read).
     */
    public function getFileContent(Player $player, string $fileId): ?array
    {
        $file = $this->ownedFile($player, $fileId);
        if ($file === null || $file->isFolder()) {
            return null;
        }

        return [
            ...$this->summarize($file),
            'content' => $file->content,
        ];
    }

    /**
     * Creates a new, unprotected file inside one of the player's own
     * folders. Returns null if parentId doesn't exist, isn't owned by this
     * player, or isn't a folder — creation is allowed inside ANY folder the
     * player owns, protected ones included (Documents/Downloads can always
     * receive new files; only the seeded structure itself can't be touched).
     */
    public function createFile(Player $player, string $parentId, string $name, ?string $extension): ?array
    {
        $parent = $this->ownedFile($player, $parentId);
        if ($parent === null || !$parent->isFolder()) {
            return null;
        }

        $file = PlayerFile::create([
            'player_id'  => $player->id,
            'parent_id'  => $parent->id,
            'name'       => $name,
            'type'       => 'file',
            'extension'  => $extension ?: 'txt',
            'content'    => '',
            'sort_order' => $this->nextSortOrder($parent->id),
            'protected'  => false,
        ]);

        return $this->summarize($file);
    }

    /**
     * Deletes a file the player owns. Refuses (returns false) if it doesn't
     * exist, isn't owned by this player, is a folder (no folder-delete
     * feature exists), or is protected — the seeded structure can never be
     * removed this way.
     */
    public function deleteFile(Player $player, string $fileId): bool
    {
        $file = $this->ownedFile($player, $fileId);
        if ($file === null || $file->isFolder() || $file->protected) {
            return false;
        }

        $file->delete();
        return true;
    }

    /**
     * Drops a file into the player's Downloads folder. Not wired to any API
     * route yet — this is the hook future mission/game-system code calls
     * directly to hand the player a file (an extracted document, mission
     * loot, etc.) without going through the client at all. Finds the
     * player's Downloads folder (seeding it defensively if somehow absent);
     * the deposited file itself is unprotected, same as anything the player
     * adds themselves, so they can clean it up once they're done with it.
     */
    public function depositDownload(Player $player, string $name, ?string $extension, string $content): PlayerFile
    {
        $this->ensureSeeded($player);

        $downloads = PlayerFile::where('player_id', $player->id)
            ->whereNull('parent_id')
            ->where('type', 'folder')
            ->where('name', 'Downloads')
            ->first();

        if ($downloads === null) {
            $downloads = PlayerFile::create([
                'player_id'  => $player->id,
                'parent_id'  => null,
                'name'       => 'Downloads',
                'type'       => 'folder',
                'sort_order' => 1,
                'protected'  => true,
            ]);
        }

        return PlayerFile::create([
            'player_id'  => $player->id,
            'parent_id'  => $downloads->id,
            'name'       => $name,
            'type'       => 'file',
            'extension'  => $extension ?: 'txt',
            'content'    => $content,
            'sort_order' => $this->nextSortOrder($downloads->id),
            'protected'  => false,
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────

    private function ownedFile(Player $player, string $fileId): ?PlayerFile
    {
        return PlayerFile::where('player_id', $player->id)
            ->where('id', $fileId)
            ->first();
    }

    private function nextSortOrder(string $parentId): int
    {
        return (PlayerFile::where('parent_id', $parentId)->max('sort_order') ?? -1) + 1;
    }

    private function summarize(PlayerFile $f): array
    {
        return [
            'id'        => $f->id,
            'parentId'  => $f->parent_id,
            'name'      => $f->name,
            'type'      => $f->type,
            'extension' => $f->extension,
            'protected' => $f->protected,
        ];
    }

    private function ensureSeeded(Player $player): void
    {
        if (PlayerFile::where('player_id', $player->id)->exists()) {
            return;
        }

        $documents = PlayerFile::create([
            'player_id'  => $player->id,
            'parent_id'  => null,
            'name'       => 'Documents',
            'type'       => 'folder',
            'sort_order' => 0,
            'protected'  => true,
        ]);

        PlayerFile::create([
            'player_id'  => $player->id,
            'parent_id'  => null,
            'name'       => 'Downloads',
            'type'       => 'folder',
            'sort_order' => 1,
            'protected'  => true,
        ]);

        $starters = [
            [
                'name'      => 'stat_guide',
                'extension' => 'txt',
                'content'   => self::STAT_GUIDE,
            ],
            [
                'name'      => 'command_manual',
                'extension' => 'txt',
                'content'   => self::COMMAND_MANUAL,
            ],
            [
                'name'      => 'rig_manual',
                'extension' => 'txt',
                'content'   => self::RIG_MANUAL,
            ],
        ];

        foreach ($starters as $i => $doc) {
            PlayerFile::create([
                'player_id'  => $player->id,
                'parent_id'  => $documents->id,
                'name'       => $doc['name'],
                'type'       => 'file',
                'extension'  => $doc['extension'],
                'content'    => $doc['content'],
                'sort_order' => $i,
                'protected'  => true,
            ]);
        }
    }

    private const STAT_GUIDE = <<<'TXT'
        RIG STAT REFERENCE
        ==================

        SS (System Stability) — your rig's health. Hits zero and you're
        kicked to a safe respawn, losing pocket creds and your current
        bounty. Repair at any CyberDoc.

        UPLINK — fuel for movement. Depletes as you move, refills at
        Uplink nodes. Stranded at zero uplink until you reach one.

        CPU / RAM / OS — the three stats that gate which commands you can
        run and how effective they are. Upgrade via chassis peripherals,
        installed at a CyberDoc.

        BOUNTY LEVEL — rises as you hack nodes. Higher bounty means bigger
        payouts but paints a target on you for other players.
        TXT;

    private const COMMAND_MANUAL = <<<'TXT'
        COMMAND QUICK REFERENCE
        =======================

        Commands are split by context:

          MAP commands  — used while moving between nodes (traps, decoys,
                          scans). Slotted from your loadout, one use per
                          cooldown.

          HACK commands — used during a node hack or Packet Hijack duel.
                          Higher level = faster resolve, better odds.

        Loadout slots are limited by your rig's chassis tier — check the
        RIG page to see how many you've got free.

        Cooldowns are per-command, not global: firing one doesn't lock out
        the others.
        TXT;

    private const RIG_MANUAL = <<<'TXT'
        RIG MAINTENANCE NOTES
        ======================

        Peripherals install at any CyberDoc and boost CPU/RAM/OS depending
        on type. Slots are limited by chassis tier — a chassis upgrade adds
        more room before it adds raw stats.

        Repair costs scale with how far SS has dropped, not a flat fee —
        topping off a small dent is cheap, letting it run to empty before
        repairing costs more overall.

        Reallocating your loadout is free at a CyberDoc and doesn't cost a
        cooldown — there's no reason to run into a hack with an unsuited
        loadout if you're standing at a doc anyway.
        TXT;
}
