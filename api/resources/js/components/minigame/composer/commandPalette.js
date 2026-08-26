/**
 * commandPalette — the command vocabulary for command-gated artifact
 * reveals, used by ArtifactInspectInput.vue.
 *
 * Kept separate from dataFeed.js on purpose: dataFeed.js decides what an
 * artifact IS (a cert, a log line, with real fields and a possible flaw);
 * this file decides what COMMAND a player has to run to actually see one.
 * Neither imports the other — they only agree on the `kind` string
 * ('cert' | 'log') dataFeed.js already stamps on every artifact it
 * generates.
 *
 * Includes two decoys (whois, ping) that never reveal anything here — real
 * commands that exist in the real world but aren't the right tool for this
 * job, so picking the right command is itself part of the challenge, not
 * just a formality before the "real" mechanic starts.
 */

const COMMANDS = [
    { key: 'openssl', label: 'openssl s_client -connect <target>:443', revealsKind: 'cert' },
    { key: 'cat_log', label: 'cat access.log <target>',                revealsKind: 'log'  },
    { key: 'whois',   label: 'whois <target>',                          revealsKind: null   },
    { key: 'ping',    label: 'ping <target>',                           revealsKind: null   },
];

export function getCommandPalette() {
    return COMMANDS;
}

export function findCommand(key) {
    return COMMANDS.find(c => c.key === key) ?? null;
}

function shuffle(arr) {
    const out = [...arr];
    for (let i = out.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [out[i], out[j]] = [out[j], out[i]];
    }
    return out;
}

/**
 * selectCommands — the per-instance command subset a generated minigame
 * exposes, rather than every instance always showing the full fixed
 * vocabulary. Always includes the real command for every artifact kind
 * actually present in that instance's set (so the puzzle stays solvable —
 * an instance never demands a command it doesn't also supply), then tops
 * up with decoys drawn from whatever's left in COMMANDS (other kinds'
 * commands, plus whois/ping). This is the seam composeMinigame.js uses so
 * "which commands are on the palette" becomes a choice each instance
 * makes, not a global constant.
 *
 * @param {string[]} kindsPresent  Distinct artifact `kind`s in this instance's set.
 * @param {number}   decoyCount    How many non-revealing-for-this-set commands to add.
 */
export function selectCommands(kindsPresent, decoyCount = 2) {
    const required = COMMANDS.filter(c => kindsPresent.includes(c.revealsKind));
    const rest      = COMMANDS.filter(c => !required.includes(c));
    const decoys    = shuffle(rest).slice(0, decoyCount);
    return shuffle([...required, ...decoys]);
}

/** Does running this command against an artifact of `artifactKind` reveal it? */
export function commandReveals(commandKey, artifactKind) {
    const cmd = findCommand(commandKey);
    return !!cmd && cmd.revealsKind === artifactKind;
}
