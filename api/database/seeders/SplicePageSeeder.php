<?php

namespace Database\Seeders;

use App\Models\SplicePage;
use Illuminate\Database\Seeder;

/**
 * SplicePageSeeder
 *
 * Seeds the "Monroe Street Signal" Codex thread — 15 flavor pages (the
 * SPLICE browser's public-facing sites) plus 5 Codex-tier login-gated pages
 * built from credentials hidden inside those flavor pages. All 20 rows
 * share thread_key = self::THREAD_KEY; activated for a player via
 * QuestStage::codex_thread_key on the Veil "Report Back" stage
 * (DT-hub|1, stage 3) — see QuestStageSeeder. Entirely optional content;
 * nothing here gates required progress.
 *
 * Sourced from docs/CodexLore/ — see that folder for the full authored
 * corpus (world bible, faction spec, per-page manuals) this seeder draws
 * its slugs and text from.
 */
class SplicePageSeeder extends Seeder
{
    private const THREAD_KEY = 'monroe-street-signal';

    public function run(): void
    {
        foreach ($this->flavorPages() as $slug => $page) {
            SplicePage::updateOrCreate(['slug' => $slug], [
                'type'       => 'flavor',
                'title'      => $page['title'],
                'body'       => $page['body'],
                'thread_key' => self::THREAD_KEY,
            ]);
        }

        foreach ($this->codexPages() as $slug => $page) {
            SplicePage::updateOrCreate(['slug' => $slug], [
                'type'               => 'codex',
                'title'              => $page['title'],
                'body'               => $page['body'],
                'unlocked_body'      => $page['unlocked_body'],
                'thread_key'         => self::THREAD_KEY,
                'login_username'     => $page['login_username'] ?? null,
                'credentials'        => $page['credentials'],
                'lead_slugs'         => $page['lead_slugs'],
                'reward_creds'       => $page['reward_creds'],
                'reward_tech_points' => $page['reward_tech_points'],
            ]);
        }
    }

