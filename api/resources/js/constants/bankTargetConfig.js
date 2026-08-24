/**
 * BANK_TARGET_CONFIG
 *
 * Per-bank display config for the Bank Heist target roster (see
 * BANK_TARGET_ROSTER.md at the repo root). Keyed by the exact splice:// URL
 * each bank is registered under in SpliceRouter.js — one entry per target,
 * rendered by the single shared BankTargetPage.vue component (same pattern
 * as DOC_CONFIG + DocDialoguePage.vue).
 *
 * Content-only, no gameplay data. Bank ICE / account count / reward
 * numbers are a separate concern for BankHeistService once the mechanic's
 * numbers pass lands — this file exists purely to make each target's
 * public-facing SPLICE page look and read like its own institution.
 *
 * `tier` drives the page's color theme (see TIER_THEME in BankTargetPage.vue)
 * and corresponds to BANK_TARGET_ROSTER.md's four progression tiers:
 *   1 = Retail & Community      2 = Neo-Tech & Fast-Yield
 *   3 = Institutional / HNW     4 = Apex & Specialized
 *
 * NOTE: the source roster this was drawn from claimed 20 targets but only
 * lists 19 (10 banks + 9 brokerages) — one brokerage short of the stated
 * count. Flagged for the user; this file covers the 19 that exist.
 */
