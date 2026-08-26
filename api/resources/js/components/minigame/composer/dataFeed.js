/**
 * dataFeed — shared "realistic technical output" generator for the composer.
 *
 * This is the piece underneath every hacking-flavored idea discussed for
 * the composer (a command feed, an artifact-inspection puzzle, terminal
 * flavor text): a pool of fake-but-plausible technical nouns (IPs,
 * hostnames, hashes, ports, timestamps, process names) plus structured
 * ARTIFACT generators built from them — a fake SSL cert, a fake log line —
 * each capable of being generated "clean" or with one deliberate, checkable
 * flaw injected (CN mismatch, expired cert, self-signed, suspicious port,
 * auth failure, unexpected parent process).
 *
 * The flaw is the whole point: unlike pair_match's first cut (an arbitrary
 * string-reversal transform with no real meaning), every artifact here
 * carries real structured `fields`, and `flawed`/`flawType` say exactly
 * which field is wrong and how — so a future win rule can judge based on
 * something a player could actually reason about, the same way a security
 * analyst would eyeball a cert or a log line.
 *
 * Deliberately self-contained: no imports from PacketHijack.vue,
 * ArchiveExtraction.vue, BankHeist.vue, or businessNodes.js. This is new,
 * standalone infrastructure for the composer, inspired by the FEEL of
 * those systems (and by ArchiveExtraction's own flavor-line generator,
 * which does something similar purely for ambient decoration) but with
 * zero dependency on any of them.
 *
 * NOT YET WIRED to any input model or win rule — this is pure content
 * infrastructure, callable by whatever composer content generator wants
 * realistic output next.
 */

// ── Noun pools ──────────────────────────────────────────────────────────────

const DOMAIN_WORDS  = ['northgate', 'ashgrove', 'ironvale', 'brightwell', 'cascade', 'redwire', 'sablepoint', 'greyline', 'fernway', 'quarrytech', 'basalt', 'driftline'];
const DOMAIN_TLDS   = ['net', 'sys', 'io', 'sec', 'com'];
const SUBDOMAINS    = ['mail', 'api', 'vpn', 'admin', 'portal', 'db', 'auth', 'gateway', 'edge', 'ns1'];
const CERT_ISSUERS  = ['DigiTrust CA', 'GlobalSign Root', 'SecureNet Authority', 'IronVale Certificate Services', 'Northwire Root CA', 'Basalt Trust Services'];
const PROCESS_NAMES = ['svchost32', 'auth_daemon', 'netmonitor', 'sshd', 'cron_worker', 'update_agent', 'sysmonitor', 'backup_svc', 'relay_proxy'];
const COMMON_PORTS  = [21, 22, 23, 25, 53, 80, 110, 143, 443, 445, 3306, 3389, 8080, 8443];
const SUSPECT_PORTS = [4444, 31337, 6667, 1337];

function pick(arr) {
    return arr[Math.floor(Math.random() * arr.length)];
}

function shuffle(arr) {
    for (let i = arr.length - 1; i > 0; i--) {
        const j = Math.floor(Math.random() * (i + 1));
        [arr[i], arr[j]] = [arr[j], arr[i]];
    }
    return arr;
}

export function randomIp() {
    return `${1 + Math.floor(Math.random() * 223)}.${Math.floor(Math.random() * 256)}.${Math.floor(Math.random() * 256)}.${1 + Math.floor(Math.random() * 254)}`;
}

export function randomHostname() {
    const sub    = pick(SUBDOMAINS);
    const domain = pick(DOMAIN_WORDS);
    const tld    = pick(DOMAIN_TLDS);
    return `${sub}.${domain}.${tld}`;
}

export function randomHex(len = 8) {
    const chars = '0123456789abcdef';
    let out = '';
    for (let i = 0; i < len; i++) out += chars[Math.floor(Math.random() * chars.length)];
    return out;
}

export function randomPort({ suspect = false } = {}) {
    return suspect ? pick(SUSPECT_PORTS) : pick(COMMON_PORTS);
}

export function randomTimestamp() {
    const h = String(Math.floor(Math.random() * 24)).padStart(2, '0');
    const m = String(Math.floor(Math.random() * 60)).padStart(2, '0');
    const s = String(Math.floor(Math.random() * 60)).padStart(2, '0');
    return `${h}:${m}:${s}`;
}

export function randomProcessName() {
    return pick(PROCESS_NAMES);
}

// ── Structured artifact generators ──────────────────────────────────────────
// Each returns { kind, text, fields, flawed, flawType }. `text` is what
// renders in a terminal feed; `fields` + `flawed`/`flawType` are what a win
// rule actually judges against.

const CERT_FLAW_TYPES = ['cn_mismatch', 'expired', 'self_signed'];

