// ─── SpliceRouter.js ──────────────────────────────────────────────────────────
//
//  Maps SPLICE URLs → Vue page components.
//
//  TO ADD A NEW PAGE:
//    1. Create your component in ./pages/
//    2. Import it here
//    3. Add one entry to ROUTES
//  That's it. Nothing else in the codebase needs to change.
//
// ─────────────────────────────────────────────────────────────────────────────

import HomePage      from './pages/HomePage.vue';
import CityFeed      from './pages/CityFeed.vue';
import HowToPlay     from './pages/HowToPlay.vue';
import CyberDocStore from './pages/CyberDocStore.vue';
import SysStats      from './pages/SysStats.vue';
import SysRig        from './pages/SysRig.vue';
import SysCommands       from './pages/SysCommands.vue';
import SysCommandCatalog from './pages/SysCommandCatalog.vue';
import SysStatGuide      from './pages/SysStatGuide.vue';
import SysInventory      from './pages/SysInventory.vue';
import GridBreachGuide   from './pages/GridBreachGuide.vue';
import GhostProtocol0   from './pages/GhostProtocol0.vue';
import NotFound      from './pages/NotFound.vue';

// ── Route table ───────────────────────────────────────────────────────────────
// To add a new page: import its component and add one entry here. That's it.
const ROUTES = [
    // ── Public / network pages ────────────────────────────────────────────────
    { url: 'splice://home',              title: 'New Tab',          component: HomePage      },
    { url: 'splice://darknet.spk/feed',  title: 'DarkNet // Feed',  component: CityFeed      },
    { url: 'splice://sys.local/manual',  title: 'SYS Manual',       component: HowToPlay     },
    { url: 'splice://cyberdoc.net/shop', title: 'CyberDoc // Shop', component: CyberDocStore },

    // ── Runner sys apps ───────────────────────────────────────────────────────
    { url: 'splice://sys.local/stats',     title: 'Runner Status', component: SysStats     },
    { url: 'splice://sys.local/rig',       title: 'Rig Info',     component: SysRig       },
    { url: 'splice://sys.local/commands/catalog', title: 'Command Catalog', component: SysCommandCatalog },
    { url: 'splice://sys.local/guide/stats',      title: 'Stat Reference',  component: SysStatGuide      },
    { url: 'splice://sys.local/guide/gridbreach', title: 'Grid-Breach Manual', component: GridBreachGuide },
    { url: 'splice://sys.local/tutorial',         title: 'GHOST_PROTOCOL_0',   component: GhostProtocol0  },
    { url: 'splice://sys.local/commands',         title: 'Commands',        component: SysCommands       },
    { url: 'splice://sys.local/inventory', title: 'Inventory',    component: SysInventory },
];

export function resolveRoute(url) {
    return ROUTES.find(r => url.startsWith(r.url))?.component ?? NotFound;
}

export function getPageTitle(url) {
    return ROUTES.find(r => url.startsWith(r.url))?.title ?? 'Unknown';
}

// ── Named URLs ────────────────────────────────────────────────────────────────
// Import SPLICE wherever you need to navigate programmatically.
// Keeps all URL strings in one place — no magic strings scattered around.
export const SPLICE = {
    // Network
    HOME:      'splice://home',
    FEED:      'splice://darknet.spk/feed',
    MANUAL:    'splice://sys.local/manual',
    CYBER_DOC: 'splice://cyberdoc.net/shop',

    // Runner sys apps
    STATS:     'splice://sys.local/stats',
    RIG:       'splice://sys.local/rig',
    COMMANDS:         'splice://sys.local/commands',
    COMMAND_CATALOG:  'splice://sys.local/commands/catalog',
    STAT_GUIDE:       'splice://sys.local/guide/stats',
    GRID_BREACH_GUIDE: 'splice://sys.local/guide/gridbreach',
    TUTORIAL:          'splice://sys.local/tutorial',
    INVENTORY: 'splice://sys.local/inventory',
};