    /**
     * The 15 public SPLICE browser pages. Each body ends with a "found in
     * the fine print" fragment — an in-fiction note a player would have to
     * actually read to the end to catch. A handful of these fragments are
     * the credentials the 5 Codex pages below require; the rest are pure
     * texture with nowhere to plug in (read: possible red herrings).
     */
    private function flavorPages(): array
    {
        return [
            'avista-grid' => [
                'title' => 'A.V.I.S.T.A. — Municipal Grid Management Portal',
                'body'  => <<<'TXT'
================================================================================
ALPINE VALLEY INTEGRATED SUBSTATION & TRANSMISSION AUTHORITY (A.V.I.S.T.A.)
MUNICIPAL GRID MANAGEMENT PORTAL // REGION 09 - SPOKANE BASIN
================================================================================

[SYSTEM NOTICE: CURRENT LOAD BALANCING ACTIVE]
Grid Stability Index: 81.4% (WARNING: Sub-Station 09 Reporting Harmonic Drift)

[ANNOUNCEMENT - 08.11.2026]
Scheduled Load Shedding in Progress for Sector 4 (Hillyard / North Corridor).
Power redirection protocols are in effect to preserve core University District
research telemetry. Residential accounts may experience intermittent brownouts
between 22:00 and 04:00 PST.

DO NOT APPROACH HIGH-VOLTAGE SUBSTATION ENCLOSURES.
UNAUTHORIZED TAP SPLICING IS A CLASS-2 MUNICIPAL FELONY.

--------------------------------------------------------------------------------
[TECHNICIAN TERMINAL AUTHENTICATION REQUIRED]
Terminal ID: STN-09-MONROE
Enter Grid Operator PIN: [ _ _ _ _ _ _ ]
--------------------------------------------------------------------------------

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
DEBUG_NOTE: Substation 09 transformer line 115kV-B is bypassed.
If power draw spikes past 88MW, line breaker auto-trips.
MANUAL OVERRIDE KEY: AV-8809-SUB-BYPASS
--------------------------------------------------------------------------------
TXT,
            ],

            'providence-health' => [
                'title' => 'P.R.O.V.I.D.E.N.C.E. Healthcare & Vital Diagnostics — Patient Portal',
                'body'  => <<<'TXT'
================================================================================
P.R.O.V.I.D.E.N.C.E. HEALTHCARE & VITAL DIAGNOSTICS
"PRESERVING LIFE THROUGH ADVANCED NEURAL ARCHITECTURE"
================================================================================

[CRITICAL PATIENT SAFETY BULLETIN // DEPRECATION NOTICE]
Effective 08.01.2026, all Aetheron Bio-Synthetics Series-7 Sensory Bus arrays
are officially classified as OUT-OF-LEASE and END-OF-LIFE.

Patients currently fitted with Series-7 hardware must report to the South Hill
Enclave for mandatory firmware decommission. Failure to update to Series-9
autonomic buses may result in localized neural rejection, auditory phantom
interference, or sudden chassis shutdown.

[PATIENT PORTAL LOG-IN]
Patient Medical Record Number (MRN): [ _ _ _ - _ _ _ - _ _ ]
Auth Key: [ _ _ _ _ _ _ ]

[SEARCH RECALL DATABASE]
Query: [ Enter Hardware Serial Number... ]

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
INTERNAL MEMO: Bio-Debt Collections Division
Patient #88921 (Escaped Clinic 04): Neural bus signal still broadcasting on
441.2MHz. Dispatch Monolith containment team if signal crosses Sprague Ave.
--------------------------------------------------------------------------------
TXT,
            ],

            'itron-telemetry' => [
                'title' => 'I.T.R.O.N. — Automated Municipal Sensor Array Monitoring',
                'body'  => <<<'TXT'
================================================================================
I.T.R.O.N. TELEMETRY & REMOTE OPERATIONAL NETWORKS
AUTOMATED MUNICIPAL SENSOR ARRAY MONITORING // SPOKANE METRO
================================================================================

NODE_ID: MUNI-SENS-7712
STATUS: ONLINE [LATENCY: 4ms]
FREQUENCY BAND: 915MHz Industrial Mesh

LIVE SENSOR FEED READOUT:
--------------------------------------------------------------------------------
LOC: MONROE_ST_BRIDGE_UNDERDECK  | LOAD: 142.8 kW  | TEMP: 41°C | SIG: 98%
LOC: HILLYARD_SWITCH_YARD_04     | LOAD:  12.1 kW  | TEMP: 62°C | SIG: 42% [WARN]
LOC: BROWNES_ADD_SUB_BASEMENT_01 | LOAD: 890.4 kW  | TEMP: 78°C | SIG: 12% [ANOMALY]
--------------------------------------------------------------------------------

[ALERT]: Unregistered high-draw current tap detected at BROWNES_ADD_SUB_BASEMENT_01.
Power telemetry signature matches unauthorized high-density neural array modeling.

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
ITRON_ZERO_DAY_NOTE: Firmware v4.12 left port 8088 unencrypted.
Inject string 'ITRON_NET_BYPASS' to dump raw power log pcap files.
--------------------------------------------------------------------------------
TXT,
            ],

            'wwp-archive' => [
                'title' => 'W.W.P. — Historical Infrastructure Archive',
                'body'  => <<<'TXT'
================================================================================
WEST-CASCADE WATER & POWER TRANSMISSION GROUP (W.W.P.)
HISTORICAL INFRASTRUCTURE ARCHIVE // EST. 1889
================================================================================

DOCUMENT REF: WWP-BLUEPRINT-1988-REV4
CLASSIFICATION: DECOMMISSIONED CIVIC CONDUITS
LOCATION: SPOKANE RIVER FALLS / UPPER DAM SUB-TUNNELS

TRANSCRIPT NOTE:
Heavy cast-iron access hatches along the riverbed conduit line remaining from
the 1988 hydro-expansion were never backfilled during the SMDA takeover.
Secondary 12-inch drainage pipes connect directly from Riverfront Park
underground vaults to the old G.O.N.Z.A.G.A. physics basement.

[WARNING]: Conduits may contain legacy high-voltage oil-filled transformers.
Do not breach iron seals without grounded rubber insulation.

[DOWNLOAD ARCHIVAL SCHEMATIC: WWP_HYDRO_MAP_1988.PDF]

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
ARCHIVE NOTE: Maintenance hatch lock code in 1988 was set to physical key
combination 1889.
--------------------------------------------------------------------------------
TXT,
            ],

            'gonzaga-whitepaper' => [
                'title' => 'G.O.N.Z.A.G.A. — Dept. of Advanced Signal Harmonics & Neural Meshing',
                'body'  => <<<'TXT'
================================================================================
GLOBAL OPTICAL & NEURAL ZERO-POINT ARCHITECTURE GRADUATE ACADEMY
DEPARTMENT OF ADVANCED SIGNAL HARMONICS & NEURAL MESHING
================================================================================

PUBLISHED WHITEPAPER ABSTRACT [REF: LAB-404-PERSISTENCE]:
"Sub-Quantum Resonant Harmonics in Localized Augmentation Mesh Grids"
Authors: Dr. E. Vance, Dr. H. Miller (Disavowed)

Abstract: Research indicates that continuous high-frequency modulation along
municipal power lines (Splice Frequencies) creates localized standing waves.
Under specific harmonic parameters (441.25MHz at 88kW draw), sensory data
persists within the metal conduit even after signal transmission ceases.

[NOTICE]: All subterranean laboratory facilities beneath the Applied Physics
Building have been SEALED BY MUNICIPAL INJUNCTION following the 2024 testing
incident. Student entry is strictly prohibited.

[FACULTY LOGIN]
NetID: [ _ _ _ _ _ _ ]
Password: [ _ _ _ _ _ _ _ _ ]

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
FACULTY_MEMO: Vance left his lab network key in the whitepaper footnotes.
Lab Server Passphrase: HARMONIC_RESONANCE_441
--------------------------------------------------------------------------------
TXT,
            ],

            'sta-transit' => [
                'title' => 'S.T.A. — System Status & Automated Route Network',
                'body'  => <<<'TXT'
================================================================================
SPOKANE SUBTERRANEAN & TRANSIT AUTOMATION (S.T.A.)
SYSTEM STATUS & AUTOMATED ROUTE NETWORK
================================================================================

[SYSTEM ALERT]: Monorail Line 2 (Valley Express) operating on 15-minute delays
due to Monolith Tactical security inspections at Sprague Station.

[SUBTERRANEAN SERVICE TUNNEL ALERT]:
Tunnel Vault 04 (Monroe Street Lower Cut) is CLOSED to all maintenance personnel.
Unregistered physical hardware setups and unauthorized cable runs have been
detected along the third rail. Monolith security units have been dispatched.

S.T.A. MAINTENANCE SCHEDULE:
- Line 1 (Downtown Loop): NORMAL OPERATING STATUS
- Line 2 (Valley Corridor): DELAYED [MONOLITH SWEEP]
- Sub-Level 3 (Freight Rail): CLOSED [UNAUTHORIZED HARDWARE DETECTED]

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
STA_MAINT_NOTE: Underground tunnel gate 3 override code: STA_SUB_LEVEL_03
--------------------------------------------------------------------------------
TXT,
            ],

            'copperhead-parts' => [
                'title' => 'C.O.P.P.E.R.H.E.A.D. — Heavy Equipment & Chassis Modification',
                'body'  => <<<'TXT'
================================================================================
C.O.P.P.E.R.H.E.A.D. HEAVY EQUIPMENT & CHASSIS MODIFICATION
"IF IT AIN'T HEAVY STEEL, IT AIN'T RUNNING THE VALLEY"
================================================================================

PARTS CATALOG // INVENTORY LOG:
--------------------------------------------------------------------------------
[ITEM #CH-8801] Mil-Spec Hydraulic Suspension Struts (Pair)
- Condition: Rebuilt / Serial Numbers Shaved
- Price: 1.2 ETH or 400kg Structural Steel Scrap
- Notes: Fits lifted 1990s 4x4 off-road chassis. Reinforcement welded.

[ITEM #CH-9042] High-Draw Power Converter (A.V.I.S.T.A. Surplus)
- Condition: Hot-wired / Unlocked Voltage Governors
- Price: Contact Big Mike at the Valley Yard
- WARNING: Draws enough current to black out a city block if ungrounded.
--------------------------------------------------------------------------------

[ENCRYPTED FREIGHT REQUEST]:
Enter Syndicate Drop Passcode: [ _ _ _ _ _ _ _ _ ]

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
KNUCKLE_NOTE: Mike, stop sending shaved-serial steel to Patch.
Monolith scanned the last shipment at the river bridge. Passkey: COPPER_HEAVY_99
--------------------------------------------------------------------------------
TXT,
            ],

            'inland-leasing' => [
                'title' => 'Inland Commercial Properties & Asset Management',
                'body'  => <<<'TXT'
================================================================================
INLAND COMMERCIAL PROPERTIES & ASSET MANAGEMENT
"PREMIER COMMERCIAL LEASING ACROSS THE INLAND NORTHWEST"
================================================================================

AVAILABLE LEASING OPPORTUNITIES:

PROPERTY #408: Browne's Addition Underground Storage Vault
- Size: 1,200 sq. ft. Sub-Level Space
- Features: Reinforced Concrete, High-Amp Power Line Access (Unmetered)
- Occupancy Note: Includes mandatory monthly "Utility Asset Management" fee
  payable directly to Inland Logistics Network.

--------------------------------------------------------------------------------
[TENANT PROTECTION & DUES PORTAL]
Account Number: [ _ _ _ - _ _ _ ]
Protection Voucher Key: [ _ _ _ _ _ _ _ _ ]
*Notice: Late payment of monthly protection fees will result in immediate
 power line severing and location reporting to Monolith Security.*
--------------------------------------------------------------------------------

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
LEASE_ADMIN: Patch's clinic dues are two weeks late.
Send Frankie down to cut his power drop if he doesn't transfer 500 Credits
by Friday.
--------------------------------------------------------------------------------
TXT,
            ],

            'stitchers-market' => [
                'title' => 'S.T.I.T.C.H.E.R.S. Clearinghouse — Surgical Hardware Market',
                'body'  => <<<'TXT'
================================================================================
S.T.I.T.C.H.E.R.S. CLEARINGHOUSE // SURGICAL HARDWARE MARKET
[OFFLINE / ONION ROUTED NODE]
================================================================================

[UNVERIFIED INVENTORY LISTINGS]:
--------------------------------------------------------------------------------
LOT #9901: Aetheron Series-7 Sensory Bus (Grade-B / Used)
- Source: Recovered / Serial Shaved
- Status: Minor neural feedback recorded prior to extraction.
- Price: 0.4 ETH (No refunds once spliced)

LOT #9905: Mil-Spec Ocular Neural Mesh
- Source: Unknown
- Status: UNTESTED. Requires Cyber Doc to clear residual memory cache.
--------------------------------------------------------------------------------

[SERIAL CHECKER]: Test if your hardware serial is flagged on Monolith/Providence
stolen asset registries before installation.
[ INPUT SERIAL: _ _ _ _ _ _ _ _ _ _ ]

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
WARNING: Do not buy Lot #9901. Sensory bus came off a patient who died of
resonance shock. It still broadcasts an auto-ping to Providence hospital
servers.
--------------------------------------------------------------------------------
TXT,
            ],

            'null-forum' => [
                'title' => 'N.U.L.L. — Neural Uncoupling & Logic Liberation Front',
                'body'  => <<<'TXT'
================================================================================
N.U.L.L. // NEURAL UNCOUPLING & LOGIC LIBERATION FRONT
"THE FREQUENCY BELONGS TO EVERYONE"
================================================================================

  _  _ _  _ _    _
  |\ | |  | |   | |
  | \| |__| |___| |___

[THREAD: Persistence Theory is Real? — Proof inside A.V.I.S.T.A. Logs]
Posted by: @ghost_signal_99 [08.10.2026]

"If you splice into the 115kV tap under Monroe Street Bridge at 441.25MHz,
you pick up something in the noise that sounds almost like audio, echoing
back from around the time of the 2024 Gonzaga lab meltdown. Probably nothing.
Probably just harmonic bleed off the old iron. But Apex sure seems to want
that substation quiet, and I can't find a boring reason why."

[RECENT EXPLOIT DROPS]:
- `itron_telemetry_dump.sh` (Exploits unencrypted port 8088)
- `providence_firmware_bypass.bin` (Overrides Series-7 brick commands)

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
NULL_IRC_CHANNEL: Connect to 127.0.0.1:6667 #null-underground
KEY: UNLINK_THE_GRID
--------------------------------------------------------------------------------
TXT,
            ],

            'spectre-manifesto' => [
                'title' => 'S.P.E.C.T.R.E. — Electromagnetic Containment Enclave',
                'body'  => <<<'TXT'
================================================================================
S.P.E.C.T.R.E. // ELECTROMAGNETIC CONTAINMENT ENCLAVE
"PURGE THE WIRE. CLEANSE THE SIGNAL."
================================================================================

MANIFESTO EXTRACT #14:
"The Splice Frequency is not progress. It is neural poisoning transmitted through
corporate copper lines into the human brain. Every augmented citizen is a
relay node for Apex and Providence surveillance."

TARGET WARNING MAP // KNOWN FREQUENCY NODES:
- Substation 09 (Monroe Bridge): HIGH POISONING LEVEL
- University District Fiber Conduit: SEVERE SIGNAL LEAKAGE

DIRECTIVE: Local EMP dampeners are being deployed along Valley relay towers.
Prepare for localized blackout ops.

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
CELL_NOTE: Next EMP charge deployment scheduled for Substation 09
transformer line.
--------------------------------------------------------------------------------
TXT,
            ],

            'sin-news' => [
                'title' => 'Spokane Information Network (S.I.N.)',
                'body'  => <<<'TXT'
================================================================================
SPOKANE INFORMATION NETWORK (S.I.N.)
"YOUR TRUSTED VOICE FOR CIVIC PROGRESS & SAFETY"
================================================================================

[BREAKING NEWS TICKER]
*** SMDA ANNOUNCES SUCCESSFUL INFRASTRUCTURE UPGRADES IN NORTH CORRIDOR ***
*** MONOLITH TACTICAL REPORTS RECORD LOW CRIME RATES IN DOWNTOWN CORE ***

HEADLINE STORY:
"VANDALISM AT SUBSTATION 09 CAUSES BRIEF VOLTAGE DIP; UTILITY OFFICIALS URGE CALM"

Spokane Municipal Development Authority (SMDA) representatives confirmed that
a minor technical anomaly at Substation 09 was caused by illegal infrastructure
tampering by radical anti-civic agitators. Power has been restored to core
corporate facilities, and repair crews are working around the clock.

Monolith Tactical Security has deployed additional rapid-response units along
the Spokane River corridor to ensure citizen safety.

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
EDITOR_NOTE: Do not mention that 4 residential blocks lost power for 8 hours.
Frame all blackouts as 'scheduled grid balancing.'
--------------------------------------------------------------------------------
TXT,
            ],

            'ibj-financial' => [
                'title' => 'Inland Business Journal (I.B.J.)',
                'body'  => <<<'TXT'
================================================================================
INLAND BUSINESS JOURNAL (I.B.J.)
FINANCIAL DATA, CORPORATE MARKET ANALYTICS & LOGISTICS
================================================================================

MARKET SUMMARY [08.12.2026]:
- AETHERON BIO (AETH):  UP $142.50 (+4.2%) [Series-9 Patent Approved]
- APEX INFRA (APX):     DOWN $88.10 (-1.8%) [Substation 09 Outage Impact]
- MONOLITH SEC (MNS):   UP $210.00 (+6.1%) [Municipal Security Contract Extended]

DEEP-DIVE ANALYSIS:
"Aetheron Bio-Synthetics (AETH) Secures Regional Monopoly Following Series-7 Deprecation"
By forcing mandatory firmware phase-outs on legacy sensory hardware, Aetheron
projects a 34% increase in Q3 replacement component revenues across the Inland Northwest.

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
IBJ_LEAK: Insider trading memo shows Monolith buying stock in Aetheron
before the recall notice was posted.
--------------------------------------------------------------------------------
TXT,
            ],

            'valley-voice-news' => [
                'title' => 'The Valley Voice — Independent Neighborhood Journalism',
                'body'  => <<<'TXT'
================================================================================
THE VALLEY VOICE // INDEPENDENT NEIGHBORHOOD JOURNALISM
"FOR THE PEOPLE WHO KEEP SPOKANE RUNNING"
================================================================================

HEADLINE:
"THIRD STRAIGHT NIGHT OF BLACKOUTS IN HILLYARD: WHERE IS THE POWER GOING?"
While corporate offices in the University District glow brightly all night,
working families in Hillyard and the Valley are sitting in the dark.

Local street electrician Knuckle claims A.V.I.S.T.A. isn't losing power—they're
diverting it. "They're pumping high voltage into the old underground conduits
near the river. Something down there is pulling massive wattage, and it isn't
a residential refrigerator."

COMMUNITY CLASSIFIEDS:
- Lost: Sensory Bus Diagnostic Tool near Browne's Addition. Reward offered.
  Contact Patch at the alley clinic.

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
VALLEY_NOTE: If SMDA shuts down our web domain again, mirror the text feed
to wire-dead.net
--------------------------------------------------------------------------------
TXT,
            ],

            'wire-dead-leak' => [
                'title' => 'WIRE-DEAD — Pirate Data Dump & Raw Leak Feed',
                'body'  => <<<'TXT'
================================================================================
WIRE-DEAD // PIRATE DATA DUMP & RAW LEAK FEED
"NO EDITORS. NO CORPORATE FILTER. PURE SIGNAL."
================================================================================

[RAW DUMP #9942]: MONOLITH PATROL SCHEDULE (SPRAGUE CORRIDOR)
Timestamp: 08.12.2026 - 03:14:00 PST
Source: Intercepted Tactical Feed

02:00 - Sector 1 Patrol (Downtown Core) -> CLEAR
03:00 - Sector 2 Patrol (Sprague Ave / Valley Rail) -> DELAYED 20 MINS (Shift Change)
04:00 - Sector 3 Patrol (Riverfront Park Vaults) -> HEAVY PATROL [ORDERS TO SHOOT ON SIGHT]

[AUDIO LEAK]: Intercepted A.V.I.S.T.A. Engineer Communication
Audio Waveform: [ PLAY AUDIO_LEAK_SUB09.WAV ]
Transcript: "...I don't know, Dave, it's not reading like a short circuit. Line's
throwing something weird into the noise floor, almost sounds structured. Might
just be the recorder picking up interference off the bridge deck. I'll flag it
for someone with better ears than mine."

--------------------------------------------------------------------------------
[UNCACHED FRAGMENT — LEFT IN THE PAGE SOURCE]
WIRE_DEAD_SECRET: The Monroe Street tap password is: SIGNAL_PERSISTS_2026
--------------------------------------------------------------------------------
TXT,
            ],
        ];
    }

    /**
     * The 5 Codex-tier login-gated pages. Each consumes credentials seeded
     * inside the flavor pages above. Two of the five (avista/gonzaga-adjacent
     * and the null-irc-relay) were toned down from their original draft to
     * read as an unconfirmed, in-world conspiracy theory rather than a
     * confirmed reveal — see docs/CodexLore/nodes/*.md for the revision
     * notes on exactly what changed and why.
     */
    private function codexPages(): array
    {
        return [
            'codex-avista-substation-09' => [
                'title'    => 'RESTRICTED — A.V.I.S.T.A. Substation 09 Emergency Line Bypass',
                'login_username' => 'STN-09-MONROE',
                'body'     => <<<'TXT'
================================================================================
A.V.I.S.T.A. // HIGH-VOLTAGE SUBSTATION 09 TELEMETRY OVERRIDE
LOCATION: MONROE STREET BRIDGE UNDERDECK (SPOKANE RIVER)
================================================================================
[WARNING]: ACCESS RESTRICTED TO CERTIFIED GRID OPERATORS.
UNAUTHORIZED ACCESS TO 115kV TRANSFORMER BREAKER BYPASS WILL TRIGGER
MONOLITH TACTICAL DISPATCH.
TXT,
                'unlocked_body' => <<<'TXT'
================================================================================
AUTHENTICATION ACCEPTED // LEVEL-4 OPERATIONAL OVERRIDE ACTIVE
================================================================================
[LOG EXTRACT: VOLTAGE DIVERSIFIED - 08.09.2026 03:22 PST]

Breaker Line 115kV-B status forced OPEN. 88MW current diverted from Hillyard
residential sector into sub-river hydro conduit line #4.

TELEMETRY HARMONIC READOUT:
Line impedance at Monroe Street span showing static resonance wave at 441.25MHz.
Acoustic sensors attached to cast-iron casing registering a repeating structured
pattern in the noise floor. Pattern does not fully attenuate when transformer
load drops to zero. Engineering flagged as unresolved — see attached note.

[UNOFFICIAL NOTE — CHIEF ENGINEER LANDIS, NOT FILED IN THE MAIN LOG]
"We killed the breaker at midnight for maintenance. Line was dead — zero current.
Put the analog tap against the iron casing anyway, out of habit. Swore I picked
up something under the hiss. Could've been cross-talk bleeding up from the
transit tunnels below us, could've been the tap picking up my own pulse through
the handle, wouldn't be the first time. Sounded almost like talking, slowed down
wrong. Didn't write it up official. Not shutting that line down again until
someone with more letters after their name than me explains the readout — if
it's interference, fine, but I'm not volunteering to be the one who finds out
it isn't."

--------------------------------------------------------------------------------
[REWARD]: A.V.I.S.T.A. Substation Master Schematic Downloaded
--------------------------------------------------------------------------------
TXT,
                'credentials' => [
                    ['label' => 'override_key',          'answer' => 'AV-8809-SUB-BYPASS'],
                    ['label' => 'itron_diagnostic_token', 'answer' => 'ITRON_NET_BYPASS'],
                ],
                'lead_slugs'         => ['avista-grid', 'itron-telemetry'],
                'reward_creds'       => 150,
                'reward_tech_points' => 2.0,
            ],

            'codex-gonzaga-lab-404' => [
                'title'    => 'RESTRICTED — G.O.N.Z.A.G.A. Subterranean Physics Lab Terminal (Lab 404)',
                'login_username' => 'E.VANCE',
                'body'     => <<<'TXT'
================================================================================
GLOBAL OPTICAL & NEURAL ZERO-POINT ARCHITECTURE GRADUATE ACADEMY
SUB-LEVEL RESEARCH LABORATORY // FACULTY & RESEARCH ONLY
================================================================================
[NOTICE]: FACULTY SEAL IN EFFECT. ALL DATA SUBJECT TO SMDA COURT ORDER #4409.
TXT,
                'unlocked_body' => <<<'TXT'
================================================================================
FACULTY AUTHENTICATION SUCCESSFUL // DR. E. VANCE (DISAVOWED)
================================================================================
[LAB JOURNAL EXTRACT - DR. VANCE - 11.14.2024]

Nobody's going to publish this, so I'm just writing it down for myself.

Miller thinks I'm chasing static. Maybe I am. But the zero-point array logged a
return signal tonight before I ever switched the emitter on — an echo with no
transmission behind it. Instrument error is the boring explanation and it's
probably the right one. I've ruled out three sources of interference already
and I'm running out of boring explanations to check.

My un-boring one, which I will deny ever writing if this gets read by anyone
but me: old infrastructure holds onto more than heat and rust. I'm not saying
memory — I couldn't defend that word in front of a review board and I wouldn't
try. I'm saying the pattern in the noise floor doesn't look random to me, and
I've been doing signal analysis for eleven years. That's it. That's the whole
theory.

Miller says he's picked up something in the carrier hiss he didn't like either.
He won't say what. I'm not going to push him on it. If this journal gets out
before I have real data behind it, I'm finished here — and I'd probably
deserve it.

--------------------------------------------------------------------------------
[REWARD]: Zero-Point Signal Harmonics Decryption Key
--------------------------------------------------------------------------------
TXT,
                'credentials' => [
                    ['label' => 'passphrase', 'answer' => 'HARMONIC_RESONANCE_441'],
                ],
                'lead_slugs'         => ['gonzaga-whitepaper'],
                'reward_creds'       => 200,
                'reward_tech_points' => 3.0,
            ],

            'codex-sta-tunnel-vault-04' => [
                'title'    => 'RESTRICTED — S.T.A. Subterranean Service Tunnel Vault 04',
                'login_username' => 'STA-MAINT-04',
                'body'     => <<<'TXT'
================================================================================
S.T.A. SUBTERRANEAN TRANSIT AUTOMATION // SYSTEM GATEWAY
SERVICE TUNNEL VAULT 04 // MONROE CUT ACCESS
================================================================================
[WARNING]: RESTRICTED DANGER ZONE. UNPOWERED THIRD RAIL & STRUCTURAL DECAY.
TXT,
                'unlocked_body' => <<<'TXT'
================================================================================
HYDRAULIC SEAL RELEASED // ACCESS GRANTED TO LOWER HYDRO-CONDUIT 04
================================================================================
[AUTOMATED SENSOR REPORT - S.T.A. MAINTENANCE ROBOT #09]

Location: Junction point between S.T.A. Line 2 Sub-Tunnel and 1889 W.W.P. Iron
Conduit.

ENVIRONMENTAL READINGS:
- Ambient Temp: 11°C
- Water Level: 4 Inches (SPOKANE RIVER SEEPAGE)
- Unregistered Hardware Detected: YES

STRUCTURE DETAILED DESCRIPTION:
Heavy cast-iron hydro pipe spliced open with carbon cutting torches. Spliced
into the main trunk line are twelve hand-soldered optical rigs, wrapped in
copper mesh. Rigs are drawing unmetered current directly from the A.V.I.S.T.A.
115kV line above. Rigs are running continuous looped memory buffers formatted
for P.R.O.V.I.D.E.N.C.E. Series-7 sensory arrays.

ROBOT NOTE: Operator flagged an unusual acoustic reading near the water surface
around the cables during the sweep — logged as unresolved, no source identified.
No human personnel present at time of inspection.

--------------------------------------------------------------------------------
[REWARD]: Subterranean Transit Map Shortcuts Unlocked
--------------------------------------------------------------------------------
TXT,
                'credentials' => [
                    ['label' => 'tunnel_override_code', 'answer' => 'STA_SUB_LEVEL_03'],
                    ['label' => 'historic_hatch_key',    'answer' => '1889'],
                ],
                'lead_slugs'         => ['sta-transit', 'wwp-archive'],
                'reward_creds'       => 150,
                'reward_tech_points' => 2.0,
            ],

            'codex-copperhead-freight-manifest' => [
                'title'    => 'RESTRICTED — C.O.P.P.E.R.H.E.A.D. / Knuckle Freight Logistics Terminal',
                'login_username' => 'COPPERHEAD-OPS',
                'body'     => <<<'TXT'
================================================================================
C.O.P.P.E.R.H.E.A.D. HEAVY FREIGHT & FREIGHT DROP LOGISTICS
================================================================================
ENCRYPTED SYNDICATE NODE. AUTHORIZED CHOP SHOP OPERATORS ONLY.
TXT,
                'unlocked_body' => <<<'TXT'
================================================================================
LOGISTICS MANIFEST UNLOCKED // VALLEY YARD DROP #44
================================================================================
[SHIPMENT LOG: KNUCKLE -> PATCH (ALLEY CLINIC)]

- Cargo: 800kg Reinforced Structural Steel Plate, 4x Mil-Spec Converter Rails.
- Transit Route: Via S.T.A. Sub-Level 3 Service Tunnels (Bypassing Sprague
  Monolith Checkpoint).
- Transit Status: DELIVERED.

NOTES FROM KNUCKLE:
"Patch, I got your steel down to the basement clinic. Reinforced the door and
the far wall like you asked. But you need to stop using those Series-7 sensory
buses you're buying off S.T.I.T.C.H.E.R.S.

My boys were welding the converter rails near your shop last night and every
time they struck an arc, the sparks flew in straight lines toward your back
door like a magnet pulling 'em. Whatever you got running inside those patient
decker chassis, it's twisting the local magnetic field. Monolith's scanners
are gonna pick that up sooner or later."

--------------------------------------------------------------------------------
[REWARD]: Heavy Equipment Fabrication Schematics
--------------------------------------------------------------------------------
TXT,
                'credentials' => [
                    ['label' => 'syndicate_passkey', 'answer' => 'COPPER_HEAVY_99'],
                ],
                'lead_slugs'         => ['copperhead-parts'],
                'reward_creds'       => 75,
                'reward_tech_points' => 1.0,
            ],

            'codex-null-irc-relay' => [
                'title'    => 'RESTRICTED — N.U.L.L. Underground IRC Relay Gateway',
                'login_username' => 'NULL-RELAY',
                'body'     => <<<'TXT'
================================================================================
N.U.L.L. // NEURAL UNCOUPLING & LOGIC LIBERATION FRONT
PRIVATE IRC RELAY #null-underground
================================================================================
[AUTHENTICATION REQUIRED]: SIGNAL PURISTS ONLY.
TXT,
                'unlocked_body' => <<<'TXT'
================================================================================
CONNECTED TO #null-underground (ENCRYPTED MESH RELAY)
================================================================================
<@veil>: anyone catch the WIRE-DEAD dump on Substation 09? that audio leak's
         going around the boards again
<@ghost_signal_99>: yeah. patched into the Monroe tap for like ten minutes just
         to see for myself
<@ghost_signal_99>: terminal started printing text I didn't type. old
         hospital-formatted logs, dated a few years back. no idea how current
         reached my rig without me punching anything in
<@ghost_signal_99>: probably cached garbage bleeding through on a dead
         connection. probably
<@veil>: probably. though Providence bricked those old buses for a reason and
         it wasn't warranty costs
<@ghost_signal_99>: got a theory or just throwing shade
<@veil>: no theory. just noticing
<@ghost_signal_99>: well I'm not going back on that node to find out which one
         of us is right
<@veil>: smart. if Monolith or SPECTRE drops a charge on that substation it's
         frying every decker within 5 miles who's still jacked in when it
         happens

--------------------------------------------------------------------------------
[REWARD]: N.U.L.L. Zero-Day Exploit Toolkit (.sh Scripts)
--------------------------------------------------------------------------------
TXT,
                'credentials' => [
                    ['label' => 'irc_channel_key',        'answer' => 'UNLINK_THE_GRID'],
                    ['label' => 'monroe_tap_passphrase',   'answer' => 'SIGNAL_PERSISTS_2026'],
                ],
                'lead_slugs'         => ['null-forum', 'wire-dead-leak'],
                'reward_creds'       => 200,
                'reward_tech_points' => 3.0,
            ],
        ];
    }
}
