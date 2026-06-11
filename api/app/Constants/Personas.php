<?php

namespace App\Constants;

/**
 * The 20 runner Personas available on The Splice Frequency.
 *
 * Each entry: ['name' => string, 'desc' => string]
 *
 * Used for:
 *   - Validation in PersonaController (name must be in this list)
 *   - Token replacement in quest text ({persona} and {persona_desc})
 *   - The Splice personas reference page
 *
 * This list is permanent. Do not remove entries — players have selected them.
 * New entries may be appended in future updates.
 */
class Personas
{
    public const LIST = [
        ['name' => 'Ghost',    'desc' => 'Leaves no trace. Exists only in the negative space between system logs.'],
        ['name' => 'Wraith',   'desc' => 'Something the network dreamed up and couldn\'t delete.'],
        ['name' => 'Pirate',   'desc' => 'Takes what the system locked away and calls it liberation.'],
        ['name' => 'Phantom',  'desc' => 'Was declared dead by three different registries. All three were right.'],
        ['name' => 'Vector',   'desc' => 'Every system has an entry point. This one finds them before breakfast.'],
        ['name' => 'Cipher',   'desc' => 'Speaks exclusively in problems the system doesn\'t know it has.'],
        ['name' => 'Relic',    'desc' => 'Runs protocols the network forgot existed. Older than the grid itself.'],
        ['name' => 'Parasite', 'desc' => 'Doesn\'t break systems. Lives inside them until they stop working.'],
        ['name' => 'Nomad',    'desc' => 'No fixed node. No fixed identity. The grid can\'t tax what it can\'t locate.'],
        ['name' => 'Shade',    'desc' => 'Operates in the half-second between a system\'s question and its answer.'],
        ['name' => 'Fracture', 'desc' => 'Doesn\'t find the crack in the wall. Becomes it.'],
        ['name' => 'Static',   'desc' => 'Impossible to read. Impossible to ignore. Ruins everything near it.'],
        ['name' => 'Hydra',    'desc' => 'Kill one process, two more open. The network learned to leave it alone.'],
        ['name' => 'Null',     'desc' => 'Exists in the system as an error it decided not to investigate.'],
        ['name' => 'Specter',  'desc' => 'The ping always misses. The trace always goes cold.'],
        ['name' => 'Entropy',  'desc' => 'Doesn\'t attack the system. Just waits for it to collapse inward.'],
        ['name' => 'Crucible', 'desc' => 'Pressure is the point. Runs hotter under surveillance than without it.'],
        ['name' => 'Vagrant',  'desc' => 'Unregistered. Untracked. Surviving on what the network drops on the floor.'],
        ['name' => 'Conduit',  'desc' => 'Doesn\'t hold data. Moves it. The network\'s most useful ghost.'],
        ['name' => 'Override', 'desc' => 'Doesn\'t ask the system for permission. Never has.'],
    ];

    /** Returns an array of just the name strings for validation. */
    public static function names(): array
    {
        return array_column(self::LIST, 'name');
    }

    /** Returns the descriptor for a given persona name, or null if not found. */
    public static function descFor(string $name): ?string
    {
        foreach (self::LIST as $p) {
            if ($p['name'] === $name) return $p['desc'];
        }
        return null;
    }
}
