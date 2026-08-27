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
 * Supported verbs are intentionally the small, real set a person actually
 * types at a shell: ls, cd, cat, login, help. No made-up game-only verbs —
 * that's the whole point of this being a terminal instead of another
 * button-per-mechanic widget.
 */

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
 * @param {Object} scenario  { checkWin(user, password) => boolean }
 * @returns {{ output: string[], state: Object }}
 */
export function runCommand(root, state, rawLine, scenario) {
    const line = rawLine.trim();
    if (!line) return { output: [], state };

    const [cmdRaw, ...args] = line.split(/\s+/);
    const cmd = cmdRaw.toLowerCase();
    const cwd = state.cwd;

    switch (cmd) {
        case 'help':
            return {
                output: [
                    'available commands:',
                    '  ls [path]              list a directory',
                    '  cd <path>               change directory (.. goes up, no arg goes home)',
                    '  cat <file>              print a file\'s contents',
                    '  login <user> <pass>     attempt to authenticate',
                ],
                state,
            };

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

        case 'login': {
            if (args.length < 2) return { output: ['login: usage — login <user> <password>'], state };
            const [user, password] = args;
            const ok = scenario.checkWin(user, password);
            if (ok) {
                return { output: ['ACCESS GRANTED.'], state: { ...state, solved: true } };
            }
            return { output: ['ACCESS DENIED — invalid credentials.'], state };
        }

        default:
            return { output: [`command not recognized: ${cmd}`], state };
    }
}
