<?php

namespace Database\Seeders;

use App\Models\Command;
use App\Models\Consumable;
use App\Models\Peripheral;
use App\Services\CyberDocInventoryService;
use Illuminate\Database\Seeder;

/**
 * Seeds the cyber_doc_catalog table.
 *
 * ── Global items (every doc) ──────────────────────────────────────────────────
 *   All repair consumables — seeded with cyber_doc_id = null.
 *
 * ── Per-doc inventory ─────────────────────────────────────────────────────────
 *
 *   Patch    (NS-hub) — Budget starter hub. Common peripherals, basic commands.
 *   Knuckle  (BA-hub) — Combat and brawl. ICE Picks, offensive hack commands.
 *   Veil     (DT-hub) — Stealth and deception. OS hardware, evasion/counter commands.
 *   Axiom    (UD-hub) — Research and tech. High-end CPU/storage, Packet Hijack specialists.
 *   Float    (SV-hub) — Utility and defense. Firewall hardware, mixed command set.
 *
 * ── Adding items via missions, events, or admin calls ─────────────────────────
 *
 *   Use CyberDocInventoryService::grantCatalogItem() directly from any context:
 *
 *   // Mission-exclusive chassis at Float, one unit only
 *   $service->grantCatalogItem('SV-hub', 'chassis', $chassisId, [
 *       'is_exclusive' => true,
 *       'stock_limit'  => 1,
 *       'source'       => 'mission:valley_contract_03',
 *   ]);
 *
 *   // 24-hour timed special at Veil
 *   $service->grantCatalogItem('DT-hub', 'peripheral', $peripheralId, [
 *       'source'          => 'rotation:2026-06-04',
 *       'available_until' => now()->addHours(24),
 *   ]);
 */
class CyberDocCatalogSeeder extends Seeder
{
    public function __construct(private readonly CyberDocInventoryService $inventory) {}

    private const NS = 'NS-hub';
    private const BA = 'BA-hub';
    private const DT = 'DT-hub';
    private const UD = 'UD-hub';
    private const SV = 'SV-hub';

    public function run(): void
    {
        $this->seedGlobalRepairKits();
        $this->seedPatch();
        $this->seedKnuckle();
        $this->seedVeil();
        $this->seedAxiom();
        $this->seedFloat();

        $this->command?->info('CyberDocCatalogSeeder: all doc inventories seeded.');
    }

    // -------------------------------------------------------------------------
    // Global — repair consumables at every doc
    // -------------------------------------------------------------------------

    private function seedGlobalRepairKits(): void
    {
        $kits = Consumable::where('category', 'repair')->get();

        if ($kits->isEmpty()) {
            $this->command?->warn('CyberDocCatalogSeeder: no repair consumables found — run ConsumableSeeder first.');
            return;
        }

        foreach ($kits as $kit) {
            $this->inventory->grantCatalogItem(null, 'consumable', $kit->id, ['source' => 'seed']);
        }

        $this->command?->info("  [global] {$kits->count()} repair consumable(s).");
    }

    // ── Patch's Clinic — North Spokane ────────────────────────────────────────
    // Budget starter hub. Common peripherals, entry-level commands.

    private function seedPatch(): void
    {
        $this->peripherals(self::NS, [
            'Bare-Metal CPU Patch',
            'Budget RAM Stick',
            'Copper NIC',
            'Surplus Drive',
            'Bootleg OS Patch',
            'Deep Link Mk.I',
        ]);
        $this->software(self::NS, ['Scan Patch v1.1']);
        $this->commands(self::NS, [
            'Ghost Protocol', 'Signal Noise', 'Crash',
            'Trace Route', 'Static Burst',
        ]);
        $this->command?->info('  [NS-hub] Patch seeded.');
    }

    // ── Knuckle's Med-Wagon — Browne's Addition ───────────────────────────────
    // Combat and brawl. ICE Pick modules, offensive hack commands.

