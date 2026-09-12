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
