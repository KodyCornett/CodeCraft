/**
 * bankPageRoutes
 *
 * Name-based search index for the 19 Bank Heist target SPLICE pages (see
 * BANK_TARGET_ROSTER.md at the repo root and constants/bankTargetConfig.js
 * for the content those pages render). Lets a player type a bank's actual
 * name into the SPLICE address bar / New Tab search (e.g. "apex", "black
 * tide") instead of needing to already know its exact domain — the same
 * discoverability the 15 Codex-thread companies get from
 * codexPageRoutes.js's SEARCH_INDEX/findCompanyByQuery.
 *
 * Deliberately a separate, standalone file rather than an addition to
 * codexPageRoutes.js — that file is the Codex/ArchiveExtraction system's
 * slug -> URL map and is kept untouched. This file has no relationship to
 * splice_pages/CodexService; every Bank Heist target page here is static
 * flavor content with no backend-driven unlock gating.
 */
const SEARCH_INDEX = [
    { url: 'splice://firstmetro-fcu.org',         terms: ['first metro', 'firstmetro', 'first metro federal union', 'fcu'] },
    { url: 'splice://solis-lending.io',           terms: ['solis', 'solis lending', 'solis micro-lending', 'solis micro lending'] },
    { url: 'splice://vantagepoint-trade.com',     terms: ['vantage point', 'vantagepoint', 'vantage point securities'] },
    { url: 'splice://aether-neobank.io',          terms: ['aether', 'aether neobank'] },
    { url: 'splice://bluesky-funds.com',          terms: ['bluesky', 'blue sky', 'bluesky index funds', 'blue sky funds'] },
    { url: 'splice://hyperion-vc.io',             terms: ['hyperion', 'hyperion vc', 'hyperion venture capital'] },
    { url: 'splice://pensiondirect.gov',          terms: ['pension direct', 'pensiondirect', 'pension direct assurance'] },
    { url: 'splice://ironclad-trust.com',         terms: ['ironclad', 'ironclad trust', 'ironclad vault', 'ironclad vault and trust'] },
    { url: 'splice://aegis-wealth.com',           terms: ['aegis', 'aegis wealth', 'aegis wealth management'] },
    { url: 'splice://kurogane-fleet.co',          terms: ['kurogane', 'kurogane fleet', 'kurogane fleet bank'] },
    { url: 'splice://zenjin-assets.io',           terms: ['zenjin', 'zenjin assets', 'zenjin asset management'] },
    { url: 'splice://horizon-mutual.com',         terms: ['horizon mutual', 'horizon mutual insurance'] },
    { url: 'splice://apex-capital.com',           terms: ['apex', 'apex capital', 'apex capital partners'] },
    { url: 'splice://chronos-quant.io',           terms: ['chronos', 'chronos quant', 'chronos quantitative management'] },
    { url: 'splice://horizon-sovereign.offshore', terms: ['horizon sovereign', 'horizon sovereign holdings'] },
    { url: 'splice://veritas-custody.io',         terms: ['veritas', 'veritas custody', 'veritas crypto-custody', 'veritas crypto custody'] },
    { url: 'splice://nova-exchange.com',          terms: ['nova', 'nova exchange'] },
    { url: 'splice://starlight-sovereign.gov',    terms: ['starlight', 'starlight sovereign', 'starlight sovereign wealth'] },
    { url: 'splice://blacktide.onion',            terms: ['black tide', 'blacktide', 'black-tide', 'black tide liquidity'] },
];

function normalizeSearchTerm(s) {
    return s.toLowerCase().replace(/[.\-_]/g, ' ').replace(/\s+/g, ' ').trim();
}

/**
 * Resolve a free-typed search query (e.g. "apex", "black tide") to the
 * matching Bank Heist target's splice:// URL, or null if nothing matches.
 * Tries an exact normalized match first, then falls back to a substring
 * match against the longest (most specific) matching term. Mirrors
 * codexPageRoutes.js's findCompanyByQuery() behavior exactly, just against
 * this separate 19-entry index.
 */
export function findBankByQuery(query) {
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
