<?php

namespace Database\Seeders;

use App\Models\CyberDoc;
use App\Models\Node;
use App\Models\QuestArc;
use App\Models\QuestStage;
use Illuminate\Database\Seeder;

/**
 * Seeds quest stages for all arcs.
 *
 * HOW TO ADD QUEST CONTENT:
 *   1. Find the canvas_id and arc sequence_order you want to add stages to.
 *   2. Add entries to the STAGES constant below under the matching key.
 *   3. Run: php artisan db:seed --class=QuestStageSeeder
 *
 * Key format: "CANVAS_ID|arc_sequence_order"
 *   BA-hub = Knuckle  (Browne's Addition)
 *   NS-hub = Patch    (North Spokane)
 *   DT-hub = Veil     (Downtown)
 *   UD-hub = Axiom    (University District)
 *   SV-hub = Float    (Spokane Valley)
 *
 * Stage fields:
 *   stage_number        — order within the arc (1-based)
 *   title               — short label shown in the quest log header
 *   objective_text      — full mission briefing shown in the log body
 *   rep_reward          — rep granted to this doc on completion
 *   is_branch           — true if the player chooses which doc to turn the job into
 *   branch_options      — array: [{"canvas_id": "BA-hub", "label": "Turn job into Knuckle", "rep_reward": 200}, ...]
 *   referral_canvas_id  — canvas_id of the doc to introduce on completion (null if none)
 *   referral_text       — line shown in that doc's log section on introduction
 *   reward_creds        — wallet creds granted on completion (0 = none)
 *   reward_tech_points  — tech points granted on completion (0.0 = none)
 *   reward_node_access  — canvas_id of node to unlock (null = none)
 *   reward_lore_key     — string key unlocking a Splice page or archive entry (null = none)
 *   node_canvas_id      — map node the player must be at to trigger (null = no requirement)
 *   minigame_type       — null | 'data_grab' | 'system_override'
 */
