/**
 * codexPageRoutes
 *
 * Single source of truth mapping every splice_pages.slug (see
 * SplicePageSeeder.php) to the dedicated splice:// URL that renders it.
 *
 * The Codex system's backend (CodexService/CodexController) only knows
 * slugs — it has no concept of frontend routing. This map is what lets
 * Decrypter.vue's History list and each codex-tier terminal page's
 * "leads" list turn a bare slug into an actual navigable page, via
 * `inject('spliceNavigate')`.
 *
 * Keep in sync with SpliceRouter.js's ROUTES table — every slug here
 * should resolve to a real entry there.
 */
export const CODEX_PAGE_ROUTES = {
    // ── 15 flavor pages — public-facing SPLICE sites ──────────────────────────
    'avista-grid':         'splice://avista-grid.com',
    'providence-health':   'splice://providence-health.med',
    'itron-telemetry':     'splice://itron-telemetry.io',
    'wwp-archive':         'splice://wwp-archives.org',
    'gonzaga-whitepaper':  'splice://gonzaga-research.edu',
    'sta-transit':         'splice://sta-transit.net',
    'copperhead-parts':    'splice://copperhead-chassis.net',
    'inland-leasing':      'splice://inland-properties.biz',
    'stitchers-market':    'splice://stitchers-market.onion',
    'null-forum':          'splice://null-front.board',
    'spectre-manifesto':   'splice://spectre-defense.militia',
    'sin-news':            'splice://sin-news.com',
    'ibj-financial':       'splice://inland-biz.io',
    'valley-voice-news':   'splice://valley-voice.org',
    'wire-dead-leak':      'splice://wire-dead.net',

    // ── 5 codex-tier pages — restricted terminals on their parent's domain ────
    'codex-avista-substation-09':        'splice://avista-grid.com/sub09-terminal',
    'codex-gonzaga-lab-404':             'splice://gonzaga-research.edu/lab404-terminal',
    'codex-sta-tunnel-vault-04':         'splice://sta-transit.net/vault04-terminal',
    'codex-copperhead-freight-manifest': 'splice://copperhead-chassis.net/freight-terminal',
    'codex-null-irc-relay':              'splice://null-front.board/irc-relay',
};

/** Slug -> URL, or null if unmapped (shouldn't happen for real Codex slugs). */
export function routeForSlug(slug) {
    return CODEX_PAGE_ROUTES[slug] ?? null;
}

/**
 * Name-based search index for the 15 public flavor pages, so a player can
 * type a company's actual name into the SPLICE address bar / New Tab search
 * (e.g. "avista") instead of needing to already know its exact domain.
 *
 * Deliberately excludes the 5 codex-tier restricted terminals — those stay
 * reachable only via a resolved key or a lead link, same as before. Making
 * them name-searchable would let a player "find" a locked terminal by
 * guessing a company name, which undercuts the credential-hunt mechanic
 * even though the page itself would still show as locked.
 */
const SEARCH_INDEX = [
    { url: CODEX_PAGE_ROUTES['avista-grid'],       terms: ['avista', 'a.v.i.s.t.a.', 'avista grid', 'alpine valley'] },
    { url: CODEX_PAGE_ROUTES['providence-health'], terms: ['providence', 'p.r.o.v.i.d.e.n.c.e.', 'providence health', 'providence healthcare'] },
    { url: CODEX_PAGE_ROUTES['itron-telemetry'],   terms: ['itron', 'i.t.r.o.n.', 'itron telemetry'] },
    { url: CODEX_PAGE_ROUTES['wwp-archive'],       terms: ['wwp', 'w.w.p.', 'wwp archive', 'west-cascade', 'west cascade'] },
    { url: CODEX_PAGE_ROUTES['gonzaga-whitepaper'],terms: ['gonzaga', 'g.o.n.z.a.g.a.', 'gonzaga research'] },
    { url: CODEX_PAGE_ROUTES['sta-transit'],       terms: ['sta', 's.t.a.', 'sta transit', 'spokane transit'] },
    { url: CODEX_PAGE_ROUTES['copperhead-parts'],  terms: ['copperhead', 'c.o.p.p.e.r.h.e.a.d.'] },
    { url: CODEX_PAGE_ROUTES['inland-leasing'],    terms: ['inland properties', 'inland commercial', 'inland leasing'] },
    { url: CODEX_PAGE_ROUTES['stitchers-market'],  terms: ['stitchers', 's.t.i.t.c.h.e.r.s.', 'stitchers market'] },
    { url: CODEX_PAGE_ROUTES['null-forum'],        terms: ['null', 'n.u.l.l.', 'null forum', 'null front'] },
    { url: CODEX_PAGE_ROUTES['spectre-manifesto'], terms: ['spectre', 's.p.e.c.t.r.e.'] },
    { url: CODEX_PAGE_ROUTES['sin-news'],          terms: ['sin', 's.i.n.', 'sin news', 'spokane information network'] },
    { url: CODEX_PAGE_ROUTES['ibj-financial'],     terms: ['ibj', 'i.b.j.', 'inland business journal', 'ibj financial'] },
    { url: CODEX_PAGE_ROUTES['valley-voice-news'], terms: ['valley voice', 'the valley voice'] },
    { url: CODEX_PAGE_ROUTES['wire-dead-leak'],    terms: ['wire dead', 'wire-dead', 'wiredead'] },
];

function normalizeSearchTerm(s) {
    return s.toLowerCase().replace(/[.\-_]/g, ' ').replace(/\s+/g, ' ').trim();
}

/**
 * Resolve a free-typed search query (e.g. "avista", "the valley voice") to
 * the matching flavor page's splice:// URL, or null if nothing matches.
 * Tries an exact normalized match first, then falls back to a substring
 * match against the longest (most specific) matching term.
 */
export function findCompanyByQuery(query) {
    const q = normalizeSearchTerm(query || '');
    if (!q) return null;

    for (const entry of SEARCH_INDEX) {
        if (entry.terms.some((t) => normalizeSearchTerm(t) === q)) return entry.url;
    }

    let bestUrl = null;
    let bestLen = -1;
    for (const entry of SEARCH_INDEX) {
        for (const t of entry.terms) {
            const nt = normalizeSearchTerm(t);
            if ((nt.includes(q) || q.includes(nt)) && nt.length > bestLen) {
                bestUrl = entry.url;
                bestLen = nt.length;
            }
        }
    }
    return bestUrl;
}
