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
import ArchivePage     from './pages/ArchivePage.vue';
import BankPage      from './pages/BankPage.vue';
import SystemUpdate    from './pages/SystemUpdate.vue';
import DocDialoguePage from './pages/DocDialoguePage.vue';
import Decrypter        from './pages/Decrypter.vue';
import DevMinigameLauncher from './pages/DevMinigameLauncher.vue';
import DevGeneratorLab from './pages/DevGeneratorLab.vue';
import DevSITLab       from './pages/DevSITLab.vue';
import DevSceneLauncher    from './pages/DevSceneLauncher.vue';
import SpliceMapsPage   from './pages/SpliceMapsPage.vue';
import NotFound        from './pages/NotFound.vue';

// ── Codex system — 15 public flavor pages + 5 restricted codex terminals ──────
// See resources/js/composables/codexPageRoutes.js for the slug -> URL map
// these routes must stay in sync with.
import AvistaGridPage           from './pages/AvistaGridPage.vue';
import ProvidenceHealthPage     from './pages/ProvidenceHealthPage.vue';
import ItronTelemetryPage       from './pages/ItronTelemetryPage.vue';
import WwpArchivePage           from './pages/WwpArchivePage.vue';
import GonzagaResearchPage      from './pages/GonzagaResearchPage.vue';
import StaTransitPage           from './pages/StaTransitPage.vue';
import CopperheadPartsPage      from './pages/CopperheadPartsPage.vue';
import InlandLeasingPage        from './pages/InlandLeasingPage.vue';
import StitchersMarketPage      from './pages/StitchersMarketPage.vue';
import NullForumPage            from './pages/NullForumPage.vue';
import SpectreManifestoPage     from './pages/SpectreManifestoPage.vue';
import SinNewsPage              from './pages/SinNewsPage.vue';
import IbjFinancialPage         from './pages/IbjFinancialPage.vue';
import ValleyVoiceNewsPage      from './pages/ValleyVoiceNewsPage.vue';
import WireDeadLeakPage         from './pages/WireDeadLeakPage.vue';
import AvistaSub09Terminal      from './pages/AvistaSub09Terminal.vue';
import GonzagaLab404Terminal    from './pages/GonzagaLab404Terminal.vue';
import StaVault04Terminal       from './pages/StaVault04Terminal.vue';
import CopperheadFreightTerminal from './pages/CopperheadFreightTerminal.vue';
import NullIrcRelayTerminal     from './pages/NullIrcRelayTerminal.vue';