export function generateCertArtifact({ hostname, issuer, validFromYear, validToYear, flawed = false } = {}) {
    const host = hostname || randomHostname();

    // Every legitimate cert in a set is expected to share the same issuer
    // and validity window — generateArtifactSet() pins these once and
    // passes them to every artifact it generates below. Falling back to
    // random here only matters when this function is called standalone
    // (e.g. a smoke test) outside that shared-baseline flow.
    const canonicalIssuer   = issuer ?? pick(CERT_ISSUERS);
    const canonicalFromYear = validFromYear ?? (2024 + Math.floor(Math.random() * 2));
    const canonicalToYear   = validToYear   ?? (canonicalFromYear + 1 + Math.floor(Math.random() * 2));

    let cn         = host;
    let thisIssuer = canonicalIssuer;
    let fromYear   = canonicalFromYear;
    let toYear     = canonicalToYear;
    let selfSigned = false;
    let expired    = false;
    let flawType   = null;

    if (flawed) {
        flawType = pick(CERT_FLAW_TYPES);
        if (flawType === 'cn_mismatch') {
            cn = randomHostname();
        } else if (flawType === 'expired') {
            expired = true;
            // Shift the whole window into the past — a visible deviation
            // from every other cert's shared window, not just a tag on an
            // otherwise-identical-looking date range.
            fromYear = canonicalFromYear - 2;
            toYear   = canonicalFromYear - 1;
        } else if (flawType === 'self_signed') {
            selfSigned = true;
            thisIssuer = cn;
        }
    }

    const text = [
        `Certificate for ${host}:443`,
        `  Subject CN: ${cn}`,
        `  Issuer:     ${thisIssuer}${selfSigned ? ' (self-signed)' : ''}`,
        `  Valid:      ${fromYear}-01-01 to ${toYear}-06-15${expired ? '  [EXPIRED]' : ''}`,
    ].join('\n');

    return {
        kind:   'cert',
        text,
        fields: { host, cn, issuer: thisIssuer, selfSigned, expired, fromYear, toYear },
        flawed,
        flawType,
    };
}

const LOG_FLAW_TYPES = ['unexpected_parent', 'suspicious_port', 'auth_failure'];

export function generateLogLine({ flawed = false } = {}) {
    const ts   = randomTimestamp();
    const proc = randomProcessName();
    const ip   = randomIp();
    const port = randomPort();

    let event    = `CONNECT ${ip}:${port} — OK`;
    let flawType = null;

    if (flawed) {
        flawType = pick(LOG_FLAW_TYPES);
        if (flawType === 'unexpected_parent') {
            event = `SPAWN ${proc} <- parent:unknown (PID mismatch)`;
        } else if (flawType === 'suspicious_port') {
            event = `CONNECT ${ip}:${randomPort({ suspect: true })} — unrecognized service`;
        } else if (flawType === 'auth_failure') {
            event = `AUTH_FAIL user=admin src=${ip} attempts=${3 + Math.floor(Math.random() * 5)}`;
        }
    }

    return {
        kind:   'log',
        text:   `[${ts}] ${proc}: ${event}`,
        fields: { ts, proc, ip, port, event },
        flawed,
        flawType,
    };
}

const GENERATORS = {
    cert: generateCertArtifact,
    log:  generateLogLine,
};

/**
 * Generate a shuffled set of artifacts, with exactly `flawedCount` of them
 * flawed — the shape a "spot the anomaly" win rule needs: N items, a known
 * subset actually wrong, everything else clean.
 *
 * `kind: 'mixed'` shuffles certs and log lines together into one
 * investigation instead of a uniform batch of one type — the point being a
 * command-gated reveal flow (ArtifactInspectInput.vue) where the player
 * has to figure out which command actually applies to a given locked
 * target, not just spam one command for the whole set.
 */
export function generateArtifactSet({ kind = 'cert', count = 4, flawedCount = 1, hostname } = {}) {
    const mixed = kind === 'mixed';
    if (!mixed && !GENERATORS[kind]) {
        throw new Error(`[dataFeed] Unknown artifact kind "${kind}".`);
    }

    // Shared cert baseline (issuer + validity window) — applies to every
    // cert-kind artifact in this set, mixed batch or not, so any cert
    // among a mixed pile is still comparable against the same profile.
    // Without this, every clean cert had a different random issuer/date
    // range too, leaving nothing stable to compare the flawed one against.
    const validFromYear = 2024 + Math.floor(Math.random() * 2);
    const certSharedParams = {
        hostname,
        issuer:       pick(CERT_ISSUERS),
        validFromYear,
        validToYear:  validFromYear + 1 + Math.floor(Math.random() * 2),
    };

    const flawedIdx = new Set();
    while (flawedIdx.size < Math.min(flawedCount, count)) {
        flawedIdx.add(Math.floor(Math.random() * count));
    }

    const items = [];
    for (let i = 0; i < count; i++) {
        const itemKind  = mixed ? pick(['cert', 'log']) : kind;
        const generator = GENERATORS[itemKind];
        const params    = itemKind === 'cert' ? certSharedParams : {};
        items.push({
            id: `artifact_${i}`,
            ...generator({ ...params, flawed: flawedIdx.has(i) }),
        });
    }

    return shuffle(items);
}
