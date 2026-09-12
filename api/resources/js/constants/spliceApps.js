import { SPLICE } from '@/components/browser/SpliceRouter.js';

/**
 * SPLICE_APPS
 *
 * Single source of truth for the "pinned program" list — the taskbar
 * (NavBar.vue) and the desktop (Desktop.vue) both render icons from this
 * same array instead of keeping two hand-duplicated lists in sync.
 *
 * Was previously defined inline inside NavBar.vue only.
 */
export const SPLICE_APPS = [
    { url: SPLICE.STATS,     icon: '◈', label: 'STATUS',   tourId: 'nav-status'   },
    { url: SPLICE.RIG,       icon: '⬡', label: 'RIG'                              },
    { url: SPLICE.COMMANDS,  icon: '▶', label: 'CMDS'                             },
    { url: SPLICE.INVENTORY, icon: '▣', label: 'INV'                              },
    { url: SPLICE.MAPS,      icon: '⛯', label: 'MAPS'                              },
    { url: SPLICE.TERMINAL,  icon: '⌨', label: 'TERMINAL', badged: true, tourId: 'nav-terminal' },
];

/**
 * PROGRAMS
 *
 * Everything a player can launch from the desktop or the Start Menu — the
 * one true program (Network Map — opens as its own window, not a SPLICE
 * page) plus SPLICE home plus the same SPLICE_APPS list above. Desktop.vue
 * and StartMenu.vue both render from this single list instead of each
 * hand-assembling "map + home + apps" separately.
 *
 * kind: 'window' → not a SPLICE page; caller emits 'open-map' (only entry
 *       of this kind today, but kept generic rather than special-cased).
 * kind: 'launch' → a SPLICE page; caller emits 'launch' with `url`.
 */
export const PROGRAMS = [
    { id: 'map', kind: 'window', icon: '⬢', label: 'NETWORK MAP' },
    { id: 'browser', kind: 'launch', icon: '◈', label: 'SPLICE', url: SPLICE.HOME },
    ...SPLICE_APPS.map(app => ({
        id: app.url, kind: 'launch', icon: app.icon, label: app.label, url: app.url,
    })),
];