export const BANK_TARGET_CONFIG = {
    // ── Tier 1 — Retail & Community (Early Game) ──────────────────────────────
    'splice://firstmetro-fcu.org': {
        name: 'First Metro Federal Union', type: 'Bank', tier: 1,
        tagline: 'Your Neighborhood, Your Union.',
        focus: 'Everyday credit union and municipal checking accounts.',
        securityProfile: 'Low defenses, unpatched software, predictable admin credentials.',
    },
    'splice://solis-lending.io': {
        name: 'Solis Micro-Lending', type: 'Bank', tier: 1,
        tagline: 'Funding Runs on Solis.',
        focus: 'Micro-loans and P2P crowdfunding routing.',
        securityProfile: 'Built fast on open-source frameworks; poor input validation.',
    },
    'splice://vantagepoint-trade.com': {
        name: 'Vantage Point Securities', type: 'Brokerage', tier: 1,
        tagline: 'Trade From Any Angle.',
        focus: 'Retail day traders and fractional meme-stock positions.',
        securityProfile: 'Heavy reliance on client-side logic and quick-turnaround endpoints.',
    },

    // ── Tier 2 — Neo-Tech & Fast-Yield (Mid Game) ─────────────────────────────
    'splice://aether-neobank.io': {
        name: 'Aether Neobank', type: 'Bank', tier: 2,
        tagline: 'Banking, Untethered.',
        focus: 'Gen-Z and gig-worker checking with instant transfers.',
        securityProfile: 'Sleek UI hiding poorly configured APIs.',
    },
    'splice://bluesky-funds.com': {
        name: 'BlueSky Index Funds', type: 'Brokerage', tier: 2,
        tagline: 'Clear Skies for the Long Haul.',
        focus: 'Mass retirement and 401(k) holdings.',
        securityProfile: 'Dated enterprise middleware; daily batch-processing scripts.',
    },
    'splice://hyperion-vc.io': {
        name: 'Hyperion Venture Capital', type: 'Brokerage', tier: 2,
        tagline: 'Backing What Comes Next.',
        focus: 'Unlisted pre-IPO equity and convertible notes.',
        securityProfile: 'Decoupled cloud servers; poor internal access control post-perimeter breach.',
    },
    'splice://pensiondirect.gov': {
        name: 'Pension Direct Assurance', type: 'Bank', tier: 2,
        tagline: 'Securing Tomorrow, Today.',
        focus: 'Government pensions and municipal annuities.',
        securityProfile: 'Outdated web application firewalls; sprawling legacy databases.',
    },

    // ── Tier 3 — Institutional & High-Net-Worth (Late Game) ───────────────────
    'splice://ironclad-trust.com': {
        name: 'Ironclad Vault & Trust', type: 'Bank', tier: 3,
        tagline: 'Generations of Trust.',
        focus: 'Old-money family trusts and high-yield municipal bonds.',
        securityProfile: 'Rigid multi-factor authentication; air-gapped legacy mainframes.',
    },
    'splice://aegis-wealth.com': {
        name: 'Aegis Wealth Management', type: 'Brokerage', tier: 3,
        tagline: 'A Shield for Every Portfolio.',
        focus: 'Generational wealth and blue-chip holdings.',
        securityProfile: 'Multi-tier approvals; manager sign-offs on outward transactions.',
    },
    'splice://kurogane-fleet.co': {
        name: 'Kurogane Fleet Bank', type: 'Bank', tier: 3,
        tagline: 'Financing the Fleet.',
        focus: 'Industrial fleet supply chains and heavy machinery financing.',
        securityProfile: 'Heavy network segmentation between operations and financial vaults.',
    },
    'splice://zenjin-assets.io': {
        name: 'Zenjin Asset Management', type: 'Brokerage', tier: 3,
        tagline: 'Beyond the Horizon.',
        focus: 'Orbital satellite and robotics investment funds.',
        securityProfile: 'Geofenced regional proxy hubs; strict location filtering.',
    },
    'splice://horizon-mutual.com': {
        name: 'Horizon Mutual Insurance', type: 'Brokerage', tier: 3,
        tagline: 'Coverage You Can Count On.',
        focus: 'Insurance reserve capital and low-risk corporate debt.',
        securityProfile: 'Heavily audited but bloated legacy service layers.',
    },

    // ── Tier 4 — High-Risk Apex & Specialized (End Game) ──────────────────────
    'splice://apex-capital.com': {
        name: 'Apex Capital Partners', type: 'Bank', tier: 4,
        tagline: 'Where Capital Moves the World.',
        focus: 'Investment banking, corporate mergers, hedge fund liquidity.',
        securityProfile: 'Active intrusion detection with rapid counter-traces.',
    },
    'splice://chronos-quant.io': {
        name: 'Chronos Quantitative Management', type: 'Brokerage', tier: 4,
        tagline: 'Every Millisecond Counts.',
        focus: 'Algorithmic dark pools and autonomous trading engines.',
        securityProfile: 'Sub-millisecond latency monitoring.',
    },
    'splice://horizon-sovereign.offshore': {
        name: 'Horizon Sovereign Holdings', type: 'Bank', tier: 4,
        tagline: 'Discretion, By Design.',
        focus: 'Offshore tax havens, shell companies, anonymous numbered accounts.',
        securityProfile: 'High-grade encryption, dynamic proxy routing, non-standard account indexing.',
    },
    'splice://veritas-custody.io': {
        name: 'Veritas Crypto-Custody', type: 'Bank', tier: 4,
        tagline: 'Custody Without Compromise.',
        focus: 'Institutional crypto reserves and multi-sig hot wallets.',
        securityProfile: 'Cryptographic multi-signature authorization protocols.',
    },
    'splice://nova-exchange.com': {
        name: 'Nova Exchange', type: 'Brokerage', tier: 4,
        tagline: 'Trade at the Speed of Light.',
        focus: 'Leveraged options and commodity futures.',
        securityProfile: 'High-concurrency state machines processing real-time margin calls.',
    },
    'splice://starlight-sovereign.gov': {
        name: 'Starlight Sovereign Wealth', type: 'Brokerage', tier: 4,
        tagline: 'Stewarding the Nation\'s Reserve.',
        focus: 'Government reserve funds and national infrastructure assets.',
        securityProfile: 'State-level defensive software and honeypot traps.',
    },
    'splice://blacktide.onion': {
        name: 'Black-Tide Liquidity', type: 'Bank / Underground', tier: 4,
        tagline: 'No Names. No Ledger. No Trace.',
        focus: 'Illicit syndicate escrow and black-market contract payouts.',
        securityProfile: 'Hidden onion addresses, custom CLI interfaces, lethal counter-hacking response.',
    },
};
