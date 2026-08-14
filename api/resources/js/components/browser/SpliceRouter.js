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
import CyberDocStore   from './pages/CyberDocStore.vue';
import CyberDocPatch   from './pages/CyberDocPatch.vue';
import CyberDocKnuckle from './pages/CyberDocKnuckle.vue';
import CyberDocVeil    from './pages/CyberDocVeil.vue';
import CyberDocAxiom   from './pages/CyberDocAxiom.vue';
import CyberDocFloat   from './pages/CyberDocFloat.vue';
import SysStats      from './pages/SysStats.vue';
import SysRig        from './pages/SysRig.vue';
import SysCommands       from './pages/SysCommands.vue';
import SysCommandCatalog from './pages/SysCommandCatalog.vue';
import SysStatGuide      from './pages/SysStatGuide.vue';
import SysInventory      from './pages/SysInventory.vue';
import GridBreachGuide   from './pages/GridBreachGuide.vue';
import PacketHijackGuide from './pages/PacketHijackGuide.vue';
import DataGrabGuide     from './pages/DataGrabGuide.vue';
import FlushBufferGuide  from './pages/FlushBufferGuide.vue';
import CipherLockGuide   from './pages/CipherLockGuide.vue';
import GhostProtocol0   from './pages/GhostProtocol0.vue';
import QuestLog      from './pages/QuestLog.vue';
import PersonasPage    from './pages/PersonasPage.vue';
import WatcherChannel  from './pages/WatcherChannel.vue';
import ArchivePage     from './pages/ArchivePage.vue';
import BankPage      from './pages/BankPage.vue';
import SystemUpdate    from './pages/SystemUpdate.vue';
import DocDialoguePage from './pages/DocDialoguePage.vue';
import Decrypter        from './pages/Decrypter.vue';
import DevMinigameLauncher from './pages/DevMinigameLauncher.vue';
import NotFound        from './pages/NotFound.vue';