    private function seedKnuckle(): void
    {
        $this->peripherals(self::BA, [
            'ICE Pick Mk.I', 'ICE Pick Mk.II',
            'Overclocked CPU Module', 'Hardened NIC',
        ]);
        $this->software(self::BA, ['Scan Patch v1.1']);
        $this->commands(self::BA, [
            'Packet Flood', 'Blackout', 'OS Exploit',
            'Hardlock', 'Null Byte', 'Data Spike', 'Sector Corrupt',
        ]);
        $this->command?->info('  [BA-hub] Knuckle seeded.');
    }

    // ── Veil's Parlour — Downtown ─────────────────────────────────────────────
    // Stealth and deception. OS hardware, evasion and counter commands.

    private function seedVeil(): void
    {
        $this->peripherals(self::DT, [
            'Ghost OS Kernel', 'Daemon Core Kernel',
            'Ghost RAM Module', 'Deep Link Mk.II',
        ]);
        $this->software(self::DT, ['Stealth Kernel']);
        $this->commands(self::DT, [
            'Dark Mode', 'Decoy', 'RootKit', 'Signal Noise',
            'Phase Shift', 'Mirror Protocol', 'Bait',
        ]);
        $this->command?->info('  [DT-hub] Veil seeded.');
    }

    // ── Axiom Systems — University District ──────────────────────────────────
    // Research and tech. High-end CPU/storage, Packet Hijack specialists.

    private function seedAxiom(): void
    {
        $this->peripherals(self::UD, [
            'Hardwired CPU Core', 'Expanded Drive Array',
            'Deep Vault Array', 'Nav Wraith Mk.I', 'Nav Wraith Mk.II',
        ]);
        $this->software(self::UD, ['Zero-Day Exploit Pack']);
        $this->commands(self::UD, [
            'Buffer Overflow', 'Ghost Protocol',
            'Sector Purge', 'Phantom Key', 'Overclock', 'Trace Route',
        ]);
        $this->command?->info('  [UD-hub] Axiom seeded.');
    }

    // ── Float's Repair Bay — Spokane Valley ──────────────────────────────────
    // Utility and defense. Firewall hardware, mixed command set.

    private function seedFloat(): void
    {
        $this->peripherals(self::SV, [
            'Military-Grade Firewall Card', 'Hardened NIC',
            'Deep Link Mk.I', 'Nav Wraith Mk.I', 'ICE Pick Mk.I',
        ]);
        $this->software(self::SV, ['Scan Patch v1.1', 'Stealth Kernel']);
        $this->commands(self::SV, [
            'Crash', 'Blackout', 'Decoy',
            'Overclock', 'Phase Shift', 'Hardlock',
        ]);
        $this->command?->info('  [SV-hub] Float seeded.');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    private function peripherals(string $canvasId, array $names): void
    {
        foreach ($names as $name) {
            $item = Peripheral::where('name', $name)->first();
            if ($item === null) {
                $this->command?->warn("  CyberDocCatalogSeeder: peripheral '{$name}' not found — skipped.");
                continue;
            }
            $this->inventory->grantCatalogItem($canvasId, 'peripheral', $item->id, ['source' => 'seed']);
        }
    }

    private function software(string $canvasId, array $names): void
    {
        foreach ($names as $name) {
            $item = Consumable::where('name', $name)->first();
            if ($item === null) {
                $this->command?->warn("  CyberDocCatalogSeeder: consumable '{$name}' not found — skipped.");
                continue;
            }
            $this->inventory->grantCatalogItem($canvasId, 'consumable', $item->id, ['source' => 'seed']);
        }
    }

    private function commands(string $canvasId, array $names): void
    {
        foreach ($names as $name) {
            $item = Command::where('name', $name)->first();
            if ($item === null) {
                $this->command?->warn("  CyberDocCatalogSeeder: command '{$name}' not found — skipped.");
                continue;
            }
            $this->inventory->grantCatalogItem($canvasId, 'command', $item->id, ['source' => 'seed']);
        }
    }
}
