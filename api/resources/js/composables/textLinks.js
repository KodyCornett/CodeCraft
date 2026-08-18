/**
 * textLinks
 *
 * Turns plain prose written in the 15 flavor pages' About/News copy into
 * clickable in-text links whenever it mentions another company by name —
 * e.g. "...supplied by I.T.R.O.N." on the A.V.I.S.T.A. page becomes a link
 * to itron-telemetry.io. Purely cosmetic/navigational: no backend changes,
 * no new content, just making already-written text interactive.
 *
 * Deliberately separate from codexPageRoutes.js's SEARCH_INDEX (which
 * matches loosely-normalized free-typed search queries). This list matches
 * literal phrases exactly as they're written in the page copy, so matching
 * stays precise with zero false positives — no fuzzy normalization needed.
 *
 * Consumed by LinkifiedText.vue, the single shared renderer every flavor
 * page uses instead of duplicating this logic 15 times.
 */
import { CODEX_PAGE_ROUTES } from './codexPageRoutes.js';

// Longest-phrase-first so e.g. "Providence Healthcare" matches before the
// shorter "Providence" would otherwise split it.
const LINK_TERMS = [
    ['A.V.I.S.T.A.', CODEX_PAGE_ROUTES['avista-grid']],
    ['Providence Healthcare', CODEX_PAGE_ROUTES['providence-health']],
    ['P.R.O.V.I.D.E.N.C.E.', CODEX_PAGE_ROUTES['providence-health']],
    ['Providence', CODEX_PAGE_ROUTES['providence-health']],
    ['I.T.R.O.N.', CODEX_PAGE_ROUTES['itron-telemetry']],
    ['W.W.P.', CODEX_PAGE_ROUTES['wwp-archive']],
    ['G.O.N.Z.A.G.A.', CODEX_PAGE_ROUTES['gonzaga-whitepaper']],
    ['Gonzaga', CODEX_PAGE_ROUTES['gonzaga-whitepaper']],
    ['S.T.A.', CODEX_PAGE_ROUTES['sta-transit']],
    ['C.O.P.P.E.R.H.E.A.D.', CODEX_PAGE_ROUTES['copperhead-parts']],
    ['Inland Commercial Properties & Asset Management', CODEX_PAGE_ROUTES['inland-leasing']],
    ['Inland Logistics Network', CODEX_PAGE_ROUTES['inland-leasing']],
    ['S.T.I.T.C.H.E.R.S.', CODEX_PAGE_ROUTES['stitchers-market']],
    ['N.U.L.L.', CODEX_PAGE_ROUTES['null-forum']],
    ['S.P.E.C.T.R.E.', CODEX_PAGE_ROUTES['spectre-manifesto']],
    ['S.I.N.', CODEX_PAGE_ROUTES['sin-news']],
    ['Inland Business Journal', CODEX_PAGE_ROUTES['ibj-financial']],
    ['I.B.J.', CODEX_PAGE_ROUTES['ibj-financial']],
    ['The Valley Voice', CODEX_PAGE_ROUTES['valley-voice-news']],
    ['Valley Voice', CODEX_PAGE_ROUTES['valley-voice-news']],
    ['WIRE-DEAD', CODEX_PAGE_ROUTES['wire-dead-leak']],
    ['avista-grid.com', CODEX_PAGE_ROUTES['avista-grid']],
    ['ITRON', CODEX_PAGE_ROUTES['itron-telemetry']],
].sort((a, b) => b[0].length - a[0].length);

/**
 * Split a plain-text string into an array of segments:
 *   { text: '...', url: null }        — plain text, render as-is
 *   { text: 'A.V.I.S.T.A.', url: '…' } — a recognized company mention
 *
 * Case-sensitive, non-overlapping, longest-match-first. Safe to call on
 * text that contains no company mentions — returns a single plain segment.
 */
export function linkifySegments(text) {
    if (!text) return [];

    const segments = [];
    let cursor = 0;

    while (cursor < text.length) {
        let bestIndex = -1;
        let bestTerm  = null;
        let bestUrl   = null;

        for (const [term, url] of LINK_TERMS) {
            const idx = text.indexOf(term, cursor);
            if (idx === -1) continue;
            if (bestIndex === -1 || idx < bestIndex) {
                bestIndex = idx;
                bestTerm  = term;
                bestUrl   = url;
            }
        }

        if (bestIndex === -1) {
            segments.push({ text: text.slice(cursor), url: null });
            break;
        }

        if (bestIndex > cursor) {
            segments.push({ text: text.slice(cursor, bestIndex), url: null });
        }
        segments.push({ text: bestTerm, url: bestUrl });
        cursor = bestIndex + bestTerm.length;
    }

    return segments;
}
