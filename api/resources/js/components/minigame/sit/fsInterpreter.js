/**
 * fsInterpreter — pure logic for SIT's (Splice Interface Terminal) command line.
 *
 * No Vue, no DOM. Given a virtual filesystem tree, a small piece of session
 * state ({ cwd, solved }), and one typed line, returns the output lines to
 * print plus the (possibly updated) state. TerminalShell.vue only renders
 * lines and captures typed input — it never interprets a command itself;
 * SIT.vue is the only caller of runCommand().
 *
 * Filesystem shape — a node is either:
 *   { type: 'dir',  name, children: { [name]: node, ... } }
 *   { type: 'file', name, content: string }
 *
 * The universal verbs (ls, cd, cat, help) are hardcoded here because every
 * scenario needs them. A scenario's actual OBJECTIVE verb (login,
 * connect, whatever fits its puzzle) is NOT hardcoded — it's supplied by
 * the scenario itself via `scenario.verbs = { name: { usage, run(args,
 * state) => ({ output, solved }) } }`. This is what lets a second scenario
 * ship a completely different final command (connect <ip> instead of
 * login <user> <pass>) without editing this shared file at all.
 *
 * getSuggestions() is the one addition purely in service of accessibility:
 * a player who has never used a real terminal shouldn't have to already
 * know this vocabulary to use it. It only ever suggests command/verb
 * NAMES and ls/cd/cat path arguments — never a scenario verb's arguments
 * (login's user/pass, connect's ip, etc.) — so it helps with syntax
 * without ever leaking a puzzle answer. That guarantee falls out of the
 * same code path regardless of which verbs a scenario defines: only cd/
 * cat/ls get argument suggestions at all.
 */

export const COMMAND_NAMES = ['ls', 'cd', 'cat', 'help'];

function splitPath(path) {
    return path.split('/').filter(Boolean);
}

/** Resolve a typed path (relative or absolute, with . and ..) against cwd into a segment array. */
export function resolveSegments(cwdSegments, pathArg) {
    let segments = pathArg.startsWith('/') ? [] : [...cwdSegments];
    for (const part of splitPath(pathArg)) {
        if (part === '.') continue;
        if (part === '..') { segments.pop(); continue; }
        segments.push(part);
    }
    return segments;
}

/** Walk the tree to the node at `segments`, or null if any part of the path doesn't exist. */
export function getNode(root, segments) {
    let node = root;
    for (const seg of segments) {
        if (!node || node.type !== 'dir' || !node.children[seg]) return null;
        node = node.children[seg];
    }
    return node;
}

export function pathString(segments) {
    return '/' + segments.join('/');
}

/**
 * Run one typed line against the filesystem + current session state.
 *
 * @param {Object} root      Root directory node.
 * @param {Object} state     { cwd: string[], solved: boolean }
 * @param {string} rawLine   Whatever the player typed.
 * @param {Object} scenario  { verbs?: { [name]: { usage, run(args, state) => ({ output, solved }) } } }
 * @returns {{ output: string[], state: Object }}
 */
