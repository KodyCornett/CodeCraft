/**
 * resolveQuestTokens
 *
 * Replaces player identity tokens in quest stage text.
 *
 * Supported tokens:
 *   {handle}       → player's runner handle        e.g. "NullByte"
 *   {persona}      → player's persona name         e.g. "Ghost"
 *   {persona_desc} → player's persona descriptor   e.g. "Leaves no trace..."
 *
 * Usage:
 *   import { resolveQuestTokens } from '@/utils/questTokens.js';
 *   const text = resolveQuestTokens(stage.objective_text, player);
 *
 * @param {string|null} text   Raw quest text containing tokens
 * @param {object}      player Player object with .handle, .persona, .persona_desc
 * @returns {string}
 */
export function resolveQuestTokens(text, player) {
    if (!text) return '';
    return text
        .replace(/\{handle\}/g,       player?.handle       ?? '')
        .replace(/\{persona\}/g,      player?.persona      ?? '')
        .replace(/\{persona_desc\}/g, player?.persona_desc ?? '');
}
