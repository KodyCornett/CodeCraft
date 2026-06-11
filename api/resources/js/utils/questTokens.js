/**
 * resolveQuestTokens
 *
 * Replaces persona tokens in quest stage text with the player's selected values.
 *
 * Supported tokens:
 *   {persona}      → player's persona name        e.g. "Ghost"
 *   {persona_desc} → player's persona descriptor  e.g. "Leaves no trace..."
 *
 * Usage:
 *   import { resolveQuestTokens } from '@/utils/questTokens.js';
 *   const text = resolveQuestTokens(stage.objective_text, player);
 *
 * @param {string|null} text   Raw quest text containing tokens
 * @param {object}      player Player object with .persona and .persona_desc fields
 * @returns {string}
 */
export function resolveQuestTokens(text, player) {
    if (!text) return '';
    return text
        .replace(/\{persona\}/g,      player?.persona      ?? '')
        .replace(/\{persona_desc\}/g, player?.persona_desc ?? '');
}