export function runCommand(root, state, rawLine, scenario) {
    const line = rawLine.trim();
    if (!line) return { output: [], state };

    const [cmdRaw, ...args] = line.split(/\s+/);
    const cmd = cmdRaw.toLowerCase();
    const cwd = state.cwd;
    const verbs = scenario?.verbs ?? {};

    switch (cmd) {
        case 'help': {
            const output = [
                'available commands:',
                '  ls [path]              list a directory',
                '  cd <path>               change directory (.. goes up, no arg goes home)',
                '  cat <file>              print a file\'s contents',
            ];
            for (const verb of Object.values(verbs)) {
                output.push(`  ${verb.usage}`);
            }
            return { output, state };
        }

        case 'ls': {
            const targetSegs = args[0] ? resolveSegments(cwd, args[0]) : cwd;
            const node = getNode(root, targetSegs);
            if (!node) return { output: [`ls: no such directory: ${args[0]}`], state };
            if (node.type !== 'dir') return { output: [`ls: not a directory: ${args[0]}`], state };
            const names = Object.values(node.children)
                .map(c => (c.type === 'dir' ? c.name + '/' : c.name))
                .sort();
            return { output: [names.length ? names.join('   ') : '(empty)'], state };
        }

        case 'cd': {
            const targetSegs = args[0] ? resolveSegments(cwd, args[0]) : [];
            const node = getNode(root, targetSegs);
            if (!node) return { output: [`cd: no such directory: ${args[0]}`], state };
            if (node.type !== 'dir') return { output: [`cd: not a directory: ${args[0]}`], state };
            return { output: [], state: { ...state, cwd: targetSegs } };
        }

        case 'cat': {
            if (!args[0]) return { output: ['cat: missing filename'], state };
            const targetSegs = resolveSegments(cwd, args[0]);
            const node = getNode(root, targetSegs);
            if (!node) return { output: [`cat: no such file: ${args[0]}`], state };
            if (node.type !== 'file') return { output: [`cat: is a directory: ${args[0]}`], state };
            return { output: node.content.split('\n'), state };
        }

        default: {
            const verb = verbs[cmd];
            if (!verb) {
                return { output: [`command not recognized: ${cmd}`], state };
            }
            const result = verb.run(args, state);
            const nextState = result.solved ? { ...state, solved: true } : state;
            return { output: result.output, state: nextState };
        }
    }
}

/**
 * Candidate completions for whatever the player is currently typing —
 * always full replacement values for the LAST token (the caller just
 * swaps the last token wholesale, it never has to know why).
 *
 * - No text yet, or still typing the first word → matching command/verb
 *   names (universal commands plus whatever verbs this scenario defines).
 * - Typing an argument to ls/cd/cat → matching file/directory names from
 *   whatever directory the partial path resolves against.
 * - Anything else (a scenario verb's arguments, help's no-args) → nothing.
 *   Deliberately never suggests a scenario verb's arguments — that's the
 *   one place a suggestion could hand over part of the actual puzzle
 *   answer, and it holds for every scenario automatically since only
 *   cd/cat/ls get argument-level suggestions at all.
 *
 * @param {Object} root      Root directory node.
 * @param {Object} state     { cwd: string[], solved: boolean }
 * @param {string} typedText Whatever's currently in the input.
 * @param {Object} [scenario] { verbs?: { [name]: {...} } } — used only to list verb names.
 * @returns {string[]}
 */
export function getSuggestions(root, state, typedText, scenario) {
    const verbNames = Object.keys(scenario?.verbs ?? {});
    const allNames = [...COMMAND_NAMES, ...verbNames];

    const endsWithSpace = /\s$/.test(typedText);
    const parts = typedText.split(/\s+/).filter(Boolean);

    if (parts.length === 0) {
        return allNames;
    }

    if (parts.length === 1 && !endsWithSpace) {
        const prefix = parts[0].toLowerCase();
        return allNames.filter(c => c.startsWith(prefix));
    }

    const cmd = parts[0].toLowerCase();
    if (cmd !== 'cd' && cmd !== 'cat' && cmd !== 'ls') {
        return [];
    }

    const rawArg = endsWithSpace ? '' : parts[parts.length - 1];
    const lastSlash = rawArg.lastIndexOf('/');
    const dirPart = lastSlash >= 0 ? rawArg.slice(0, lastSlash) : '';
    const partial = lastSlash >= 0 ? rawArg.slice(lastSlash + 1) : rawArg;

    const baseSegs = dirPart ? resolveSegments(state.cwd, dirPart) : state.cwd;
    const node = getNode(root, baseSegs);
    if (!node || node.type !== 'dir') return [];

    return Object.values(node.children)
        .map(c => (c.type === 'dir' ? c.name + '/' : c.name))
        .filter(name => name.toLowerCase().startsWith(partial.toLowerCase()))
        .sort()
        .map(name => (dirPart ? dirPart + '/' + name : name));
}
