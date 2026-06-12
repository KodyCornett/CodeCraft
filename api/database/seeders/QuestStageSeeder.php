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
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'text' => "The med-wagon smells like solder and old rain. Knuckle doesn't look up when you walk in — he's already watching your rig's signal bleed across a cracked diagnostic panel bolted to the far wall. Numbers cascade in red. He doesn't seem surprised."],
                    ['speaker' => 'KNUCKLE',  'text' => "Close the door. Don't talk yet.\n\nYour deck is throwing noise all over my bandwidth."],
                    ['speaker' => 'NARRATOR', 'text' => "He runs a portable scanner slowly across your rig housing. The readout floods. He says nothing for a long moment. Sets the scanner down. Lights a burner off the edge of his workbench."],
                    ['speaker' => 'KNUCKLE',  'text' => "That patch didn't come from any doc I know. The architecture's wrong — it's old. Pre-collapse framework, compressed into something that shouldn't fit inside a modern rig.\n\nAnd it's not finished. Whatever got into your system is still... settling in."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'EXHAUSTED', 'text' => "Just tell me how to get it out."],
                        ['tone' => 'COLD',      'text' => "Is it going to kill me?"],
                        ['tone' => 'PANICKED',  'text' => "What do you mean it's not finished?"],
                    ]],
                    ['speaker' => 'KNUCKLE', 'text' => "You can't get it out. Not here, not with anything I have. And I've been doing this a long time.\n\nHere's what I can tell you: it's not hostile. Not to you, anyway. Whatever it is, it came in deliberate. Someone wrote this specifically to sit inside a runner's rig without tearing it apart.\n\nMy advice? Keep moving. Keep working. I've seen corruption like this go dormant in runners who went quiet. You don't want that — dormant means it's waiting for something. Active means it's still deciding.\n\nI've got a job. Nothing complicated. You run it, I keep the diagnostics up and tell you what I find. Deal?"],
                ],
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
                'dialogue'           => null,
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
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'text' => "The block's heat signatures are already climbing on Knuckle's monitor when you walk back in. He doesn't acknowledge it. He's already looking at your rig readings."],
                    ['speaker' => 'KNUCKLE', 'text' => "It spiked twice while you were at the node. Whatever's in you reacted to the grid interference. It's not fighting the work — it's interested in it.\n\nI don't know what that means yet. But it's something."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'FLAT',      'text' => "Credits. Then I'm gone."],
                        ['tone' => 'UNCERTAIN', 'text' => "How long before you know more?"],
                        ['tone' => 'TIRED',     'text' => "I just want one day where my rig isn't on fire."],
                    ]],
                    ['speaker' => 'KNUCKLE', 'text' => "You're still leaking noise. There's a specialist Downtown — goes by Veil. She handles signal corruption that's above my pay grade.\n\nDon't mention my name. She doesn't do referrals. Show her what your rig's throwing off and let her decide if she's interested.\n\nYou'll know if she is."],
                ],
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
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'text' => "Her parlour is a converted sub-level office — no windows, no signage. The air smells like ionised copper. Veil doesn't greet you. She's studying the readout from a passive scanner mounted above the doorframe, watching your signature leak into her space."],
                    ['speaker' => 'VEIL', 'text' => "I know what you're carrying. Knuckle ran the diagnostics but he doesn't have the vocabulary for it. He sees the damage. I see the architecture.\n\nSit down."],
                    ['speaker' => 'NARRATOR', 'text' => "She pulls up a layered signal map — your rig's output rendered as geometry. The corruption doesn't look like noise from here. It looks deliberate. Structural."],
                    ['speaker' => 'VEIL', 'text' => "Whatever was installed in your system isn't malware. Malware destroys. This is using your rig as infrastructure. It's built a relay inside you — routing something through your processes without your hardware even noticing the draw.\n\nI can't remove it. But I can work around it. I need a favour first."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'GUARDED',   'text' => "What kind of favour?"],
                        ['tone' => 'RESIGNED',  'text' => "Of course you do."],
                        ['tone' => 'DIRECT',    'text' => "Tell me what's in me first."],
                    ]],
                    ['speaker' => 'VEIL', 'text' => "There's a ghost-signal at a node Downtown. Recursive loop — it's been degrading the grid infrastructure for weeks and nobody can get close enough to kill it without their rig catching the feedback.\n\nYours won't. The thing inside you is already running a similar loop on a deeper layer. The ghost-signal will recognise it as kin and let you in close enough to flush it.\n\nDo that for me and I'll tell you everything I know about what's living in your system."],
                ],
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
                'dialogue'           => null,
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
                'dialogue'           => [
                    ['speaker' => 'VEIL', 'text' => "I watched the node stabilise from here. The ghost-signal didn't fight you — it opened up. That confirms what I suspected.\n\nWhat's in your system isn't a virus. It's a key. Someone used your rig as a carrier medium to move a piece of pre-collapse architecture through the current grid without triggering the ICE filters. They needed something broken enough to look like noise.\n\nThey needed you specifically."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'UNSETTLED', 'text' => "Who knew I was going to be running the Frequency?"],
                        ['tone' => 'FOCUSED',   'text' => "What does the key open?"],
                        ['tone' => 'TIRED',     'text' => "I just want to know if I'm going to survive this."],
                    ]],
                    ['speaker' => 'VEIL', 'text' => "I don't know what it opens. The data you cleared was pre-collapse — the same era as what's inside you. There's a salvager in Spokane Valley. Goes by Float. She deals in exactly this kind of architecture.\n\nWhether she knows what it means is her problem. Whether you trust her with it is yours."],
                ],
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
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'text' => "Float's repair bay is a converted shipping container on the edge of the Valley grid, half-buried in a chain-link compound. She's under a raised panel unit when you arrive, one arm elbow-deep in the chassis. She doesn't come out."],
                    ['speaker' => 'FLOAT', 'text' => "I know why you're here. Veil sent a signal ahead. Said you were carrying something old.\n\nHand me the torque driver. The flat one. Don't touch anything else."],
                    ['speaker' => 'NARRATOR', 'text' => "She emerges eventually, wiping her hands on a rag that's already past the point of usefulness. Her eyes go immediately to your rig. She studies it the way a salvager studies a wreck — not looking for damage, looking for value."],
                    ['speaker' => 'FLOAT', 'text' => "Pre-collapse architecture running inside a live rig. On the Frequency. In this district.\n\nI've seen fragments — bits of it pulled from dead nodes, compressed into storage media that won't interface with modern hardware. Never seen it active. Never seen it hosted.\n\nYou want to know what it is or you want to know what it's worth?"],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'CAREFUL',   'text' => "What it is. Start there."],
                        ['tone' => 'PRAGMATIC', 'text' => "Both. In that order."],
                        ['tone' => 'BLUNT',     'text' => "I want it out of me."],
                    ]],
                    ['speaker' => 'FLOAT', 'text' => "Can't get it out. That's not how this architecture works — it doesn't sit on top of a system, it integrates. Give it long enough and you won't be able to tell where it ends and your rig begins.\n\nBut I can read it. Not here — I need data from a sink node first. There's a place in the Valley where the grid dumps its volatile processes. What pools there reacts to this kind of old code. You soak it, I can decode the signatures.\n\nIt'll cost you something. Not credits."],
                ],
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
                'dialogue'           => null,
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
                'dialogue'           => [
                    ['speaker' => 'FLOAT', 'text' => "You held longer than I expected.\n\nSit down before you fall down."],
                    ['speaker' => 'NARRATOR', 'text' => "She runs the readout twice. Doesn't explain what she's seeing. The silence isn't unfriendly — it's the kind of quiet that means something is actually interesting."],
                    ['speaker' => 'FLOAT', 'text' => "The data you soaked — it reacted to what's in you. Bonded to it. That's not a coincidence. Whatever is running inside your rig has a specific affinity for this era of architecture. Like it's looking for something.\n\nThere's someone at the University District who catalogues pre-collapse systems. Goes by Axiom. They'll want to see what you're carrying — and they'll have better answers than me."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'GRATEFUL',  'text' => "Thank you. I mean it."],
                        ['tone' => 'WORN OUT',  'text' => "How many more people do I have to see?"],
                        ['tone' => 'RESOLUTE',  'text' => "I'll find Axiom."],
                    ]],
                    ['speaker' => 'FLOAT', 'text' => "As many as it takes. You're carrying something that shouldn't exist anymore.\n\nThat either ends very badly or very interestingly. My money's on both."],
                ],
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
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'text' => "Axiom Systems occupies a clean sub-level space beneath the University grid infrastructure. No clutter. No visible hardware. Just white surfaces, indirect light, and a terminal interface so precise it makes your rig feel like a salvage wreck by comparison.\n\nAxiom doesn't introduce themselves. They're already reading your output on a display you can't see."],
                    ['speaker' => 'AXIOM', 'text' => "You are leaking 4.3 terabytes of uncompressed pre-collapse data per hour. You have been doing this for approximately nine days.\n\nSit down. You are making my instruments anxious."],
                    ['speaker' => 'NARRATOR', 'text' => "They pull up a structural rendering of your rig's current state. The corruption doesn't look like corruption from here. It looks like an installation — deliberate, layered, patient."],
                    ['speaker' => 'AXIOM', 'text' => "The architecture inside your system predates the Frequency by eleven years. It should not be functional in a current-gen environment. The fact that it is suggests someone spent considerable time adapting it.\n\nI have been searching for active instances of this code for three years. You walked in carrying one.\n\nI require something from the University Archives in exchange for what I know. This is not negotiable."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'CURIOUS',   'text' => "What do you know?"],
                        ['tone' => 'CAUTIOUS',  'text' => "What's in the Archives?"],
                        ['tone' => 'RESIGNED',  'text' => "Fine. What do you need?"],
                    ]],
                    ['speaker' => 'AXIOM', 'text' => "The Archives contain a data packet from the original SPLICE construction logs. The ICE protecting it was written to stop clean systems — systems with legible, classifiable signatures.\n\nYour rig reads as debris. The ICE will not recognise you as a threat.\n\nRetrieve the packet and I will tell you what is inside you, where it came from, and what it is looking for."],
                ],
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
                'dialogue'           => null,
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
                'dialogue'           => [
                    ['speaker' => 'AXIOM', 'text' => "The packet is intact. Thank you.\n\nWhat you are carrying is called a Ghost-Kernel. It is not a virus. It is not malware. It is a person.\n\nNot a full person — a compressed instance. A cognitive framework, preserved in pre-collapse architecture and adapted to run inside a host rig without that host's knowledge or consent. Someone's consciousness, or a significant fragment of it, is running inside your system."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'DISTURBED', 'text' => "Whose consciousness?"],
                        ['tone' => 'FOCUSED',   'text' => "How do I get it out without killing it?"],
                        ['tone' => 'SHAKEN',    'text' => "It's been in there since the beginning?"],
                    ]],
                    ['speaker' => 'AXIOM', 'text' => "I don't know whose. The packet will take time to decode. What I can tell you is this: Ghost-Kernels don't travel passively. Someone loaded it onto the Frequency and aimed it at you. That targeting was deliberate.\n\nThere is a technician in North Spokane. Goes by Patch. They work in the Under-Grid — infrastructure layer, below the public Frequency. They will know how to isolate and communicate with what is inside you.\n\nThey will already know you are coming. They always do."],
                ],
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
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'text' => "The NS-Hub terminal is a grille-covered access panel mounted into a concrete wall at the edge of the Under-Grid maintenance corridor. There is no door. No signage. A single line of green text cycles on the screen:\n\n> AWAITING INPUT"],
                    ['speaker' => 'PATCH', 'text' => "> You took longer than I expected.\n> Your rig is broadcasting on seventeen simultaneous frequencies. You have been for eight days.\n> I have been listening to all of them."],
                    ['speaker' => 'NARRATOR', 'text' => "There is a long pause. More text appears."],
                    ['speaker' => 'PATCH', 'text' => "> The Ghost-Kernel inside your system has been trying to establish a handshake with the Under-Grid infrastructure since it activated. It is using your rig as a bridge.\n> It is looking for a specific node. One that does not appear on any current grid map.\n> I know where it is.\n> I will not tell you for free."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'WARY',      'text' => "What do you want?"],
                        ['tone' => 'DIRECT',    'text' => "Tell me about the node first."],
                        ['tone' => 'EXHAUSTED', 'text' => "Everyone wants something. Fine."],
                    ]],
                    ['speaker' => 'PATCH', 'text' => "> There is a cache of volatile sub-routines at NS-v13. Unstable code — it fights anything clean that touches it. I cannot retrieve it safely.\n> You can. Whatever is inside you is too broken to register as a threat. The sub-routines will let you carry them.\n> Move them to my drop-box. I will tell you everything I know about the node the Kernel is searching for.\n> Do not read the sub-routines while you carry them. Do not open the packets. Move them and nothing else.\n> The Kernel will try to help you. Let it."],
                ],
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
                'dialogue'           => null,
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
                'dialogue'           => [
                    ['speaker' => 'PATCH', 'text' => "> Sub-routines received. Intact.\n> The node the Kernel is searching for is called ORIGIN_NULL. It predates the Frequency by eleven years. It was the first node ever connected to what would become SPLICE — a test relay that was officially decommissioned and scrubbed from the public grid index.\n> It was not actually scrubbed.\n> The Ghost-Kernel was written at ORIGIN_NULL. Whoever preserved it intended it to find its way back.\n> I do not know why. I do not know who.\n> But the Kernel does.\n> You just have to ask it."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'RESOLVED',  'text' => "I'll find ORIGIN_NULL."],
                        ['tone' => 'UNCERTAIN', 'text' => "How do I ask a piece of compressed consciousness something?"],
                        ['tone' => 'QUIET',     'text' => "I think I already know the answer."],
                    ]],
                    ['speaker' => 'PATCH', 'text' => "> Your credits have been transferred.\n> One more thing.\n> The Kernel has been broadcasting on the Watcher channel since it activated. If you have not been reading those signals — read them.\n> It has been trying to tell you something since the beginning.\n> Connection terminated."],
                ],
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
                        'dialogue'            => isset($stageData['dialogue']) ? json_encode($stageData['dialogue']) : null,
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
