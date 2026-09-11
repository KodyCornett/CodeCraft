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
 * Fields per entry:
 *   name/type/tier/tagline/focus/securityProfile — original flavor fields.
 *   about            — 2-3 sentence institutional history/character blurb.
 *   services         — 3 short bullet items for the SERVICES section.
 *   news             — 2 dated flavor articles ({date, tag, headline, body})
 *                       for the NEWS section, each nodding at the bank's
 *                       BANK_TARGET_ROSTER.md "Primary Exploit" flavor
 *                       without spelling out any real mechanic.
 *   securityRating   — 0-100 cosmetic "perimeter confidence" number shown
 *                       as a meter bar. Purely flavor — NOT the real Bank
 *                       ICE value the eventual mechanic will use.
 *   ratingUnverified — optional flag for targets with no legitimate audit
 *                       (currently just Black-Tide) — renders a footnote.
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
        about: "Chartered in 1958 as a co-op for Spokane municipal employees, First Metro still runs on the same member-first pitch — free checking, low-fee auto loans, and a staff directory that's a little too public for its own good.",
        services: ['Free Member Checking', 'Auto & Personal Loans', 'Direct Deposit Payroll Routing'],
        news: [
            { date: '08.14.2026', tag: 'COMMUNITY', headline: 'First Metro Sponsors Hillyard Youth Robotics League', body: 'The credit union donated $4,000 in matching funds to the district robotics program, continuing its "Neighbors First" community grant series.' },
            { date: '08.02.2026', tag: 'IT NOTICE', headline: 'Online Banking Portal Maintenance Window Extended', body: 'Members reported intermittent login failures during last week\'s scheduled maintenance. IT staff say the legacy portal update is "still in progress."' },
        ],
        securityRating: 42,
    },
    'splice://solis-lending.io': {
        name: 'Solis Micro-Lending', type: 'Bank', tier: 1,
        tagline: 'Funding Runs on Solis.',
        focus: 'Micro-loans and P2P crowdfunding routing.',
        securityProfile: 'Built fast on open-source frameworks; poor input validation.',
        about: 'Solis launched three years ago promising same-day micro-loans for gig workers locked out of traditional underwriting. The platform grew fast — faster, some say, than its engineering team could keep up with.',
        services: ['Same-Day Micro-Loans', 'P2P Crowdfund Routing', 'Gig-Worker Credit Building'],
        news: [
            { date: '08.19.2026', tag: 'GROWTH', headline: 'Solis Crosses 40,000 Active Borrowers', body: 'The lending platform announced record loan origination volume for Q2, crediting its "frictionless" signup flow.' },
            { date: '07.28.2026', tag: 'FORUM LEAK', headline: 'User Complaints Mount Over Password Reset Flow', body: 'A thread on a lending-forum board claims Solis\'s reset emails include more account detail than "any bank should ever put in plaintext."' },
        ],
        securityRating: 38,
    },
    'splice://vantagepoint-trade.com': {
        name: 'Vantage Point Securities', type: 'Brokerage', tier: 1,
        tagline: 'Trade From Any Angle.',
        focus: 'Retail day traders and fractional meme-stock positions.',
        securityProfile: 'Heavy reliance on client-side logic and quick-turnaround endpoints.',
        about: 'Vantage Point built its name on zero-commission trades and a slick mobile app that made fractional shares of OMNI and VOLT feel like a video game. The backend has not aged as gracefully as the UI.',
        services: ['Zero-Commission Trading', 'Fractional Share Positions', 'Real-Time Market Alerts'],
        news: [
            { date: '08.20.2026', tag: 'MARKETS', headline: 'OMNI Rallies on Vantage Point Retail Volume Surge', body: 'Retail order flow through Vantage Point\'s app reportedly moved OMNI\'s share price nearly 6% in a single session.' },
            { date: '08.09.2026', tag: 'APP UPDATE', headline: 'v4.2 Ships With "Under-the-Hood" Session Handling Rework', body: 'Release notes promise faster order execution. Some users on social media noted their sessions now "never seem to expire."' },
        ],
        securityRating: 45,
    },

    // ── Tier 2 — Neo-Tech & Fast-Yield (Mid Game) ─────────────────────────────
    'splice://aether-neobank.io': {
        name: 'Aether Neobank', type: 'Bank', tier: 2,
        tagline: 'Banking, Untethered.',
        focus: 'Gen-Z and gig-worker checking with instant transfers.',
        securityProfile: 'Sleek UI hiding poorly configured APIs.',
        about: 'Aether markets itself as the anti-bank — no branches, no fees, an app that looks better than banking has any right to. Underneath, its account API trusts client requests more than it probably should.',
        services: ['Instant P2P Transfers', 'No-Fee Checking', 'Early Paycheck Access'],
        news: [
            { date: '08.17.2026', tag: 'FUNDING', headline: 'Aether Closes $40M Series C Led by Riverline Capital', body: 'The neobank says the raise will fund "aggressive" user growth heading into next year.' },
            { date: '07.30.2026', tag: 'DEV BLOG', headline: 'Engineering Team Posts "Scaling Our Account API" Retrospective', body: 'The post details how account lookups were moved client-side "for speed" — a decision since quietly walked back in the comments.' },
        ],
        securityRating: 51,
    },
    'splice://bluesky-funds.com': {
        name: 'BlueSky Index Funds', type: 'Brokerage', tier: 2,
        tagline: 'Clear Skies for the Long Haul.',
        focus: 'Mass retirement and 401(k) holdings.',
        securityProfile: 'Dated enterprise middleware; daily batch-processing scripts.',
        about: 'BlueSky has quietly managed retirement money for two generations of Inland Northwest workers. Its front end got a redesign in 2019; the nightly batch jobs underneath did not.',
        services: ['401(k) Fund Management', 'Target-Date Retirement Plans', 'Employer Plan Administration'],
        news: [
            { date: '08.11.2026', tag: 'RETIREMENT', headline: 'BlueSky OMNI/BYTE Target-Date Fund Beats Sector Average', body: 'Fund managers credit "disciplined rebalancing" for the outperformance this quarter.' },
            { date: '07.22.2026', tag: 'IT', headline: 'Overnight Batch Delay Pushes Statement Postings to Midday', body: 'BlueSky apologized for the delay, attributing it to "a scheduling conflict" in the nightly reconciliation run.' },
        ],
        securityRating: 55,
    },
    'splice://hyperion-vc.io': {
        name: 'Hyperion Venture Capital', type: 'Brokerage', tier: 2,
        tagline: 'Backing What Comes Next.',
        focus: 'Unlisted pre-IPO equity and convertible notes.',
        securityProfile: 'Decoupled cloud servers; poor internal access control post-perimeter breach.',
        about: "Hyperion moves fast, funds faster, and treats its own internal tooling with the same startup-speed mentality it backs in founders. Once you're past the front door, not much stands between departments.",
        services: ['Pre-IPO Equity Access', 'Convertible Note Structuring', 'Deal Room Syndication'],
        news: [
            { date: '08.21.2026', tag: 'DEAL FLOW', headline: 'Hyperion Leads $18M Round in Autonomous Logistics Startup', body: 'Partners called the deal "a clear bet on the next decade of freight automation."' },
            { date: '08.03.2026', tag: 'INTERNAL', headline: 'All-Hands Memo Urges "Tighter" Deal Room Access Controls', body: 'A leaked internal memo asked staff to "please stop reusing the same login across deal rooms" — with limited apparent effect.' },
        ],
        securityRating: 58,
    },
    'splice://pensiondirect.gov': {
        name: 'Pension Direct Assurance', type: 'Bank', tier: 2,
        tagline: 'Securing Tomorrow, Today.',
        focus: 'Government pensions and municipal annuities.',
        securityProfile: 'Outdated web application firewalls; sprawling legacy databases.',
        about: 'Pension Direct administers municipal retirement funds across the county — a mission-critical, low-glamour job running on database schemas that predate most of its current staff.',
        services: ['Municipal Pension Administration', 'Annuity Disbursement', 'Beneficiary Record Management'],
        news: [
            { date: '08.06.2026', tag: 'MUNICIPAL', headline: 'County Renews Pension Direct Administration Contract', body: 'The renewal cites "decades of reliable service" despite ongoing calls for a system modernization audit.' },
            { date: '07.15.2026', tag: 'AUDIT', headline: 'State Auditor Flags "Aging Infrastructure" in Annual Review', body: "The report recommends replacing the pension database's core system \"within the next funding cycle.\"" },
        ],
        securityRating: 53,
    },

    // ── Tier 3 — Institutional & High-Net-Worth (Late Game) ───────────────────
    'splice://ironclad-trust.com': {
        name: 'Ironclad Vault & Trust', type: 'Bank', tier: 3,
        tagline: 'Generations of Trust.',
        focus: 'Old-money family trusts and high-yield municipal bonds.',
        securityProfile: 'Rigid multi-factor authentication; air-gapped legacy mainframes.',
        about: "Ironclad has managed old Spokane money since before the city had a skyline. Its mainframes are air-gapped and its processes are glacial by design — trust, here, is measured in decades, not uptime.",
        services: ['Family Trust Administration', 'Municipal Bond Portfolios', 'Multi-Generational Estate Planning'],
        news: [
            { date: '08.13.2026', tag: 'LEGACY', headline: 'Ironclad Marks 90th Year Managing Downtown Family Trusts', body: 'A commemorative statement praised the firm\'s "unbroken chain of discretion" across three generations of trustees.' },
            { date: '07.19.2026', tag: 'COMPLIANCE', headline: 'Portal Rolls Out Mandatory Hardware Key Login', body: 'Clients now require a physical security key for portal access — a change the bank calls "the last word in account protection."' },
        ],
        securityRating: 74,
    },
    'splice://aegis-wealth.com': {
        name: 'Aegis Wealth Management', type: 'Brokerage', tier: 3,
        tagline: 'A Shield for Every Portfolio.',
        focus: 'Generational wealth and blue-chip holdings.',
        securityProfile: 'Multi-tier approvals; manager sign-offs on outward transactions.',
        about: "Aegis built its reputation on caution — every outward transfer needs a manager's signature, and every signature has a paper trail. The system works exactly as designed, provided nobody can forge the signature.",
        services: ['Blue-Chip Portfolio Management', 'Generational Wealth Transfer', 'Manager-Approved Disbursement'],
        news: [
            { date: '08.16.2026', tag: 'MARKETS', headline: 'Aegis KRO/VOLT Blended Fund Posts Strongest Quarter Since 2022', body: 'Portfolio managers attributed the gain to "disciplined blue-chip weighting."' },
            { date: '07.27.2026', tag: 'INTERNAL', headline: 'Manager Sign-Off Policy Updated After Client Complaint', body: 'Aegis clarified its approval chain after a client reported a delayed transfer "stuck in review for a week."' },
        ],
        securityRating: 76,
    },
    'splice://kurogane-fleet.co': {
        name: 'Kurogane Fleet Bank', type: 'Bank', tier: 3,
        tagline: 'Financing the Fleet.',
        focus: 'Industrial fleet supply chains and heavy machinery financing.',
        securityProfile: 'Heavy network segmentation between operations and financial vaults.',
        about: "Kurogane finances the trucks, cranes, and rail spurs that keep the region's freight moving. Its operations network and its financial vault are deliberately walled apart — a pivot from one side to the other is the whole game.",
        services: ['Heavy Equipment Financing', 'Fleet Supply Chain Lending', 'Shipping Manifest Underwriting'],
        news: [
            { date: '08.10.2026', tag: 'INDUSTRY', headline: 'Kurogane Finances Regional Rail Spur Expansion', body: "The bank called the loan \"a bet on the corridor's freight future\" following a rise in shipping manifest volume." },
            { date: '07.24.2026', tag: 'SECURITY', headline: 'Operations Network Segmentation Audit Completed', body: 'An internal review confirmed "no direct path" between logistics systems and the financial vault — on paper.' },
        ],
        securityRating: 78,
    },
    'splice://zenjin-assets.io': {
        name: 'Zenjin Asset Management', type: 'Brokerage', tier: 3,
        tagline: 'Beyond the Horizon.',
        focus: 'Orbital satellite and robotics investment funds.',
        securityProfile: 'Geofenced regional proxy hubs; strict location filtering.',
        about: "Zenjin bets on orbit and automation — satellite constellations, robotics manufacturers, the AERO/NEXS basket that's outperformed the broader index for three years running. Access is geofenced hub by hub, which mostly just slows down where an attacker has to start.",
        services: ['Orbital & Robotics Fund Access', 'Regional Proxy-Gated Trading', 'Institutional Satellite Investment'],
        news: [
            { date: '08.18.2026', tag: 'MARKETS', headline: 'AERO/NEXS Basket Extends Three-Year Outperformance Streak', body: 'Fund managers pointed to "sustained institutional demand" for orbital infrastructure plays.' },
            { date: '08.01.2026', tag: 'INFRASTRUCTURE', headline: 'Zenjin Adds Third Regional Proxy Hub for Latency', body: 'The new hub is meant to "tighten" access filtering for the fund\'s highest-value accounts.' },
        ],
        securityRating: 73,
    },
    'splice://horizon-mutual.com': {
        name: 'Horizon Mutual Insurance', type: 'Brokerage', tier: 3,
        tagline: 'Coverage You Can Count On.',
        focus: 'Insurance reserve capital and low-risk corporate debt.',
        securityProfile: 'Heavily audited but bloated legacy service layers.',
        about: 'Horizon Mutual sits on decades of reserve capital, audited every year without fail. The audits check the numbers, not the sprawling legacy service layers those numbers actually move through.',
        services: ['Reserve Capital Management', 'Corporate Debt Underwriting', 'Policyholder Portal Services'],
        news: [
            { date: '08.08.2026', tag: 'FINANCE', headline: 'Horizon Mutual Passes Annual Solvency Audit', body: 'Regulators confirmed the insurer\'s reserve ratios "comfortably exceed" minimum requirements.' },
            { date: '07.20.2026', tag: 'IT', headline: 'Policyholder Portal Vendor Support Contract Renewed — Again', body: 'The 2011-era portal framework remains in production under an extended legacy support agreement.' },
        ],
        securityRating: 70,
    },

    // ── Tier 4 — High-Risk Apex & Specialized (End Game) ──────────────────────
    'splice://apex-capital.com': {
        name: 'Apex Capital Partners', type: 'Bank', tier: 4,
        tagline: 'Where Capital Moves the World.',
        focus: 'Investment banking, corporate mergers, hedge fund liquidity.',
        securityProfile: 'Active intrusion detection with rapid counter-traces.',
        about: 'Apex moves the kind of capital that reshapes industries — mergers, hedge fund liquidity, deals with more zeroes than most banks see in a year. Its security team treats every anomalous connection as an active threat, and traces first.',
        services: ['M&A Advisory', 'Hedge Fund Liquidity Solutions', 'Institutional Capital Markets'],
        news: [
            { date: '08.22.2026', tag: 'DEALS', headline: "Apex Advises on Region's Largest Manufacturing Merger This Decade", body: 'The firm called the deal "a defining moment" for its corporate advisory practice.' },
            { date: '08.05.2026', tag: 'SECURITY', headline: 'Apex Deploys Next-Gen Intrusion Detection Across Trading Floor', body: 'The upgrade promises "rapid automated counter-trace" against unauthorized network probes.' },
        ],
        securityRating: 88,
    },
    'splice://chronos-quant.io': {
        name: 'Chronos Quantitative Management', type: 'Brokerage', tier: 4,
        tagline: 'Every Millisecond Counts.',
        focus: 'Algorithmic dark pools and autonomous trading engines.',
        securityProfile: 'Sub-millisecond latency monitoring.',
        about: "Chronos runs autonomous trading engines that execute faster than any human could review. Its systems watch latency down to the microsecond — but latency isn't the only thing worth watching.",
        services: ['Algorithmic Dark Pool Access', 'Autonomous Trading Engines', 'High-Frequency Execution'],
        news: [
            { date: '08.19.2026', tag: 'TECH', headline: 'Chronos Engine Sets Internal Execution Speed Record', body: 'The firm says its latest trading engine revision shaved "critical microseconds" off average execution time.' },
            { date: '07.31.2026', tag: 'MARKETS', headline: 'Regulators Request Dark Pool Volume Disclosure', body: 'Chronos said it is "fully cooperating" with a routine request for expanded reporting.' },
        ],
        securityRating: 90,
    },
    'splice://horizon-sovereign.offshore': {
        name: 'Horizon Sovereign Holdings', type: 'Bank', tier: 4,
        tagline: 'Discretion, By Design.',
        focus: 'Offshore tax havens, shell companies, anonymous numbered accounts.',
        securityProfile: 'High-grade encryption, dynamic proxy routing, non-standard account indexing.',
        about: "Horizon Sovereign doesn't advertise, doesn't have a lobby, and won't confirm whether a given account even exists. Numbered accounts, shell layers, and proxy-routed access are the entire product.",
        services: ['Numbered Account Custody', 'Shell Entity Structuring', 'Offshore Tax Shelter Advisory'],
        news: [
            { date: '08.15.2026', tag: 'NOTICE', headline: 'Horizon Sovereign Rotates Proxy Routing Infrastructure', body: 'A brief client notice cited "routine security hardening" as the reason for the changeover.' },
            { date: '07.26.2026', tag: 'PRESS', headline: 'Financial Press Speculates on Client List After Leak Rumor', body: 'Horizon Sovereign declined to comment, noting only that "discretion is the entire business model."' },
        ],
        securityRating: 91,
    },
    'splice://veritas-custody.io': {
        name: 'Veritas Crypto-Custody', type: 'Bank', tier: 4,
        tagline: 'Custody Without Compromise.',
        focus: 'Institutional crypto reserves and multi-sig hot wallets.',
        securityProfile: 'Cryptographic multi-signature authorization protocols.',
        about: 'Veritas holds institutional crypto reserves behind multi-signature authorization — no single key, no single point of failure, at least in theory. In practice, someone still has to hold each fragment.',
        services: ['Institutional Crypto Custody', 'Multi-Signature Wallet Management', 'Cold & Hot Reserve Structuring'],
        news: [
            { date: '08.20.2026', tag: 'CRYPTO', headline: 'Veritas Reserves Cross $2B in Custodied Assets', body: 'The custodian credited "zero-compromise multi-sig architecture" for the milestone.' },
            { date: '08.02.2026', tag: 'SECURITY', headline: 'Third Key-Holder Added to Flagship Hot Wallet', body: 'Veritas said the added signer "further distributes" custody risk across its key-fragment structure.' },
        ],
        securityRating: 87,
    },
    'splice://nova-exchange.com': {
        name: 'Nova Exchange', type: 'Brokerage', tier: 4,
        tagline: 'Trade at the Speed of Light.',
        focus: 'Leveraged options and commodity futures.',
        securityProfile: 'High-concurrency state machines processing real-time margin calls.',
        about: 'Nova Exchange processes leveraged options and commodity futures through state machines built for pure throughput. At that concurrency, a well-timed race condition is worth more than any password.',
        services: ['Leveraged Options Trading', 'Commodity Futures Execution', 'Real-Time Margin Processing'],
        news: [
            { date: '08.17.2026', tag: 'MARKETS', headline: 'Nova Exchange Volume Hits Quarterly High on Futures Rally', body: 'Exchange officials cited "unprecedented" concurrent order volume during the session.' },
            { date: '07.29.2026', tag: 'TECH', headline: 'Margin Call Engine Upgraded for "Real-Time" Processing', body: 'The new engine processes margin calls "instantaneously" under normal load, per an internal release note.' },
        ],
        securityRating: 84,
    },
    'splice://starlight-sovereign.gov': {
        name: 'Starlight Sovereign Wealth', type: 'Brokerage', tier: 4,
        tagline: 'Stewarding the Nation\'s Reserve.',
        focus: 'Government reserve funds and national infrastructure assets.',
        securityProfile: 'State-level defensive software, honeypot traps.',
        about: 'Starlight stewards government reserve funds and national infrastructure assets — the kind of target that justifies state-level defensive software and a maze of honeypot decoys for anyone probing its perimeter.',
        services: ['Sovereign Reserve Fund Management', 'National Infrastructure Asset Custody', 'State-Level Compliance Reporting'],
        news: [
            { date: '08.21.2026', tag: 'GOVERNMENT', headline: 'Starlight Reserve Fund Posts Record Annual Return', body: 'Officials called the performance "a stabilizing force" for the broader sovereign portfolio.' },
            { date: '08.04.2026', tag: 'SECURITY', headline: 'Defense Contractor Deploys New Honeypot Layer', body: 'The upgrade is designed to "waste the time" of any unauthorized probe before it reaches production systems.' },
        ],
        securityRating: 95,
    },
    'splice://blacktide.onion': {
        name: 'Black-Tide Liquidity', type: 'Bank / Underground', tier: 4,
        tagline: 'No Names. No Ledger. No Trace.',
        focus: 'Illicit syndicate escrow and black-market contract payouts.',
        securityProfile: 'Hidden onion addresses, custom CLI interfaces, lethal counter-hacking response.',
        about: "Black-Tide doesn't exist on any registry, doesn't answer to any regulator, and doesn't forgive intrusion. It handles escrow for people who can't use a real bank — and it protects that arrangement personally.",
        services: ['Anonymous Escrow Custody', 'Off-Ledger Contract Payouts', 'Untraceable Fund Routing'],
        news: [
            { date: '08.23.2026', tag: 'RUMOR', headline: 'Escrow Key Rotation Schedule Reportedly Tightened', body: 'Word on darker boards is that Black-Tide moved to a shorter key-rotation window "after an incident nobody involved will talk about."' },
            { date: '08.06.2026', tag: 'WARNING', headline: 'Anonymous Board Post Warns Against "Testing" Black-Tide\'s Perimeter', body: "The post claims a previous intrusion attempt ended with the attacker's own identity \"surfacing somewhere they didn't want it to.\"" },
        ],
        securityRating: 92,
        ratingUnverified: true,
    },
};
