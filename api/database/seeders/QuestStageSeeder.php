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
                        ['tone' => 'COLD', 'text' => "Is it going to kill me?"],
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
                'field_comms'        => [
                    ['speaker' => 'doc', 'text' => "Diagnostics are live on my end. Don't get cute out there — that governor's got teeth."],
                    ['speaker' => 'doc', 'text' => "...that's odd. Your rig just spiked. Wasn't the node — that was you."],
                    ['speaker' => 'doc', 'text' => "Keep working. I'll flag it if it gets worse."],
                    ['speaker' => 'doc', 'text' => "Strip the layer and get out. We'll talk about the rest back here."],
                    ['speaker' => 'player', 'text' => "Copy. Finishing the strip — we'll figure out the rest after."],
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
                        ['tone' => 'UNCERTAIN', 'text' => "How long before you know more?"],
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
                        ['tone' => 'DIRECT', 'text' => "What's it doing?"],
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
                'objective_text'     => "Another fragment hit your system leaving Downtown. Same source. Same architecture. This time it resolved into something cleaner: Spokane Valley. Float.\n\nYour deck is running at critical temperature — whatever is inside your system isn't just leaking anymore, it's vibrating. Every few seconds your HUD stutters, your avatar rubber-bands, and you lose a full second of movement.\n\nGet to the SV-Hub before your rig gives out entirely.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s1_l1.mp3', 'text' => "Float's repair bay renders larger on the inside than it has any right to be — shipping containers stacked inside shipping containers, stitched together into architecture that stopped making sense years ago. Every surface is covered in handwritten labels and private shorthand that only means something to her. Hardware that hasn't been manufactured in over a decade hangs beside active systems she's somehow convinced to keep running.\n\nNothing matches.\n\nEverything belongs.\n\nIt doesn't feel like a workshop.\n\nIt feels like a place where forgotten things come to survive.\n\nHer avatar is compact and practical, dressed in worn work gear without a single decorative component. Half a dozen AR overlays drift around her constantly, silently cataloguing the room. A panel she's halfway through dismantling rests on the bench beside her.\n\nShe notices your signal before you've finished loading in.\n\nShe notices the architecture.\n\nThen she notices you.\n\nNot necessarily in that order."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s1_l2.mp3', 'text' => "Don't touch anything.\n\nI mean it."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s1_l3.mp3', 'text' => "One of the overlays drifts toward you and stops.\n\nShe glances at the readout.\n\nThen slowly sets down the tool in her hand.\n\nThe kind of slow people reserve for discovering something they weren't expecting."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s1_l4.mp3', 'text' => "Pre-collapse architecture...\n\nAnd it's running...\n\nInside a live rig."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s1_l5.mp3', 'text' => "One of the overlays shifts. Float stares at the readout for another second."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s1_l6.mp3', 'text' => "No.\n\nNo, that doesn't make any sense."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'CAREFUL', 'text' => "What doesn't make sense?"],
                    ]],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s1_l7.mp3', 'text' => "She spreads the architecture apart with both hands. Layers unfold across the room. Her expression narrows with the focused quiet of someone reading a language nobody speaks anymore."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s1_l8.mp3', 'text' => "I don't know what I'm looking at.\n\nAnd I really don't like saying that.\n\nThis should've crashed.\n\nOr fragmented.\n\nOr eaten your deck."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s1_l9.mp3', 'text' => "She rearranges three overlays, studies the results, then rearranges them again."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s1_l10.mp3', 'text' => "Systems don't behave this well by accident."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'DIRECT', 'text' => "Can you remove it?"],
                    ]],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s1_l11.mp3', 'text' => "She doesn't answer immediately. One of the overlays expands and collapses again as she studies the architecture from another angle."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s1_l12.mp3', 'text' => "No.\n\nNot because I can't.\n\nBecause I don't know what \"it\" is."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s1_l13.mp3', 'text' => "She runs the analysis again. Then a third time."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s1_l14.mp3', 'text' => "I can't tell where it's drawing power from.\n\nWhich should be impossible.\n\nI can't tell where it's storing itself.\n\nWhich should also be impossible.\n\nAnd I can't figure out why your rig isn't screaming louder."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s1_l15.mp3', 'text' => "She sighs quietly and folds one of the overlays shut."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s1_l16.mp3', 'text' => "Which is annoying."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s1_l17.mp3', 'text' => "She returns to the panel she'd been dismantling and removes another piece while she thinks."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s1_l18.mp3', 'text' => "There's a sink node out in the Valley.\n\nGrid dumps things there when it can't decide whether they're important.\n\nOld architecture tends to collect.\n\nMaybe it'll react.\n\nMaybe I'll learn something."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s1_l19.mp3', 'text' => "She shrugs."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s1_l20.mp3', 'text' => "Or maybe reality embarrasses me again.\n\nWouldn't be the first time.\n\nIt'll cost you something.\n\nNot credits."],
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
                'title'              => 'Soak the Sink',
                'objective_text'     => "Float isn't trying to fix you. She's trying to understand you.\n\nThere's a sink node in Spokane Valley where the grid dumps volatile processes it can't classify, contain, or delete. Strange things collect there. Old things.\n\nGet to SV-v9 and expose your rig to the pool.\n\n[WARNING] — Your architecture will react unpredictably. Stability loss will be severe.\n\nFloat thinks the signatures might tell her something.\n\nShe also admitted she might be wrong.",
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
                'minigame_type'      => 'cipher_lock',
            ],
            [
                'stage_number'       => 3,
                'title'              => 'Report Back',
                'objective_text'     => "The sink didn't kill you.\n\nSomehow.\n\nReturn to Float and find out what she learned.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s3_l1.mp3', 'text' => "The overlays surrounding Float have changed when you return. Something has moved. Something has been rebuilt. Whatever she was working on before has become something else entirely.\n\nShe notices your signal before you reach the bench."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l2.mp3', 'text' => "You held longer than I expected.\n\nSit down before you fall down."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s3_l3.mp3', 'text' => "She clears the only available surface.\n\nApparently you rank slightly above obsolete hardware.\n\nSlightly.\n\nShe runs the data once.\n\nThen again.\n\nRearranges three overlays.\n\nRuns it a third time."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l4.mp3', 'text' => "Well."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s3_l5.mp3', 'text' => "She leans closer to the readout.\n\nThen farther away.\n\nAs though changing the angle might somehow make it behave."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l6.mp3', 'text' => "That's worse."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'UNEASY', 'text' => "Worse?"],
                    ]],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l7.mp3', 'text' => "I was hoping it was broken."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s3_l8.mp3', 'text' => "She runs the analysis again."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l9.mp3', 'text' => "Broken things make sense.\n\nThe sink didn't corrupt it.\n\nDidn't slow it down either."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s3_l10.mp3', 'text' => "She reaches for a tool, thinks better of it, and instead opens another overlay."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l11.mp3', 'text' => "Whatever's inside you...\n\nIt knew exactly what to do."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'DIRECT', 'text' => "What did you find?"],
                    ]],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l12.mp3', 'text' => "I don't know.\n\nAnd I hate saying that."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s3_l13.mp3', 'text' => "She studies the overlays in silence.\n\nThen dismisses half of them with visible irritation."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l14.mp3', 'text' => "It's not hiding.\n\nWhich is strange.\n\nIt's not fighting me.\n\nWhich is stranger.\n\nAnd every assumption I make turns out wrong."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s3_l15.mp3', 'text' => "She squints at the readout."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l16.mp3', 'text' => "That's rude."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s3_l17.mp3', 'text' => "For the first time since you arrived, she stops looking at the architecture.\n\nShe looks at you."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l18.mp3', 'text' => "Most things I find are dead.\n\nBroken.\n\nIncomplete.\n\nThat's why people throw them away."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s3_l19.mp3', 'text' => "She runs the data one final time."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l20.mp3', 'text' => "Whatever's inside you...\n\nIt knows exactly what it is."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s3_l21.mp3', 'text' => "The thought seems to bother her."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l22.mp3', 'text' => "And I don't think I've decided whether I find that exciting...\n\nOr terrifying."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s3_l23.mp3', 'text' => "One of the overlays flashes for attention. Float dismisses it without looking.\n\nThen another.\n\nAnd another.\n\nNone of them are as interesting as the thing sitting across from her."],
                    ['speaker' => 'FLOAT',   'audio' => 'float/f_s3_l24.mp3', 'text' => "Hm.\n\nWell.\n\nYou're still alive.\n\nThat's usually a good sign.\n\nTry to stay that way.\n\nI'd like to know how this ends."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/float/f_s3_l25.mp3', 'text' => "She picks up the tool she'd set down when you first arrived.\n\nAnd just like that, she's back to convincing dead things to speak again."],
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
                'objective_text'     => "Another fragment of the signal resolved the moment you left Spokane Valley. Same architecture. Same source.\n\nThis time it came through clean — two words and a coordinate: University District. Axiom.\n\nGet to the UD-Hub. Whoever Axiom is, the signal already knew where to send you.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s1_l1.mp3', 'text' => "Axiom's node renders as an impossible archive — shelves stretching upward into darkness, disappearing long before they reach a ceiling. Folders drift through the air in slow deliberate orbits, endlessly reorganizing themselves around patterns only they seem to understand. Forgotten protocols, dead social spaces and centuries of accumulated thought sit preserved behind translucent partitions. Nothing here feels abandoned.\n\nNothing feels hurried either.\n\nAn older man sits at the center of it all behind a desk assembled more from memory than furniture. His silver hair is neatly kept, his clothes belong to no obvious era, and his posture carries the quiet confidence of someone who has spent his life teaching others. He doesn't notice you immediately.\n\nHe's reading."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s1_l1.mp3', 'text' => "Ah.\n\nOne moment, if you please.\n\nI'd hate to lose my place."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s1_l2.mp3', 'text' => "Several folders shift overhead. Another disappears into the shelves. Only then does he close the file and finally look up.\n\nHis eyes settle on you.\n\nThen your rig.\n\nThen back to you.\n\nA faint smile touches the corner of his mouth."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s1_l2.mp3', 'text' => "Well.\n\nThat's curious.\n\nYou do leave rather a trail behind you."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'TIRED', 'text' => "Everybody keeps saying that."],
                    ]],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s1_l3.mp3', 'text' => "Mm.\n\nI imagine they do.\n\nPlease.\n\nSit.\n\nYou're distracting several centuries."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s1_l3.mp3', 'text' => "A chair materializes opposite the desk. You don't remember it being there before.\n\nHe folds his hands and studies you.\n\nNot your rig.\n\nYou.\n\nLike a librarian trying to remember where he's seen a particular book before."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s1_l4.mp3', 'text' => "Curious."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s1_l4.mp3', 'text' => "Somewhere above, a folder changes position."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s1_l5.mp3', 'text' => "Your architecture references systems that no longer exist.\n\nWhich happens.\n\nMemory, after all, can be untidy."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s1_l5.mp3', 'text' => "Another folder drifts overhead."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s1_l6.mp3', 'text' => "It also references systems that never existed."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s1_l6.mp3', 'text' => "He stops."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s1_l7.mp3', 'text' => "Well.\n\nThat is unusual."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'DIRECT', 'text' => "Do you know what it is?"],
                    ]],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s1_l8.mp3', 'text' => "Mm.\n\nNo, I'm afraid I don't.\n\nThough I appreciate your optimism."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s1_l7.mp3', 'text' => "He leans back slightly, considering."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s1_l9.mp3', 'text' => "Understanding a thing and repairing a thing are not always the same profession.\n\nMine has always been understanding."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s1_l8.mp3', 'text' => "One of the folders settles into place behind him."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s1_l10.mp3', 'text' => "There is an archive beneath the University District.\n\nConstruction records.\n\nEarly documentation.\n\nI have spent eleven years attempting to access it.\n\nIt dislikes me."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'AMUSED', 'text' => "The archive dislikes you?"],
                    ]],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s1_l11.mp3', 'text' => "Mm.\n\nQuite passionately.\n\nFortunately, it appears to dislike things that make sense.\n\nWhich brings us to your rather exceptional circumstances.\n\nRetrieve the packet.\n\nIn exchange, I will tell you everything I know."],
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
                'objective_text'     => "Axiom has spent eleven years trying to access an archive beneath the University District. Something in its architecture refuses to engage with clean systems — it reads them as hostile.\n\nYours doesn't read as clean.\n\nGet to node UD-v17 and extract the data packet Axiom needs.\n\n[WARNING] — The archive ICE will spike. Your rig will respond in kind. Keep working.\n\nAxiom said it dislikes things that make sense.\n\nProve it right.",
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
                'objective_text'     => "The packet is out.\n\nAxiom is waiting.\n\nReturn to the UD-Hub and collect what was promised.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l1.mp3', 'text' => "When you return, the archive has changed.\n\nNew folders drift among the old ones. Entire shelves have rearranged themselves. Thousands of tiny adjustments made by someone incapable of leaving knowledge alone.\n\nAxiom isn't reading when you arrive.\n\nHe's waiting."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l1.mp3', 'text' => "Ah.\n\nYou survived.\n\nGood.\n\nI dislike unresolved stories."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l2.mp3', 'text' => "The packet unfolds into the air between you. Thousands of pages flicker past in seconds.\n\nAxiom watches quietly.\n\nPatiently.\n\nThen he sighs."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l2.mp3', 'text' => "Hm."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'FOCUSED', 'text' => "What does it say?"],
                    ]],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l3.mp3', 'text' => "Less than I hoped.\n\nMore than I expected."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'FLAT', 'text' => "That's not an answer."],
                    ]],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l4.mp3', 'text' => "No.\n\nIt isn't."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l3.mp3', 'text' => "He folds his hands atop the desk."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l5.mp3', 'text' => "I study history.\n\nHistory is comforting.\n\nEvents happen.\n\nTime passes.\n\nPeople assign meaning.\n\nBut history only speaks after things are finished."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l4.mp3', 'text' => "His eyes drift toward your rig."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l6.mp3', 'text' => "Whatever is inside you...\n\nIt is still writing itself."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'FLAT', 'text' => "So you don't know."],
                    ]],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l7.mp3', 'text' => "Correct."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l5.mp3', 'text' => "No embarrassment.\n\nNo frustration.\n\nJust honesty."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l8.mp3', 'text' => "Knuckle sees bodies.\n\nVeil sees systems.\n\nFloat sees machines."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l6.mp3', 'text' => "Another folder settles into place above him."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l9.mp3', 'text' => "I see stories.\n\nAnd stories are difficult to understand while one is still living inside them."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'BITTER', 'text' => "Nobody knows anything."],
                    ]],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l7.mp3', 'text' => "For the first time since you arrived—\n\nAxiom smiles."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l10.mp3', 'text' => "Ah.\n\nI wouldn't say that.\n\nWe know quite a lot, actually.\n\nWe know it isn't killing you.\n\nWe know it adapts.\n\nWe know it remembers things nobody expected.\n\nAnd we know it possesses remarkable patience.\n\nWhich, I confess, is more than I can say for most people."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'FLAT', 'text' => "So that's it?"],
                    ]],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l8.mp3', 'text' => "Axiom sits quietly for a moment.\n\nSomewhere above, another folder settles into place.\n\nHe watches it with the satisfaction of someone putting a book back where it belongs."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l11.mp3', 'text' => "Mm.\n\nFor now, I believe so."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'FLAT', 'text' => "That's not very reassuring."],
                    ]],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l12.mp3', 'text' => "No.\n\nIt isn't."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l9.mp3', 'text' => "He folds his hands atop the desk."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l13.mp3', 'text' => "The world has an unfortunate habit of refusing to reveal itself all at once.\n\nIf it did, I suspect my profession would've become terribly boring centuries ago."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l10.mp3', 'text' => "He smiles softly."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l14.mp3', 'text' => "We spend our lives believing understanding arrives all at once.\n\nIt rarely does.\n\nMore often, it arrives one conversation at a time."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'QUIET', 'text' => "And if it doesn't?"],
                    ]],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l11.mp3', 'text' => "The old man considers the question carefully."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l15.mp3', 'text' => "Then we live with what we know.\n\nAnd remain grateful for what we don't."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l12.mp3', 'text' => "He rises from his desk and carefully returns the file he'd been reading to the endless shelves above.\n\nSeveral folders shift to accommodate it.\n\nSatisfied, he turns back to you."],
                    ['speaker' => 'AXIOM',   'audio' => 'axiom/a_s3_l16.mp3', 'text' => "You've been carrying questions for quite some time.\n\nI imagine they must be heavy.\n\nGo get some rest."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/axiom/a_s3_l13.mp3', 'text' => "And just like that, the conversation is over.\n\nNot because he is finished with you.\n\nNot because he lacks answers.\n\nSimply because, in Axiom's view, there is nothing more to say today."],
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
                'objective_text'     => "Another fragment hit your system leaving the University District.\n\nSame source. Same impossible architecture.\n\nThis time it resolved into something cleaner: North Spokane. Patch.\n\nYou barely remember leaving the University District. Your deck is running at critical temperature. Your HUD freezes for seconds at a time. Entire moments arrive out of order. Sometimes your avatar moves before you tell it to. Sometimes it doesn't move at all.\n\nWhatever is inside your system isn't slowing down.\n\nAnd neither is the thing trying to remove it.\n\nEvery hour hurts more than the last.\n\nGet to the NS-Hub before your rig gives out completely.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/patch/p_s1_l1.mp3', 'text' => "Patch's node renders as an old maintenance station buried beneath North Spokane — exposed pipes, concrete walls and bundles of cable disappearing into the dark like roots. Nothing here was designed to be lived in.\n\nOver the years, someone changed their mind.\n\nPlants grow beneath artificial lamps. Half-finished electronics cover every available surface. A kettle simmers quietly on a hotplate that somehow still works. Several terminals drift lazily through the air around the room, opening and closing according to a logic known only to their owner.\n\nThe whole place feels less like a workshop and more like somewhere somebody forgot to leave."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/patch/p_s1_l2.mp3', 'text' => "Her avatar is compact and practical, dressed in patched work clothes with tools hanging from her belt that never seem to stay in the same place twice. Dark hair tied back with whatever happened to be closest. One sleeve rolled up, the other forgotten.\n\nShe's hanging upside down beneath a maintenance platform.\n\nSomehow.\n\nShe notices you.\n\nKeeps working.\n\nYou take another step.\n\nYour vision smears.\n\nThe room jumps sideways.\n\nSomething screams through your audio feed.\n\nYour knees buckle.\n\nBy the time the node catches up again, you're on the floor."],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s1_l1.mp3', 'text' => "Oh.\n\nThat's significantly worse than I expected."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/patch/p_s1_l3.mp3', 'text' => "She's beside you immediately. One of the floating terminals abandons whatever it was doing and starts throwing diagnostics into the air. Another begins screaming warning tones.\n\nPatch ignores both."],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s1_l2.mp3', 'text' => "Hey.\n\nStay with me.\n\nYou can pass out later.\n\nI'd rather introduce myself first."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/patch/p_s1_l4.mp3', 'text' => "She helps you upright.\n\nOne look at your architecture and her expression changes.\n\nNot fear.\n\nNot confusion.\n\nProfessional irritation.\n\nThe sort reserved for problems that refuse to explain themselves."],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s1_l3.mp3', 'text' => "Wow.\n\nThat's rude."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'DESPERATE', 'text' => "Please tell me somebody knows what's happening."],
                    ]],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s1_l4.mp3', 'text' => "Maybe.\n\nProbably not.\n\nBut maybe."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/patch/p_s1_l5.mp3', 'text' => "She circles you once, studying the readouts hanging around your rig. Her eyes move faster than the terminals.\n\nShe frowns."],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s1_l5.mp3', 'text' => "I need calibration packages from NS-v13.\n\nVolatile sub-routines.\n\nUgly things.\n\nNobody wants them.\n\nWhich makes them my responsibility.\n\nBring them back.\n\nI'll see what I can see."],
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
                'title'              => 'Calibration Tether',
                'objective_text'     => "Patch needs volatile calibration packages from node NS-v13.\n\nNobody wants them.\n\nWhich, according to Patch, makes them her problem.\n\nRetrieve the packages and bring them back to her node.\n\n[WARNING] — The sub-routines are unstable and actively resist containment. Every packet carried will drain your stability.\n\nYour own system is unstable.\n\nMove quickly.",
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
                'title'              => 'Report Back',
                'objective_text'     => "The packages are secured.\n\nYour deck isn't.\n\nReturn to Patch and collect your payment.\n\nFive docs. Five jobs. No answers.\n\nAnd whatever is inside you is only getting louder.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/patch/p_s3_l1.mp3', 'text' => "The kettle whistles when you return.\n\nPatch doesn't.\n\nShe's sitting cross-legged on the floor surrounded by open terminals and handwritten notes.\n\nOne of the plants has somehow acquired a screwdriver.\n\nShe doesn't seem surprised."],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s3_l1.mp3', 'text' => "Welcome back.\n\nOh.\n\nYou survived.\n\nGood."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/patch/p_s3_l2.mp3', 'text' => "Several terminals wake up when your signal enters the room.\n\nThey begin comparing diagnostics.\n\nPatch studies them.\n\nFrowns.\n\nStudies them again.\n\nThen rubs her eyes."],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s3_l2.mp3', 'text' => "Hm.\n\nHm."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'DIRECT', 'text' => "Just tell me what's wrong with me."],
                    ]],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s3_l3.mp3', 'text' => "No idea."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'FLAT', 'text' => "Seriously?"],
                    ]],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s3_l4.mp3', 'text' => "Mm.\n\nSorry."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/patch/p_s3_l3.mp3', 'text' => "She doesn't sound embarrassed.\n\nJust disappointed.\n\nNot in you.\n\nIn the data."],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s3_l5.mp3', 'text' => "Most things make sense eventually.\n\nDependency.\n\nAttachment.\n\nAddiction.\n\nHabit.\n\nComfort.\n\nPeople are wonderfully predictable."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'FLAT', 'text' => "And this isn't?"],
                    ]],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s3_l6.mp3', 'text' => "No.\n\nWhich is annoying.\n\nInteresting.\n\nBut annoying."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'BITTER', 'text' => "Nobody knows anything."],
                    ]],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s3_l7.mp3', 'text' => "Ah.\n\nI wouldn't say that.\n\nI think everybody knows something.\n\nWhich is considerably more inconvenient."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'FLAT', 'text' => "Meaning?"],
                    ]],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s3_l8.mp3', 'text' => "Meaning nobody gets to be entirely correct.\n\nTerrible system.\n\nI've filed several complaints."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'DRY', 'text' => "Did anyone answer?"],
                    ]],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s3_l9.mp3', 'text' => "No.\n\nWhich rather proves the point."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/patch/p_s3_l4.mp3', 'text' => "One of the terminals chirps.\n\nPatch reaches over and removes a screwdriver from a potted plant."],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s3_l10.mp3', 'text' => "Honestly, I still haven't figured out how that keeps happening."],
                    ['speaker' => 'PLAYER_CHOICE', 'options' => [
                        ['tone' => 'DRY', 'text' => "That's your professional advice?"],
                    ]],
                    ['speaker' => 'PATCH',    'audio' => 'patch/p_s3_l11.mp3', 'text' => "Mm.\n\nDisappointing, isn't it?\n\nGet some sleep.\n\nEat something.\n\nTry not to panic.\n\nHumanity's been using that strategy for thousands of years.\n\nSeems rude not to continue the tradition."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/patch/p_s3_l5.mp3', 'text' => "And just like that she's already halfway back into whatever she was doing before you arrived.\n\nNot because she doesn't care.\n\nNot because she's dismissing you.\n\nSimply because she's reached the edge of what she knows.\n\nAnd unlike most people—\n\nPatch has made peace with that."],
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