class QuestStageSeeder extends Seeder
{
    private const STAGES = [

        // ── Quest 1: The Climate Override ─────────────────────────────────────────
        // Contractor: Knuckle | District: Browne's Addition | Target: BA-v14
        // ─────────────────────────────────────────────────────────────────────────
        'BA-hub|1' => [
            [
                'stage_number'       => 1,
                'title'              => 'Find Knuckle',
                'objective_text'     => "Your system just tried to melt itself. Something got in — something fast — and your deck is still hemorrhaging noise.\n\nThe message kept repeating the same fragment before your screen went dark: Knuckles.\n\nGet to the BA-Hub. If anyone in this district knows what just happened to your rig, it's him.",
                'rep_reward'         => 0,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'BA-hub',
                'minigame_type'      => null,
            ],
            [
                'stage_number'       => 2,
                'title'              => 'Deploy DISCONNECT_LAYER',
                'objective_text'     => "Knuckle doesn't have answers — he has work. A residential block in Browne's Addition is locked at 50 degrees, grid-capped to redirect power to corporate sectors.\n\nHe's handed you a DISCONNECT_LAYER exploit. Get to node BA-v14 and strip the system-governor. Give those people their heat back.\n\n[WARNING] — Your rig is still leaking. Something inside your system is fighting for bandwidth during every operation. Watch your stability.",
                'rep_reward'         => 0,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'BA-v14',
                'minigame_type'      => 'disconnect_layer',
            ],
            [
                'stage_number'       => 3,
                'title'              => 'Report Back',
                'objective_text'     => "The layer is down. The block is warming up. Residents are flooding local channels — for once, something actually worked.\n\nGet back to Knuckle at the BA-Hub and collect your cut.",
                'rep_reward'         => 25,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => 'DT-hub',
                'referral_text'      => "Knuckle: \"You're still leaking noise. There's a specialist Downtown — goes by Veil. She handles signal corruption that's above my pay grade. Don't mention my name.\"",
                'reward_creds'       => 100,
                'reward_tech_points' => 1,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'BA-hub',
                'minigame_type'      => null,
            ],
        ],

        // ── Quest 2: The Downtown Smoothing Protocol ──────────────────────────────
        // Contractor: Veil | District: Downtown | Target: DT-v8
        // ─────────────────────────────────────────────────────────────────────────
        'DT-hub|1' => [
            [
                'stage_number'       => 1,
                'title'              => 'Find Veil',
                'objective_text'     => "Knuckle's pointed you Downtown. Your deck is still running hot, signature bleeding all over the grid. Veil apparently deals with the kind of signal corruption you're carrying.\n\nGet to the DT-Hub. If she's already tracking your trace — and she probably is — she already knows you're coming.",
                'rep_reward'         => 0,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'DT-hub',
                'minigame_type'      => null,
            ],
            [
                'stage_number'       => 2,
                'title'              => 'Flush the Buffer',
                'objective_text'     => "Veil's not interested in fixing you — she's interested in using you. There's a ghost-signal at node DT-v8, a recursive loop so unstable it would fry a clean rig on contact. Your deck is already a disaster, which makes you the ideal tool.\n\nGet to DT-v8 and run FLUSH_BUFFER. Kill the signal. Don't read the data, don't open the files — just flush it.\n\n[WARNING] — The ghost-signal will react to whatever is already wrong with your system. Your stability will drop fast. Keep the signal contained.",
                'rep_reward'         => 0,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'DT-v8',
                'minigame_type'      => 'flush_buffer',
            ],
            [
                'stage_number'       => 3,
                'title'              => 'Report Back',
                'objective_text'     => "The signal is gone. Your rig is still smoking. Return to Veil at the DT-Hub and collect what she owes you.",
                'rep_reward'         => 25,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => 'SV-hub',
                'referral_text'      => "Veil: \"The data you cleared was pre-collapse. Whatever is in your system recognised it. There's a salvager in Spokane Valley — Float. She trades in exactly this kind of rot. Whether that helps you or not is your problem.\"",
                'reward_creds'       => 100,
                'reward_tech_points' => 1,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'DT-hub',
                'minigame_type'      => null,
            ],
        ],

        // ── Quest 3: The Drift-Anchor Retrieval ───────────────────────────────────
        // Contractor: Float | District: Spokane Valley | Target: SV-v9
        // ─────────────────────────────────────────────────────────────────────────
        'SV-hub|1' => [
            [
                'stage_number'       => 1,
                'title'              => 'Find Float',
                'objective_text'     => "Veil's lead points to Spokane Valley. Your deck is running at critical temperature — whatever is inside your system isn't just leaking anymore, it's vibrating. Every few seconds your HUD stutters, your avatar rubber-bands, and you lose a full second of movement.\n\nGet to the SV-Hub before your rig gives out entirely.",
                'rep_reward'         => 0,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'SV-hub',
                'minigame_type'      => null,
            ],
            [
                'stage_number'       => 2,
                'title'              => 'Soak the Drift-Anchor',
                'objective_text'     => "Float doesn't want to fix you. She wants to harvest what's rotting inside you. There's a data-sink at node SV-v9 — a submerged relay where the grid dumps its most volatile, discarded processes. Float needs what's pooled there.\n\nGet to SV-v9 and hold position. Let your system absorb the toxic data until the anchor is full.\n\n[WARNING] — The Drift is actively rewriting anything it touches. Your stability will drain continuously. You cannot fight it — you can only endure it.",
                'rep_reward'         => 0,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'SV-v9',
                'minigame_type'      => 'toxic_soak',
            ],
            [
                'stage_number'       => 3,
                'title'              => 'Report Back',
                'objective_text'     => "You're still standing. Barely. Get back to Float at the SV-Hub and let her drain what you're carrying.",
                'rep_reward'         => 25,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => 'UD-hub',
                'referral_text'      => "Float: \"That data you soaked — it's old. Pre-collapse architecture, compressed into something that shouldn't exist. There's someone at the University who collects things like this. AXIOM. They'll pay for a look at what's left in you.\"",
                'reward_creds'       => 100,
                'reward_tech_points' => 1,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'SV-hub',
                'minigame_type'      => null,
            ],
        ],

        // ── Quest 4: The Deep Archive Extraction ──────────────────────────────────
        // Contractor: Axiom | District: University District | Target: UD-v17
        // ─────────────────────────────────────────────────────────────────────────
        'UD-hub|1' => [
            [
                'stage_number'       => 1,
                'title'              => 'Find Axiom',
                'objective_text'     => "Float's lead puts you in the University District. You don't remember the transit — your system dropped out somewhere between the Valley and the campus and when your HUD rebooted you were already here.\n\nFind the UD-Hub. Axiom has apparently been tracking your signature. They already know what you're carrying.",
                'rep_reward'         => 0,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'UD-hub',
                'minigame_type'      => null,
            ],
            [
                'stage_number'       => 2,
                'title'              => 'Extract the Archive Packet',
                'objective_text'     => "Axiom needs a deep-packet from the University Archives at node UD-v17. The archive is locked behind the best ICE in the city — too clean, too precise for any normal operator to touch without triggering a cascade failure.\n\nYour rig is so corrupted that the ICE can't classify you. You read as background noise. System debris.\n\nGet to UD-v17 and extract the packet.\n\n[WARNING] — Whatever is inside your system will try to generate false alarms while you work. The ICE will spike. Your own security layer will scream at you. Treat it as background noise — it isn't real.",
                'rep_reward'         => 0,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'UD-v17',
                'minigame_type'      => 'archive_extraction',
            ],
            [
                'stage_number'       => 3,
                'title'              => 'Report Back',
                'objective_text'     => "The packet is yours. The data is clean. Your processor is not.\n\nGet back to Axiom at the UD-Hub and deliver what they asked for.",
                'rep_reward'         => 25,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => 'NS-hub',
                'referral_text'      => "Axiom: \"The packet is older than the current architecture. That data should not exist in any active system — including yours. There is a technician in the Under-Grid, North Spokane. Goes by Patch. They will already know you are coming.\"",
                'reward_creds'       => 100,
                'reward_tech_points' => 1,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'UD-hub',
                'minigame_type'      => null,
            ],
        ],

        // ── Quest 5: The Ghost-Kernel Calibration ─────────────────────────────────
        // Contractor: Patch | District: North Spokane | Target: NS-v13
        // ─────────────────────────────────────────────────────────────────────────
        'NS-hub|1' => [
            [
                'stage_number'       => 1,
                'title'              => 'Find Patch',
                'objective_text'     => "Axiom's lead puts you in North Spokane. Your deck is barely functional. The virus is forcing a recursive loop, burning cycles trying to lead you somewhere specific — somewhere it recognises.\n\nGet to the NS-Hub. Patch communicates through a shielded remote terminal. They won't let you inside.",
                'rep_reward'         => 0,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'NS-hub',
                'minigame_type'      => null,
            ],
            [
                'stage_number'       => 2,
                'title'              => 'Haul the Sub-Routines',
                'objective_text'     => "Patch won't touch your rig — you're too dangerous to bring inside. But they have work that's equally dangerous: a cache of volatile sub-routines at node NS-v13, code so unstable it actively fights to overwrite anything it touches.\n\nGet to NS-v13 and move the sub-routines to Patch's drop-box. You carry them because you're the only thing in this city broken enough to not notice the difference.\n\n[WARNING] — These sub-routines will fight back. Every packet in your buffer will actively drain your stability. The faster you move them, the less damage they do.",
                'rep_reward'         => 0,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'NS-v13',
                'minigame_type'      => 'calibration_tether',
            ],
            [
                'stage_number'       => 3,
                'title'              => 'Collect Your Cut',
                'objective_text'     => "The sub-routines are in the drop-box. Patch has already severed the connection — you aren't getting a debrief, just the credits.\n\nReturn to the NS-Hub terminal and collect your payout.\n\nYou've run the full circuit. Five docs. Five jobs. You're more broken than when you started, and you still have no idea what's living in your system. But whatever it is — it's been with you since the beginning. And it's still trying to teach you something.",
                'rep_reward'         => 25,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 100,
                'reward_tech_points' => 1,
                'reward_node_access' => null,
                'reward_lore_key'    => 'prologue_complete',
                'node_canvas_id'     => 'NS-hub',
                'minigame_type'      => null,
            ],
        ],

    ];

