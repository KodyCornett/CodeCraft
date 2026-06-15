/**
 * useGameState
 *
 * Holds all reactive client game state.
 * All values start at sensible empty/zero defaults and are fully replaced
 * by hydrate() once the API responds. Nothing here is hardcoded game data.
 *
 * Replaces useMockGameState.js.
 */

import { ref } from 'vue';
import axios   from 'axios';

export function useGameState() {

    // ── Player ────────────────────────────────────────────────────────────────
    const player = ref({
        id:                  null,
        handle:              null,
        persona:             null,
        persona_desc:        null,
        district:            null,
        currentNodeCanvasId: null,   // last persisted canvas node ID — used to restore position on reload

        // Economy
        creds:               0,    // wallet — safe, banked
        pocketCreds:         0,    // at-risk until banked at Street Doc

        // Uplink — current/max pool. Max mirrors chassis base_uplink.
        uplink:              0,
        maxUplink:           0,

        // Tech
        techPoints:          0,

        // Bounty
        bountyLevel:         0,
        bountyMultiplier:    1.0,
        isOpenSeason:        false,
        movesSinceLastPing:  0,

        // Run stats
        nodesHackedThisRun:  0,
        pvpWinsThisRun:      0,

        // Health
        currentSS:           0,
        maxSS:               0,
        isLimping:           false,
    });

    // ── Rig ───────────────────────────────────────────────────────────────────
    const rig = ref({
        id:        null,
        chassis:   null,
        tier:      1,

        // Effective stats (as returned by RigService::effectiveStats)
        cpu:       0,
        ram:       0,
        os:        0,
        storage:   0,
        firewall:  0,
        uplink:    0,

        // Stat caps from chassis
        caps: {
            cpu:      0,
            ram:      0,
            os:       0,
            storage:  0,
            firewall: 0,
        },

        // Invested levels (points above base)
        investedPoints: {
            cpu:      0,
            ram:      0,
            os:       0,
            storage:  0,
            firewall: 0,
        },

        // Point budget
        pointsSpent: 0,
        pointsCap:   0,

        peripheralSlots: 0,
        isLimping:       false,
    });

    // ── Commands — fetched from /api/player/commands ──────────────────────────
    const commands = ref([]);

    // ── Inventory — fetched from /api/inventory ──────────────────────────────
    // Shape: { hardware: [...uninstalled encrypts], consumables: [...owned rows] }
    const inventory = ref({ hardware: [], consumables: [] });

    // ── Bounties — managed by useBountyBoard ─────────────────────────────────
    // Kept here as a stub so the provide() call in Game.vue stays consistent.
    const bounties = ref([]);

    // ─────────────────────────────────────────────────────────────────────────
    // Hydration helpers
    // ─────────────────────────────────────────────────────────────────────────

    /**
     * Seed player + rig from the /api/player/me response.
     * Call this in Game.vue after useAuth.login() resolves.
     */
    function hydrateFromAuth(authPlayer, authRig) {
        if (authPlayer) {
            player.value.id                  = authPlayer.id                      ?? null;
            player.value.handle              = authPlayer.handle                  ?? null;
            player.value.persona             = authPlayer.persona                 ?? null;
            player.value.persona_desc        = authPlayer.persona_desc            ?? null;
            player.value.district            = authPlayer.current_district        ?? null;
            player.value.currentNodeCanvasId = authPlayer.current_node_canvas_id  ?? null;
            player.value.creds               = authPlayer.wallet_creds            ?? 0;
            player.value.pocketCreds         = authPlayer.pocket_creds            ?? 0;
            player.value.techPoints          = authPlayer.tech_points             ?? 0;
            player.value.bountyLevel         = authPlayer.bounty_level            ?? 0;
            player.value.bountyMultiplier    = authPlayer.bounty_multiplier       ?? 1.0;
            player.value.isOpenSeason        = authPlayer.is_open_season          ?? false;
            player.value.nodesHackedThisRun  = authPlayer.nodes_hacked_this_run   ?? 0;
            player.value.pvpWinsThisRun      = authPlayer.pvp_wins_this_run       ?? 0;
            player.value.isLimping           = authPlayer.is_limping              ?? false;
            player.value.isDev               = authPlayer.is_dev                  ?? false;
        }

        if (authRig) {
            const s = authRig.stats ?? {};

            rig.value.id             = authRig.rig_id      ?? null;
            rig.value.chassis        = authRig.chassis      ?? null;
            rig.value.tier           = authRig.tier         ?? 1;
            rig.value.isLimping      = authRig.is_limping   ?? false;

            // Effective stats
            rig.value.cpu      = s.cpu?.effective      ?? 0;
            rig.value.ram      = s.ram?.effective      ?? 0;
            rig.value.firewall = s.firewall?.effective ?? 0;
            rig.value.storage  = s.storage?.effective  ?? 0;
            rig.value.os       = s.os?.effective       ?? 0;

            // Uplink — chassis max is locked; current is the persisted remaining pool.
            const maxUplink        = authRig.uplink         ?? 0;
            const curUplink        = authRig.current_uplink ?? maxUplink;
            rig.value.uplink       = maxUplink;
            player.value.uplink    = curUplink;     // remaining this run
            player.value.maxUplink = maxUplink;     // chassis maximum

            // SS
            player.value.currentSS = authRig.current_ss ?? 0;
            player.value.maxSS     = authRig.max_ss      ?? 0;

            // Caps + invested
            rig.value.caps           = authRig.caps     ?? rig.value.caps;
            rig.value.investedPoints = authRig.invested ?? rig.value.investedPoints;

            // Points
            rig.value.pointsSpent     = authRig.points?.spent ?? 0;
            rig.value.pointsCap       = authRig.points?.cap   ?? 0;
            rig.value.peripheralSlots = authRig.peripheral_slots ?? 0;
            rig.value.loadoutSlots    = authRig.loadout_slots    ?? null;
        }
    }

    /**
     * Fetch and replace the commands list from /api/player/commands.
     */
    async function fetchCommands() {
        try {
            const res = await axios.get('/api/player/commands');
            commands.value = res.data.commands ?? [];
        } catch (e) {
            console.error('[useGameState] Failed to fetch commands:', e?.response?.data ?? e.message);
        }
    }

    /**
     * Fetch the player's current inventory from /api/inventory.
     */
    async function fetchInventory() {
        try {
            const res = await axios.get('/api/inventory');
            inventory.value = {
                hardware:    res.data.hardware    ?? [],
                consumables: res.data.consumables ?? [],
            };
        } catch (e) {
            console.error('[useGameState] Failed to fetch inventory:', e?.response?.data ?? e.message);
        }
    }

    /**
     * Use a consumable from the player's inventory.
     * Returns the raw API result object, or null on failure.
     */
    async function useConsumable(consumableId) {
        try {
            const res    = await axios.post('/api/inventory/use', { consumable_id: consumableId });
            const result = res.data;

            if (result.type === 'repair') {
                player.value.currentSS = result.current_ss;
                if (result.is_limping !== undefined) {
                    player.value.isLimping = result.is_limping;
                }
            }

            // Decrement quantity or remove from local inventory list
            const idx = inventory.value.consumables.findIndex(c => c.consumable_id === consumableId);
            if (idx !== -1) {
                const item = inventory.value.consumables[idx];
                if (item.quantity <= 1) {
                    inventory.value.consumables.splice(idx, 1);
                } else {
                    item.quantity--;
                }
            }

            return result;
        } catch (e) {
            console.error('[useGameState] useConsumable failed:', e?.response?.data?.message ?? e.message);
            return null;
        }
    }

    /**
     * Upgrade an owned command by one level at the Street Doc.
     * Returns true on success, false on failure.
     */
    async function upgradeCommand(playerId, commandId) {
        try {
            const res = await axios.post('/api/cyberdoc/upgrade-command', {
                player_id:  playerId,
                command_id: commandId,
            });

            const cmd = commands.value.find(c => c.id === commandId);
            if (cmd) cmd.level = res.data.new_level;

            player.value.techPoints = res.data.tech_points;

            return true;
        } catch (e) {
            console.error('[useGameState] Command upgrade failed:', e?.response?.data?.message ?? e.message);
            return false;
        }
    }

    /**
     * Re-fetch /api/player/me and return the raw response data.
     * Callers patch their own reactive refs from the returned { player, rig }.
     * Used after events (PvP resolve, decline penalty) where server state drifts.
     * @returns {Promise<{player, rig}|null>}
     */
    async function resyncPlayer() {
        try {
            const res = await axios.get('/api/player/me');
            return res.data;
        } catch {
            return null;
        }
    }

    /**
     * Register a self-targeted command activation server-side so backend effects
     * (Ghost Protocol trace suppression, Blackout, Firewall Patch) are enforced
     * across reconnects.
     * @returns {Promise<boolean>}
     */
    async function activateCommand(commandId) {
        try {
            await axios.post('/api/player/activate-command', { command_id: commandId });
            return true;
        } catch (e) {
            if (import.meta.env.DEV) {
                console.warn('[CMD] Server activation failed:', e?.response?.data);
            }
            return false;
        }
    }

    return {
        player,
        rig,
        commands,
        inventory,
        bounties,
        hydrateFromAuth,
        fetchCommands,
        fetchInventory,
        upgradeCommand,
        useConsumable,
        resyncPlayer,
        activateCommand,
    };
}
