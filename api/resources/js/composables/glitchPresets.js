/**
 * glitchPresets.js
 *
 * Named, multi-stage FX combos built on top of GlitchEffect.vue's base
 * effect types (chromatic, scan, bars, static, dissolve, flicker, scramble).
 * Each preset is an ordered list of stages — { type, duration } — played
 * back to back by GlitchFx.vue. `type` uses the same comma-separated,
 * per-effect intensity syntax as GlitchEffect's own `type` prop
 * (e.g. 'chromatic(2),flicker(1)').
 *
 * Reference a preset from a dialogue entry as `fx: { preset: 'sneeze' }`.
 * Add new presets here — nothing else needs changing.
 */
export const GLITCH_PRESETS = {
    // Digital sneeze — windup (small involuntary stutter), violent release
    // (full tear, as if the vocal synth barked out a garbled half-word),
    // sharp settle (snaps back clean, no gradual fade). Ambient-tier only —
    // this is ordinary body malfunction, not the Watcher reacting to
    // something. See the two-vocabulary rule in WORLD_PHILOSOPHY.md.
    sneeze: [
        { type: 'chromatic(1)',             duration: 150 }, // windup
        { type: 'chromatic(4),scramble(3)', duration: 220 }, // release
        { type: 'flicker(1)',               duration: 120 }, // settle
    ],
};