    public function run(): void
    {
        foreach (self::STAGES as $key => $stages) {
            [$canvasId, $arcOrder] = explode('|', $key);

            $node = Node::where('canvas_id', $canvasId)->first();
            if ($node === null) {
                $this->command?->warn("QuestStageSeeder: node '{$canvasId}' not found — skipped.");
                continue;
            }

            $doc = CyberDoc::where('node_id', $node->id)->first();
            if ($doc === null) {
                $this->command?->warn("QuestStageSeeder: CyberDoc at '{$canvasId}' not found — skipped.");
                continue;
            }

            $arc = QuestArc::where('cyber_doc_id', $doc->id)
                ->where('sequence_order', (int) $arcOrder)
                ->first();
            if ($arc === null) {
                $this->command?->warn("QuestStageSeeder: Arc {$arcOrder} for '{$canvasId}' not found — run QuestArcSeeder first.");
                continue;
            }

            foreach ($stages as $stageData) {
                // Resolve referral doc by canvas_id if provided
                $referralDocId = null;
                if ($stageData['referral_canvas_id']) {
                    $refNode       = Node::where('canvas_id', $stageData['referral_canvas_id'])->first();
                    $refDoc        = $refNode ? CyberDoc::where('node_id', $refNode->id)->first() : null;
                    $referralDocId = $refDoc?->id;
                }

                // Resolve branch_options canvas_ids to cyber_doc UUIDs
                $branchOptions = null;
                if ($stageData['branch_options']) {
                    $branchOptions = array_map(function ($opt) {
                        $refNode  = Node::where('canvas_id', $opt['canvas_id'])->first();
                        $refDoc   = $refNode ? CyberDoc::where('node_id', $refNode->id)->first() : null;
                        return [
                            'cyber_doc_id' => $refDoc?->id,
                            'label'        => $opt['label'],
                            'rep_reward'   => $opt['rep_reward'],
                        ];
                    }, $stageData['branch_options']);
                }

                QuestStage::updateOrCreate(
                    [
                        'quest_arc_id' => $arc->id,
                        'stage_number' => $stageData['stage_number'],
                    ],
                    [
                        'title'               => $stageData['title'],
                        'objective_text'      => $stageData['objective_text'],
                        'rep_reward'          => $stageData['rep_reward'],
                        'is_branch'           => $stageData['is_branch'],
                        'branch_options'      => $branchOptions,
                        'referral_doc_id'     => $referralDocId,
                        'referral_text'       => $stageData['referral_text'],
                        'reward_creds'        => $stageData['reward_creds']       ?? 0,
                        'reward_tech_points'  => $stageData['reward_tech_points'] ?? 0,
                        'reward_node_access'  => $stageData['reward_node_access'] ?? null,
                        'reward_lore_key'     => $stageData['reward_lore_key']    ?? null,
                        'node_canvas_id'      => $stageData['node_canvas_id']     ?? null,
                        'minigame_type'       => $stageData['minigame_type']      ?? null,
                    ],
                );
            }
        }
    }
}
