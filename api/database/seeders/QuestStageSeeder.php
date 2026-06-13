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
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/knuckle/k_s1_l1.mp3', 'text' => "Knuckle's node renders as a cramped, low-ceiling space — walls patched together from stolen network architecture, the seams still visible where different systems were forced to talk to each other. Medical readouts float at chest height, most of them running amber. His avatar is a hulking asymmetrical frame, shoulders built wide enough to suggest he wrote them that way deliberately — two floating diagnostic arms extending from the torso like a surgeon who got tired of only having two hands. He doesn't look up when you walk in. One of the arms is already pointed at you, pulling your signal before you've said a word. The readout it throws onto the wall comes back red. He still doesn't look up."],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/k_s1_l2.mp3',  'text' => "Close the door. Don't talk yet.\n\nYour deck is throwing noise all over my bandwidth."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/knuckle/k_s1_l3.mp3', 'text' => "He reaches into a rendered equipment rack — the kind of node clutter that takes years to accumulate, layer over layer of tools that were never cleaned up — and pulls a handheld scanner into one of his diagnostic arms. He runs it along your rig housing slowly. In SPLICE, the scan looks like a wound assessment: your architecture lights up in segments and most of them come back wrong. The other diagnostic arm catches the readout mid-air and holds it open where he can study it without moving his head. He stays like that for a long moment. Then he sets the scanner down, collapses the readout with a gesture, and a burner cigarette materializes between two of his fingers — a personal touch someone went to the trouble of scripting into his avatar. He takes a long drag. The smoke even renders."],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/k_s1_l4.mp3',  'text' => "That patch didn't come from any doc I know. The architecture's wrong — it's old. Pre-collapse framework, compressed into something that shouldn't fit inside a modern rig.\n\nAnd it's not finished. Whatever got into your system is still... settling in."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'EXHAUSTED', 'text' => "Just tell me how to get it out."],
                        ['tone' => 'COLD',      'text' => "Is it going to kill me?"],
                        ['tone' => 'PANICKED',  'text' => "What do you mean it's not finished?"],
                    ]],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/knuckle/k_s1_l5.mp3', 'text' => "He's not looking at you. He's watching the smoke dissolve into the node's recycled air."],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/k_s1_l6.mp3',  'text' => "You can't get it out. Not here, not with anything I have. And I've been doing this a long time.\n\nHere's what I can tell you: it's not hostile. Not to you, anyway. Whatever it is, it came in deliberate. Someone wrote this specifically to sit inside a runner's rig without tearing it apart.\n\nMy advice? Keep moving. Keep working. I've seen corruption like this go dormant in runners who went quiet. You don't want that — dormant means it's waiting for something. Active means it's still deciding.\n\nI've got a job. Nothing complicated. You run it, I keep the diagnostics up and tell you what I find. Deal?"],
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
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/knuckle/k_s3_l1.mp3', 'text' => "The block's heat signatures are already climbing on a wall display when you step back into the node — a dozen residential units rendered as thermal columns, all of them ticking upward in slow green increments. Knuckle isn't looking at it. One of his diagnostic arms is already extended in your direction before the door geometry finishes loading behind you, scanner live, pulling your rig's signal the moment you're in range. He reads the output without a word. The arm retracts."],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/k_s3_l2.mp3',  'text' => "It spiked twice while you were at the node. Whatever's in you reacted to the grid interference. It's not fighting the work — it's interested in it.\n\nI don't know what that means yet. But it's something."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'FLAT',      'text' => "Credits. Then I'm gone."],
                        ['tone' => 'UNCERTAIN', 'text' => "How long before you know more?"],
                        ['tone' => 'TIRED',     'text' => "I just want one day where my rig isn't on fire."],
                    ]],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/knuckle/k_s3_l3.mp3', 'text' => "A transfer chip materializes on the bench between you — the standard end of a clean transaction in his node. No ceremony. One of the diagnostic arms nudges it forward an inch, like even that much is more than the job deserved."],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/k_s3_l4.mp3',  'text' => "Look, I ran the diagnostics twice. There's no trace data — nothing I can pull that tells me what's sitting in you or where it came from. Without that I'm just guessing, and I don't get paid to guess.\n\nYou did the work. I paid you. That's the end of it.\n\nNow get out of my wagon. Your rig's been screaming on every frequency since you walked in and the last thing I need is the Architects sniffing around this node because you can't keep your signal quiet."],
                ],
                'rep_reward'         => 25,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => 'DT-hub',
                'referral_text'      => "[ SPLICE ] — unknown process intercepted on session exit — signal fragment recovered — keyword: DOWNTOWN // VEIL",
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
                'objective_text'     => "The signal hit your system the moment you left BA-hub — the same corrupted architecture that's been living in your rig, resolving this time to two words: Downtown. Veil.\n\nGet to the DT-Hub. If she's already tracking your trace — and she probably is — she already knows you're coming.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/veil/v_s1_l1.mp3', 'text' => "Veil's node renders as a quiet, sprawling workspace — warm lights hanging above exposed conduits and maintenance terminals, every surface occupied by active projects and half-finished repairs. Massive windows overlook the distant glow of the Frequency, rain drifting lazily against the glass while status boards and infrastructure maps float in the air like constellations. Nothing here is decorative. Every cable is labeled. Every tool has a place. The entire node carries the strange feeling of a station that was supposed to close years ago, but never did.\n\nHer avatar stands among it all with the same quiet practicality. A woman somewhere between young and old, dark hair loosely tied back, wearing a long coat lined with pockets and utility straps instead of armor. The few visible augmentations beneath her skin are subtle and functional, easy to miss unless you know what to look for. She isn't watching the doorway when you arrive. She's watching six different terminals at once, fingers moving through diagnostic windows while old requests collapse and new ones appear faster than they disappear.\n\nShe notices you immediately.\n\nShe just doesn't stop working.\n\nOne of the displays briefly shifts to your signal. Something in the results catches her attention. Her eyes linger on it for half a second longer than they should.\n\nThen she quietly reaches over, mutes an alarm somewhere out in the district, and finally looks up.\n\nNot startled.\n\nNot concerned.\n\nJust tired.\n\nAs though strange problems stopped surprising her a very long time ago."],
                    ['speaker' => 'VEIL',    'audio' => 'veil/v_s1_l2.mp3', 'text' => "Hm.\n\nThat's unusual.\n\nSit down."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/veil/v_s1_l3.mp3', 'text' => "Another alarm flashes amber beside her. She dismisses it with a flick of her hand before giving you her full attention. A layered geometry unfolds between you — your rig rendered as structure instead of circuitry. From here, the corruption doesn't look random.\n\nIt looks built."],
                    ['speaker' => 'VEIL',    'audio' => 'veil/v_s1_l4.mp3', 'text' => "Most failures are ugly.\n\nThis isn't ugly.\n\nSomebody spent a great deal of time teaching this thing how to behave.\n\nWhich means either I'm looking at something brilliant...\n\nOr something dangerous."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'GUARDED',   'text' => "Can you remove it?"],
                        ['tone' => 'IRRITATED', 'text' => "Everybody keeps saying that."],
                        ['tone' => 'DIRECT',    'text' => "What's it doing?"],
                    ]],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/veil/v_s1_l5.mp3', 'text' => "She doesn't answer immediately. Another request appears. Another disappears. She watches your architecture while her hands continue working."],
                    ['speaker' => 'VEIL',    'audio' => 'veil/v_s1_l6.mp3', 'text' => "I don't know.\n\nWhich means I don't touch it.\n\nGuessing breaks things.\n\nBut there is something I'd like to test."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/veil/v_s1_l7.mp3', 'text' => "A section of Downtown unfolds between you. One node glows red."],
                    ['speaker' => 'VEIL',    'audio' => 'veil/v_s1_l8.mp3', 'text' => "Recursive ghost-signal.\n\nBeen degrading infrastructure for weeks.\n\nEvery runner I've sent near it cooked their deck.\n\nYours...\n\nProbably won't.\n\nSimilar structures tend to tolerate each other.\n\nFlush it.\n\nThen we'll both know something."],
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
                'objective_text'     => "Veil's interested in your corruption, but not enough to gamble on it. A recursive ghost-signal at node DT-v8 has been degrading local infrastructure for weeks. Clean rigs don't survive the feedback.\n\nYours isn't clean.\n\nGet to DT-v8 and deploy FLUSH_BUFFER.\n\n[WARNING] — Your stability will drop rapidly. Keep the signal contained.\n\nDon't inspect the data.\n\nJust flush it.",
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
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/veil/v_s3_l1.mp3', 'text' => "The status boards above Veil's workstation are running green when you step back into the node. One district map has vanished entirely. Another queue has shortened. Somewhere, a problem has stopped being a problem.\n\nVeil notices your signal immediately.\n\nThis time, she looks up before you say anything."],
                    ['speaker' => 'VEIL',    'audio' => 'veil/v_s3_l2.mp3', 'text' => "The node stabilized.\n\nNo resistance.\n\nNo feedback.\n\nIt just...\n\nopened."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/veil/v_s3_l3.mp3', 'text' => "She doesn't sound pleased."],
                    ['speaker' => 'VEIL',    'audio' => 'veil/v_s3_l4.mp3', 'text' => "Which means I was wrong.\n\nI thought whatever's inside you resembled the loop.\n\nIt doesn't.\n\nThe loop recognized it."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'UNSETTLED', 'text' => "Recognized what?"],
                        ['tone' => 'FOCUSED',   'text' => "So what does that mean?"],
                        ['tone' => 'TIRED',     'text' => "Am I dying or not?"],
                    ]],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/veil/v_s3_l5.mp3', 'text' => "A layered model of your architecture unfolds between you again. Veil studies it in silence. One hand moves automatically, dismissing an amber alert before it can become a red one."],
                    ['speaker' => 'VEIL',    'audio' => 'veil/v_s3_l6.mp3', 'text' => "I don't know.\n\nAnd I don't guess.\n\nGuessing breaks things.\n\nWhatever's in you...\n\nIt isn't random.\n\nIt isn't damaged.\n\nAnd it isn't finished."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/veil/v_s3_l7.mp3', 'text' => "The last sentence seems to bother her more than the others."],
                    ['speaker' => 'VEIL',    'audio' => 'veil/v_s3_l8.mp3', 'text' => "That's all I've got."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/veil/v_s3_l9.mp3', 'text' => "Another request appears.\n\nAnother alarm follows.\n\nSomewhere in the district, something else needs attention."],
                    ['speaker' => 'VEIL',    'audio' => 'veil/v_s3_l10.mp3', 'text' => "Sorry."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/veil/v_s3_l11.mp3', 'text' => "She doesn't sound apologetic.\n\nJust tired."],
                    ['speaker' => 'VEIL',    'audio' => 'veil/v_s3_l12.mp3', 'text' => "I'd rather give you an answer.\n\nBut I'd rather give you the right answer.\n\nAnd I don't have one."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/veil/v_s3_l13.mp3', 'text' => "One of the status boards flashes red. Veil is already reaching for it."],
                    ['speaker' => 'VEIL',    'audio' => 'veil/v_s3_l14.mp3', 'text' => "If it changes...\n\nYou'll know before I do.\n\nTry not to break anything on your way out."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/veil/v_s3_l15.mp3', 'text' => "And just like that, you're no longer the most urgent thing in the room.\n\nVeil has already returned to keeping the lights on."],
                ],
                'rep_reward'         => 25,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => 'SV-hub',
                'referral_text'      => "[ SPLICE ] — unknown process intercepted on session exit — signal fragment recovered — keyword: SPOKANE VALLEY // FLOAT",
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
                'referral_text'      => "[ SPLICE ] — unknown process intercepted on session exit — signal fragment recovered — keyword: UNIVERSITY DISTRICT // AXIOM",
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
                'referral_text'      => "[ SPLICE ] — unknown process intercepted on session exit — signal fragment recovered — keyword: NORTH SPOKANE // PATCH",
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
