<?php

namespace App\Services;

use App\Models\Player;
use App\Models\PlayerFile;

/**
 * Backs the File Explorer OS program (Phase 1 — read-only, see
 * CONTRACTS_AND_OS_REWORK_PLAN.md).
 *
 * Seeds a starter "Documents" folder the first time a player has no files
 * yet, so every player boots into a File Explorer that already has
 * something real in it. Everything below is read-only from the client's
 * perspective — writes only ever happen here (seeding) or, later, from
 * mission logic that inserts a file into a specific player's tree.
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
            ->map(fn (PlayerFile $f) => [
                'id'        => $f->id,
                'parentId'  => $f->parent_id,
                'name'      => $f->name,
                'type'      => $f->type,
                'extension' => $f->extension,
            ])
            ->toArray();
    }

    /**
     * Returns one file's content, or null if it doesn't exist, isn't owned
     * by this player, or is a folder (folders have no content to read).
     */
    public function getFileContent(Player $player, string $fileId): ?array
    {
        $file = PlayerFile::where('player_id', $player->id)
            ->where('id', $fileId)
            ->first();

        if ($file === null || $file->isFolder()) {
            return null;
        }

        return [
            'id'        => $file->id,
            'name'      => $file->name,
            'extension' => $file->extension,
            'content'   => $file->content,
        ];
    }

    // ─────────────────────────────────────────────────────────────────────────

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