// ── Route table ───────────────────────────────────────────────────────────────
// To add a new page: import its component and add one entry here. That's it.
const ROUTES = [
    // ── Public / network pages ────────────────────────────────────────────────
    { url: 'splice://home',              title: 'New Tab',          component: HomePage      },
    { url: 'splice://darknet.spk/feed',  title: 'DarkNet // Feed',  component: CityFeed      },
    { url: 'splice://sys.local/manual',  title: 'SYS Manual',       component: HowToPlay     },
    { url: 'splice://cyberdoc.net/shop',    title: 'CyberDoc // Shop',         component: CyberDocStore   },
    { url: 'splice://cyberdoc.ns/patch',   title: "Patch's Clinic",           component: CyberDocPatch   },
    { url: 'splice://cyberdoc.ba/knuckle', title: "Knuckle's Med-Wagon",      component: CyberDocKnuckle },
    { url: 'splice://cyberdoc.dt/veil',    title: "Veil's Parlour",           component: CyberDocVeil    },
    { url: 'splice://cyberdoc.ud/axiom',   title: 'Axiom Systems',            component: CyberDocAxiom   },
    { url: 'splice://cyberdoc.sv/float',   title: "Float's Repair Bay",       component: CyberDocFloat   },

    // ── Runner sys apps ───────────────────────────────────────────────────────
    { url: 'splice://sys.local/stats',     title: 'Runner Status', component: SysStats     },
    { url: 'splice://sys.local/rig',       title: 'Rig Info',     component: SysRig       },
    { url: 'splice://sys.local/commands/catalog', title: 'Command Catalog', component: SysCommandCatalog },
    { url: 'splice://sys.local/guide/stats',      title: 'Stat Reference',  component: SysStatGuide      },
    { url: 'splice://sys.local/guide/gridbreach',   title: 'Grid-Breach Manual',     component: GridBreachGuide   },
    { url: 'splice://sys.local/guide/packethijack', title: 'Packet Hijack Manual',  component: PacketHijackGuide },
    { url: 'splice://sys.local/guide/datagrab',     title: 'Data_Grab Manual',      component: DataGrabGuide     },
    { url: 'splice://sys.local/guide/flushbuffer',  title: 'Flush_Buffer Manual',   component: FlushBufferGuide  },
    { url: 'splice://sys.local/guide/cipherlock',   title: 'Cipher_Lock Manual',    component: CipherLockGuide   },
    { url: 'splice://sys.local/tutorial',         title: 'GHOST_PROTOCOL_0',   component: GhostProtocol0  },
    { url: 'splice://sys.local/terminal',         title: 'Mission Log',        component: QuestLog        },
    { url: 'splice://sys.local/archive',          title: 'Mission Archive',    component: ArchivePage     },
    { url: 'splice://sys.local/codex',            title: 'Codex Archive',      component: Decrypter      },
    { url: 'splice://sys.local/personas',         title: 'Runner Personas',    component: PersonasPage    },
    // Hidden — not linked anywhere. Discoverable only via the Watcher glitch sequence.
    { url: 'splice://watcher',                   title: '[ENCRYPTED_CHANNEL]', component: WatcherChannel  },
    { url: 'splice://bank.ch/hct',               title: 'Helvetic Cipher Trust', component: BankPage       },
    { url: 'splice://sys.tacat/cortex-patch',    title: 'CORTEX PATCH // FORCED', component: SystemUpdate   },
    { url: 'splice://sys.local/commands',         title: 'Commands',        component: SysCommands       },
    { url: 'splice://sys.local/inventory', title: 'Inventory',    component: SysInventory },

    // ── Dev tools — remove before release ────────────────────────────────────
    { url: 'splice://dev/minigames', title: 'DEV // Minigame Launcher', component: DevMinigameLauncher },

    // ── Doc dialogue pages ────────────────────────────────────────────────────
    { url: 'splice://dialogue/knuckle', title: 'KNUCKLE // TRANSMISSION', component: DocDialoguePage },
    { url: 'splice://dialogue/patch',   title: 'PATCH // TRANSMISSION',   component: DocDialoguePage },
    { url: 'splice://dialogue/veil',    title: 'VEIL // TRANSMISSION',    component: DocDialoguePage },
    { url: 'splice://dialogue/axiom',   title: 'AXIOM // TRANSMISSION',   component: DocDialoguePage },
    { url: 'splice://dialogue/float',   title: 'FLOAT // TRANSMISSION',   component: DocDialoguePage },
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
    CYBER_DOC:        'splice://cyberdoc.net/shop',   // generic fallback
    CYBER_DOC_PATCH:   'splice://cyberdoc.ns/patch',
    CYBER_DOC_KNUCKLE: 'splice://cyberdoc.ba/knuckle',
    CYBER_DOC_VEIL:    'splice://cyberdoc.dt/veil',
    CYBER_DOC_AXIOM:   'splice://cyberdoc.ud/axiom',
    CYBER_DOC_FLOAT:   'splice://cyberdoc.sv/float',

    // Runner sys apps
    STATS:     'splice://sys.local/stats',
    RIG:       'splice://sys.local/rig',
    COMMANDS:         'splice://sys.local/commands',
    COMMAND_CATALOG:  'splice://sys.local/commands/catalog',
    STAT_GUIDE:       'splice://sys.local/guide/stats',
    GRID_BREACH_GUIDE:   'splice://sys.local/guide/gridbreach',
    PACKET_HIJACK_GUIDE: 'splice://sys.local/guide/packethijack',
    DATA_GRAB_GUIDE:     'splice://sys.local/guide/datagrab',
    FLUSH_BUFFER_GUIDE:  'splice://sys.local/guide/flushbuffer',
    CIPHER_LOCK_GUIDE:   'splice://sys.local/guide/cipherlock',
    TUTORIAL:          'splice://sys.local/tutorial',
    TERMINAL:          'splice://sys.local/terminal',   // NavBar TERMINAL app
    DEV_MINIGAMES:     'splice://dev/minigames',        // DEV ONLY — remove before release— Mission Log / Quest Log
    ARCHIVE:           'splice://sys.local/archive',   // Chronological story archive
    CODEX:             'splice://sys.local/codex',     // Codex Archive — key resolution & history
    PERSONAS:          'splice://sys.local/personas',  // Runner Personas reference
    INVENTORY:    'splice://sys.local/inventory',
    BANK:         'splice://bank.ch/hct',
    CORTEX_PATCH: 'splice://sys.tacat/cortex-patch',   // forced install — story-triggered only

    // Doc dialogue pages — opened via [ OPEN DIALOGUE ] button in NodeInfoBlock
    DIALOGUE_KNUCKLE: 'splice://dialogue/knuckle',
    DIALOGUE_PATCH:   'splice://dialogue/patch',
    DIALOGUE_VEIL:    'splice://dialogue/veil',
    DIALOGUE_AXIOM:   'splice://dialogue/axiom',
    DIALOGUE_FLOAT:   'splice://dialogue/float',
};