// ── Bank Heist target roster — one shared component, many routes ─────────────
// See BANK_TARGET_ROSTER.md at the repo root and constants/bankTargetConfig.js
// for the per-bank content this component renders. Cosmetic/flavor pages only
// — the hackable-node backend (BankHeistService) lands separately once the
// mechanic's numbers pass is locked (see BANK_HEIST_BUILD_PLAN.md).
import BankTargetPage           from './pages/BankTargetPage.vue';

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
    { url: 'splice://bank.ch/hct',               title: 'Helvetic Cipher Trust', component: BankPage       },
    { url: 'splice://sys.tacat/cortex-patch',    title: 'CORTEX PATCH // FORCED', component: SystemUpdate   },
    { url: 'splice://sys.local/commands',         title: 'Commands',        component: SysCommands       },
    { url: 'splice://sys.local/inventory', title: 'Inventory',    component: SysInventory },
    { url: 'splice://maps.spk',            title: 'SPLICE // Maps', component: SpliceMapsPage },

    // ── Dev tools — remove before release ────────────────────────────────────
    { url: 'splice://dev/minigames', title: 'DEV // Minigame Launcher', component: DevMinigameLauncher },
    { url: 'splice://dev/generator-lab', title: 'DEV // Generator Lab', component: DevGeneratorLab },
    { url: 'splice://dev/sit-lab', title: 'DEV // SIT Lab', component: DevSITLab },
    { url: 'splice://dev/scenes',    title: 'DEV // Scene Splicer',     component: DevSceneLauncher    },

    // ── Doc dialogue pages ────────────────────────────────────────────────────
    { url: 'splice://dialogue/knuckle', title: 'KNUCKLE // TRANSMISSION', component: DocDialoguePage },
    { url: 'splice://dialogue/patch',   title: 'PATCH // TRANSMISSION',   component: DocDialoguePage },
    { url: 'splice://dialogue/veil',    title: 'VEIL // TRANSMISSION',    component: DocDialoguePage },
    { url: 'splice://dialogue/axiom',   title: 'AXIOM // TRANSMISSION',   component: DocDialoguePage },
    { url: 'splice://dialogue/float',   title: 'FLOAT // TRANSMISSION',   component: DocDialoguePage },

    // ── Codex system — restricted terminals (must precede their parent domain
    //    entries below, since resolveRoute matches on startsWith) ─────────────
    { url: 'splice://avista-grid.com/sub09-terminal',        title: 'RESTRICTED // A.V.I.S.T.A. SUB-09',    component: AvistaSub09Terminal      },
    { url: 'splice://gonzaga-research.edu/lab404-terminal',  title: 'RESTRICTED // GONZAGA LAB 404',        component: GonzagaLab404Terminal    },
    { url: 'splice://sta-transit.net/vault04-terminal',      title: 'RESTRICTED // S.T.A. VAULT 04',        component: StaVault04Terminal       },
    { url: 'splice://copperhead-chassis.net/freight-terminal', title: 'RESTRICTED // COPPERHEAD FREIGHT',   component: CopperheadFreightTerminal },
    { url: 'splice://null-front.board/irc-relay',            title: 'RESTRICTED // N.U.L.L. IRC RELAY',     component: NullIrcRelayTerminal     },

    // ── Codex system — public flavor pages (the SPLICE "open web") ────────────
    { url: 'splice://avista-grid.com',        title: 'A.V.I.S.T.A. // Grid Portal',        component: AvistaGridPage       },
    { url: 'splice://providence-health.med',  title: 'P.R.O.V.I.D.E.N.C.E. // Patient Portal', component: ProvidenceHealthPage },
    { url: 'splice://itron-telemetry.io',     title: 'I.T.R.O.N. // Telemetry',            component: ItronTelemetryPage   },
    { url: 'splice://wwp-archives.org',       title: 'W.W.P. // Historical Archive',       component: WwpArchivePage       },
    { url: 'splice://gonzaga-research.edu',   title: 'G.O.N.Z.A.G.A. // Research Network', component: GonzagaResearchPage  },
    { url: 'splice://sta-transit.net',        title: 'S.T.A. // Transit Terminal',         component: StaTransitPage       },
    { url: 'splice://copperhead-chassis.net', title: 'C.O.P.P.E.R.H.E.A.D. // Chassis Shop', component: CopperheadPartsPage },
    { url: 'splice://inland-properties.biz',  title: 'Inland Commercial Properties',       component: InlandLeasingPage    },
    { url: 'splice://stitchers-market.onion', title: 'S.T.I.T.C.H.E.R.S. // Market',       component: StitchersMarketPage  },
    { url: 'splice://null-front.board',       title: 'N.U.L.L. // Underground BBS',        component: NullForumPage        },
    { url: 'splice://spectre-defense.militia', title: 'S.P.E.C.T.R.E. // Enclave',         component: SpectreManifestoPage },
    { url: 'splice://sin-news.com',           title: 'S.I.N. // Spokane Information Network', component: SinNewsPage       },
    { url: 'splice://inland-biz.io',          title: 'I.B.J. // Inland Business Journal',  component: IbjFinancialPage     },
    { url: 'splice://valley-voice.org',       title: 'The Valley Voice',                   component: ValleyVoiceNewsPage  },
    { url: 'splice://wire-dead.net',          title: 'WIRE-DEAD // Leak Feed',             component: WireDeadLeakPage     },

    // ── Bank Heist target roster — public flavor pages, one component ─────────
    // Tier 1 — Retail & Community
    { url: 'splice://firstmetro-fcu.org',        title: 'First Metro Federal Union',       component: BankTargetPage },
    { url: 'splice://solis-lending.io',          title: 'Solis Micro-Lending',             component: BankTargetPage },
    { url: 'splice://vantagepoint-trade.com',    title: 'Vantage Point Securities',        component: BankTargetPage },
    // Tier 2 — Neo-Tech & Fast-Yield
    { url: 'splice://aether-neobank.io',         title: 'Aether Neobank',                  component: BankTargetPage },
    { url: 'splice://bluesky-funds.com',         title: 'BlueSky Index Funds',             component: BankTargetPage },
    { url: 'splice://hyperion-vc.io',            title: 'Hyperion Venture Capital',        component: BankTargetPage },
    { url: 'splice://pensiondirect.gov',         title: 'Pension Direct Assurance',        component: BankTargetPage },
    // Tier 3 — Institutional & High-Net-Worth
    { url: 'splice://ironclad-trust.com',        title: 'Ironclad Vault & Trust',          component: BankTargetPage },
    { url: 'splice://aegis-wealth.com',          title: 'Aegis Wealth Management',         component: BankTargetPage },
    { url: 'splice://kurogane-fleet.co',         title: 'Kurogane Fleet Bank',             component: BankTargetPage },
    { url: 'splice://zenjin-assets.io',          title: 'Zenjin Asset Management',         component: BankTargetPage },
    { url: 'splice://horizon-mutual.com',        title: 'Horizon Mutual Insurance',        component: BankTargetPage },
    // Tier 4 — High-Risk Apex & Specialized
    { url: 'splice://apex-capital.com',          title: 'Apex Capital Partners',           component: BankTargetPage },
    { url: 'splice://chronos-quant.io',          title: 'Chronos Quantitative Management', component: BankTargetPage },
    { url: 'splice://horizon-sovereign.offshore', title: 'Horizon Sovereign Holdings',     component: BankTargetPage },
    { url: 'splice://veritas-custody.io',        title: 'Veritas Crypto-Custody',          component: BankTargetPage },
    { url: 'splice://nova-exchange.com',         title: 'Nova Exchange',                   component: BankTargetPage },
    { url: 'splice://starlight-sovereign.gov',   title: 'Starlight Sovereign Wealth',      component: BankTargetPage },
    { url: 'splice://blacktide.onion',           title: 'Black-Tide Liquidity',            component: BankTargetPage },
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
    DEV_GENERATOR_LAB: 'splice://dev/generator-lab',    // DEV ONLY — remove before release — composer input/rule test lab
    DEV_SIT_LAB:       'splice://dev/sit-lab',          // DEV ONLY — remove before release — SIT (Splice Interface Terminal) test lab
    DEV_SCENES:        'splice://dev/scenes',           // DEV ONLY — remove before release
    ARCHIVE:           'splice://sys.local/archive',   // Chronological story archive
    CODEX:             'splice://sys.local/codex',     // Codex Archive — key resolution & history
    PERSONAS:          'splice://sys.local/personas',  // Runner Personas reference
    INVENTORY:    'splice://sys.local/inventory',
    MAPS:         'splice://maps.spk',
    BANK:         'splice://bank.ch/hct',
    CORTEX_PATCH: 'splice://sys.tacat/cortex-patch',   // forced install — story-triggered only

    // Doc dialogue pages — opened via [ OPEN DIALOGUE ] button in NodeInfoBlock
    DIALOGUE_KNUCKLE: 'splice://dialogue/knuckle',
    DIALOGUE_PATCH:   'splice://dialogue/patch',
    DIALOGUE_VEIL:    'splice://dialogue/veil',
    DIALOGUE_AXIOM:   'splice://dialogue/axiom',
    DIALOGUE_FLOAT:   'splice://dialogue/float',

    // Codex system — public flavor pages. Prefer codexPageRoutes.js's
    // routeForSlug() over these when navigating from a slug (e.g. a lead
    // link) — these named constants are for direct/hardcoded links only.
    AVISTA_GRID:        'splice://avista-grid.com',
    PROVIDENCE_HEALTH:   'splice://providence-health.med',
    ITRON_TELEMETRY:     'splice://itron-telemetry.io',
    WWP_ARCHIVE:         'splice://wwp-archives.org',
    GONZAGA_RESEARCH:    'splice://gonzaga-research.edu',
    STA_TRANSIT:         'splice://sta-transit.net',
    COPPERHEAD_CHASSIS:  'splice://copperhead-chassis.net',
    INLAND_PROPERTIES:   'splice://inland-properties.biz',
    STITCHERS_MARKET:    'splice://stitchers-market.onion',
    NULL_FRONT:          'splice://null-front.board',
    SPECTRE_DEFENSE:     'splice://spectre-defense.militia',
    SIN_NEWS:            'splice://sin-news.com',
    INLAND_BIZ:          'splice://inland-biz.io',
    VALLEY_VOICE:        'splice://valley-voice.org',
    WIRE_DEAD:           'splice://wire-dead.net',

    // Codex system — restricted terminals
    CODEX_AVISTA_SUB09:      'splice://avista-grid.com/sub09-terminal',
    CODEX_GONZAGA_LAB404:    'splice://gonzaga-research.edu/lab404-terminal',
    CODEX_STA_VAULT04:       'splice://sta-transit.net/vault04-terminal',
    CODEX_COPPERHEAD_FREIGHT: 'splice://copperhead-chassis.net/freight-terminal',
    CODEX_NULL_IRC:          'splice://null-front.board/irc-relay',

    // Bank Heist target roster — see BANK_TARGET_ROSTER.md and
    // constants/bankTargetConfig.js for content. All render via BankTargetPage.
    BANK_FIRSTMETRO:        'splice://firstmetro-fcu.org',
    BANK_SOLIS:             'splice://solis-lending.io',
    BANK_VANTAGE_POINT:     'splice://vantagepoint-trade.com',
    BANK_AETHER:            'splice://aether-neobank.io',
    BANK_BLUESKY:           'splice://bluesky-funds.com',
    BANK_HYPERION:          'splice://hyperion-vc.io',
    BANK_PENSION_DIRECT:    'splice://pensiondirect.gov',
    BANK_IRONCLAD:          'splice://ironclad-trust.com',
    BANK_AEGIS:             'splice://aegis-wealth.com',
    BANK_KUROGANE:          'splice://kurogane-fleet.co',
    BANK_ZENJIN:            'splice://zenjin-assets.io',
    BANK_HORIZON_MUTUAL:    'splice://horizon-mutual.com',
    BANK_APEX_CAPITAL:      'splice://apex-capital.com',
    BANK_CHRONOS:           'splice://chronos-quant.io',
    BANK_HORIZON_SOVEREIGN: 'splice://horizon-sovereign.offshore',
    BANK_VERITAS:           'splice://veritas-custody.io',
    BANK_NOVA_EXCHANGE:     'splice://nova-exchange.com',
    BANK_STARLIGHT:         'splice://starlight-sovereign.gov',
    BANK_BLACKTIDE:         'splice://blacktide.onion',
};
