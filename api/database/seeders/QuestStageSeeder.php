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
 *   codex_thread_key    — activates an optional Codex investigation thread on completion
 *                         (null = none). See CodexService — not required progress.
 *   node_canvas_id      — map node the player must be at to trigger (null = no requirement)
 *   minigame_type       — null | 'data_grab' | 'system_override'
 *   field_comms         — optional array of in-field DOC voice-call lines, played via
 *                         FieldCommsWindow.vue while the player works this stage's node.
 *                         [{ speaker: 'doc'|'player', text, audio?, fx? }, ...]. Omit the
 *                         key entirely for stages with no field call.
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
                // NEEDS VOICING — text only, no 'audio' key yet. FieldCommsWindow.vue
                // falls back to a reading-time hold until lines are recorded. Suggested
                // path convention to match the rest of Veil's audio: veil/fc_dt2_0N.mp3
                'field_comms'        => [
                    ['speaker' => 'doc', 'text' => "Feed's live. Don't inspect what comes through — just flush it."],
                    ['speaker' => 'doc', 'text' => "...huh. Buffer's not fighting you the way it fights everyone else."],
                    ['speaker' => 'doc', 'text' => "Don't get comfortable. Keep the signal moving."],
                    ['speaker' => 'doc', 'text' => "Last of it's coming through. Flush and get clear."],
                    ['speaker' => 'player', 'text' => "Clear. Whatever that was, it's gone now."],
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
                'codex_thread_key'   => 'monroe-street-signal',
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
                // NEEDS VOICING — text only, no 'audio' key yet. FieldCommsWindow.vue
                // falls back to a reading-time hold until lines are recorded. Suggested
                // path convention to match the rest of Float's audio: float/fc_sv2_0N.mp3
                'field_comms'        => [
                    ['speaker' => 'doc', 'text' => "You're at the sink. Don't leave until it stabilizes — half-soaked data is worse than none."],
                    ['speaker' => 'doc', 'text' => "Readouts are moving faster than I expected. Keep at it."],
                    ['speaker' => 'doc', 'text' => "Whatever's in you is drinking that faster than it should."],
                    ['speaker' => 'doc', 'text' => "Close it out. I want to see what you're carrying."],
                    ['speaker' => 'player', 'text' => "Closing it out. Hope it was worth watching."],
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
                // NEEDS VOICING — text only, no 'audio' key yet. FieldCommsWindow.vue
                // falls back to a reading-time hold until lines are recorded. Suggested
                // path convention to match the rest of Axiom's audio: axiom/fc_ud2_0N.mp3
                'field_comms'        => [
                    ['speaker' => 'doc', 'text' => "The archive knows you're in it. It always knows. Proceed regardless."],
                    ['speaker' => 'doc', 'text' => "Ah — there. The ICE just noticed you don't read as hostile. How curious."],
                    ['speaker' => 'doc', 'text' => "Take your time. Or don't. It rarely rewards patience, in my experience."],
                    ['speaker' => 'doc', 'text' => "The packet is close. Extract it before the archive changes its mind."],
                    ['speaker' => 'player', 'text' => "Got it. Whatever 'it' decided, I'm not sticking around to ask."],
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
                // NEEDS VOICING — text only, no 'audio' key yet. FieldCommsWindow.vue
                // falls back to a reading-time hold until lines are recorded. Suggested
                // path convention to match the rest of Patch's audio: patch/fc_ns2_0N.mp3
                'field_comms'        => [
                    ['speaker' => 'doc', 'text' => "Nudge them gently. They don't like being handled, but they hate being ignored more."],
                    ['speaker' => 'doc', 'text' => "That one's drifting. Route it before it destabilizes the rest."],
                    ['speaker' => 'doc', 'text' => "Your rig's shaking almost as much as the sub-routines. Comforting, that."],
                    ['speaker' => 'doc', 'text' => "Last one. Get it stable and get out of there."],
                    ['speaker' => 'player', 'text' => "Stable. Barely. Bringing them in."],
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

        // ═══════════════════════════════════════════════════════════════════════════
        // CHAPTER 1 — "Static"
        // Source of truth: api/CHAPTER_1_SCRIPT.md. Non-linear doc order per chapter
        // (Float -> Axiom -> Veil -> Knuckle -> Patch -> back to Knuckle -> Veil again),
        // so referrals chain across docs the same way the Prologue's do, except Veil's
        // arc is split: her first stage refers to Knuckle, and her second stage (the
        // chapter close) doesn't fire until Knuckle's own Chapter 1 arc is fully
        // complete — gated in Game.vue, not here, since that's a cross-doc dependency
        // this linear per-arc seeder can't express. See QuestService.php's Chapter 1
        // kickoff special-case (keyed off Patch's Prologue 'prologue_complete' stage)
        // for how these five arcs unlock from a standing start.
        //
        // Field-job stages for Float's relay station and Axiom's courier depot reuse
        // each doc's own Prologue minigame_type as a flagged placeholder (cipher_lock /
        // archive_extraction) — Chapter 1 doesn't have bespoke minigames designed yet.
        // Their field_comms lines are ORIGINAL, not from the script — CHAPTER_1_SCRIPT.md
        // explicitly left these two field jobs' comms undrafted (see its v2.1/v1.7
        // changelog notes), so these are short in-voice check-ins written to fill the
        // gap, left text-only (no audio) same as the Prologue's own not-yet-voiced
        // DT-hub|1 stage 2 lines.
        // ═══════════════════════════════════════════════════════════════════════════

        // ── Chapter 1, Quest 1: The Cold Static ───────────────────────────────────
        // Contractor: Float | District: Spokane Valley | Field target: SV-v14
        // (verified against NodeSeeder.php — reachable from SV-hub via SV-v8/SV-v15,
        // distinct from SV-v9, which is already the Prologue's target)
        // ───────────────────────────────────────────────────────────────────────────
        'SV-hub|2' => [
            [
                'stage_number'       => 1,
                'title'              => 'Something Is Missing',
                'objective_text'     => "You come to in Float's rig with no memory of the last five minutes and a corruption signature she's never seen before — zero matches against decades of cataloged payloads. She won't take payment for chasing it, but she needs rig time to keep digging, and she's got a job sitting cold.\n\nThere's a memory core still intact in a decommissioned relay station, edge of the Valley grid. Get it before some scrapper strips it for parts instead of data.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p1_l1.mp3', 'text' => "The diagnostic lead clicks into your collar before you're even conscious enough to feel the cold alloy. You wake up suspended in Float's rig, suspended in static. Your temple is throbbing — a rhythmic, dull-blade pulse right behind your left eye. The shop's ceiling is tilting slowly to the right."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p1_l2.mp3', 'text' => "Hey. Eyes on me. Stay anchored."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p1_l3.mp3', 'text' => "Float isn't looking at your face; she's looking at the diagnostic rack behind you. She steps in, wrenching a snarl of braided wire out of the way."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p1_l4.mp3', 'text' => "You blew through my lock, kicked my door off its track, and spewed three seconds of garbled machine code before your legs gave out. You want to tell me what that was?"],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p1_l5.mp3', 'text' => "You reach for the memory of five minutes ago. There's no door, no walk, no panic. Just an empty grey void where the timeline should be.\n\nYou open your mouth to explain, but your vocal synth stutters. The words fracture into raw phonemes, lagging half a beat behind your jaw."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s1_p1_l6.mp3', 'text' => "I... can't— something's... missing. I don't know how I got here."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p1_l7.mp3', 'text' => "Float doesn't look surprised. She stops listening to your voice and starts listening to the telemetry — the way an engineer ignores a panicked driver to read the oil pressure."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p1_l8.mp3', 'text' => "Stop forcing the vocal track. Just breathe. You're dropping frames."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p1_l9.mp3', 'text' => "With a sharp gesture, she snaps a holographic telemetry window into the air between you. Raw data cascades down in harsh amber text — far too fast for your glitched optics to parse, but Float's eyes track every line.\n\nHer brow hitches. A beat of dead silence hangs in the workshop."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p1_l10.mp3', 'text' => "Your core temp is spiking, but that's just collateral. Look at this spike before you collapsed. Whatever spiked your system wasn't a spike at all. It was a background process. It was running in your stack for ten minutes before you dropped."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p1_l11.mp3', 'text' => "She doesn't wait for your answer. She already knows you don't have one.\n\nFloat swaths her hand across the air, dragging a second pane alongside the first — her personal black-market archive. Decades of black-budget intrusion signatures, dead megacorp payloads, and corrupt firmware patterns begin cross-referencing against your spike.\n\nThe progress bar doesn't even stretch. It snaps instantly to zero."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p1_l12.mp3', 'text' => "Zero."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p1_l13.mp3', 'text' => "She drops her hand, staring at the empty query result. Float's voice drops half an octave — stripped of its usual defensive sarcasm, leaving only cold, mechanical calculation."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p1_l14.mp3', 'text' => "Not a bad match. Not a partial corruption signature. Zero. I have payloads cataloged in this rig from before the grid fell, and your footprint doesn't share a single line of logic with any of them."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p1_l15.mp3', 'text' => "She turns away from the floating glass, her gaze drifting toward the heavy steel door you supposedly forced open. Her hand lingers near the lock manual override."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p1_l16.mp3', 'text' => "I built this sanctuary on one rule: if it comes through that door, I know what it is and I know how to kill it. But whatever is sitting inside your head right now... it isn't in anyone's system."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p1_l17.mp3', 'text' => "Float straightens away from the window, and just like that, something in her posture resets — the crack sealing back over, replaced by the flat working calm of someone who fixes problems for a living. She crosses to the rig and starts stripping the diagnostic leads off you herself, quick and unceremonious."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p1_l18.mp3', 'text' => "Here's where we are. I don't know what this is. I don't like not knowing. Those two things mean I'm going to keep pulling on this thread whether you pay me or not — but pulling on it costs me rig time, and rig time isn't free."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p1_l19.mp3', 'text' => "The last lead comes free from your collar with a small, cold pop. She tosses it onto the rack without looking."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p1_l20.mp3', 'text' => "I've got a job sitting cold because it's not worth my time for what it pays. It's worth yours. Run it, and I keep digging on this in the background while you're out. That's the trade."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s1_p1_l21.mp3', 'text' => "Where."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p1_l22.mp3', 'text' => "Decommissioned relay station, edge of the Valley grid. There's a memory core still intact in the wreck — old enough nobody's bothered stripping it. I want it before some scrapper beats you to it and sells it for parts instead of data."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p1_l23.mp3', 'text' => "Float pulls up a district map instead of saying anything else, one node glowing cold blue against the sprawl of the Valley grid."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p1_l24.mp3', 'text' => "There. Don't collapse on me twice in one day. I've got a reputation to protect."],
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
                'title'              => 'The Relay Station',
                'objective_text'     => "Decommissioned relay station, edge of the Valley grid. Pull the memory core before a scrapper beats you to it.\n\n[WARNING] — The wreck's dead, but your rig isn't. Keep it clean.",
                'dialogue'           => null,
                'field_comms'        => [
                    ['speaker' => 'doc', 'text' => "Core's logged as still seated. Don't force it — coax it."],
                    ['speaker' => 'doc', 'text' => "...huh. Wreck's colder than the readout says it should be."],
                    ['speaker' => 'doc', 'text' => "Get the core and get out. I'll want a full read the second you're back."],
                    ['speaker' => 'player', 'text' => "Copy. Pulling it now."],
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
                'node_canvas_id'     => 'SV-v14',
                'minigame_type'      => 'cipher_lock',
            ],
            [
                'stage_number'       => 3,
                'title'              => 'Report Back',
                'objective_text'     => "The core's in hand. Get back to Float — she's had rig time to dig while you were out, and something in how she said it means she found something.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p2_l1.mp3', 'text' => "Float doesn't look up when the heavy door groans open. It's still hanging half an inch off its mounting track from when you kicked it in earlier. She's elbow-deep in an open chassis on the main bench, live wire-harbors sparking against her gauntlets.\n\nWithout breaking her stride, she extends a grease-stained palm back toward you."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p2_l2.mp3', 'text' => "Core. Give."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p2_l3.mp3', 'text' => "You drop the warm alloy module into her hand. She doesn't inspect it. It disappears into a heavy steel drawer with a hydraulic thud — filed away like the job was just a distraction to keep your hands busy."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s1_p2_l4.mp3', 'text' => "Did you pull anything off my read while I was in the field?"],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p2_l5.mp3', 'text' => "Her pneumatic driver goes silent. Float sets the tool down on the bench — slow, precise, the calculated pause of someone trying to organize bad news into manageable pieces."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p2_l6.mp3', 'text' => "I stopped looking for a match in my archive. I ran your spike against itself. Evaluated the signature's delta over time instead of static code."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p2_l7.mp3', 'text' => "She snaps her fingers, sweeping a multi-layered spectral waveform into the air between you. It pulses with a frantic, jagged frequency — dense, tight, and unnervingly rhythmic."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p2_l8.mp3', 'text' => "Noise is chaotic. It degrades. This isn't degrading. Every iteration of this wave is cleaner, sharper, and more optimized than the one before it. Like a program running a self-correction loop. Like something's practicing."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p2_l9.mp3', 'text' => "She cuts herself off mid-thought. For a fraction of a second, her jaw sets hard, like she just glimpsed something through the code she'd rather unsee."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p2_l10.mp3', 'text' => "Data behaving like it has intent... that's not a storage problem. That's a structural one. It's built on something — referencing something — and none of it matches anything I've got catalogued. I fix hardware, not history. I can't tell you if what's underneath this is a design nobody's used in decades... or one that was never supposed to exist."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s1_p2_l11.mp3', 'text' => "If you can't read it, who can?"],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p2_l12.mp3', 'text' => "Axiom. University District. Cross-referencing a pattern against a hundred years of buried, pre-collapse architecture is his entire business model — he can dig up parallels out of that archive of his that I wouldn't even know how to search for. I already sent him the raw telemetry package. Get going."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s1_p2_l13.mp3', 'text' => "She's already reaching back into the open chassis on her bench, her fingers darting into the mechanical guts before you've even taken a step toward the door. But just before the sparks start flying again, her shoulder hitches."],
                    ['speaker' => 'FLOAT',    'audio' => 'float/chapter_1/c1_s1_p2_l14.mp3', 'text' => "And don't tell him I care what he finds in that architecture of yours. He'll think I'm going soft, and I don't need Axiom thinking I have a weak spot."],
                ],
                'rep_reward'         => 25,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => 'UD-hub',
                'referral_text'      => "[ SPLICE ] — Float forwards the raw telemetry package ahead of you — keyword: UNIVERSITY DISTRICT // AXIOM",
                'reward_creds'       => 100,
                'reward_tech_points' => 1,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'SV-hub',
                'minigame_type'      => null,
            ],
        ],

        // ── Chapter 1, Quest 2: The Reaching Signal ───────────────────────────────
        // Contractor: Axiom | District: University District | Field target: UD-v9
        // ───────────────────────────────────────────────────────────────────────────
        'UD-hub|2' => [
            [
                'stage_number'       => 1,
                'title'              => 'A Confident Guess',
                'objective_text'     => "Axiom's diagnostic reader is dead — a burned-out component he can't route around, the part that reads depth architecture instead of surface noise. Without it, he can only guess.\n\nThe replacement's been sitting unclaimed at a courier depot for three days; his usual runner won't touch it since the depot changed hands.\n\nGet to UD-v9, fetch the part, and bring it back to the archive.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p1_l1.mp3', 'text' => "Axiom's space is nothing like Float's — shelves stretching up into a darkness that never quite resolves into a ceiling, folders drifting through the air in slow, unhurried orbits, resettling themselves as though the whole room is quietly filing itself. You feel the difference in your teeth before you feel it anywhere else: unhurried, precise, the kind of quiet that comes from centuries of not being interrupted. Axiom looks up from behind a desk assembled more from memory than furniture, closing whatever he was reading with the particular care of someone who intends to come back to it."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p1_l2.mp3', 'text' => "Float sent the file ahead of you. I've read it twice. Sit — you look like the walk here cost you more than it should have."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p1_l3.mp3', 'text' => "A chair drifts into place opposite the desk. You lower yourself into it before you're entirely sure it's finished arriving."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p1_l4.mp3', 'text' => "She's not wrong to send you to me. Her toolkit is built for structural repair, not exotic architecture. What she recorded doesn't look like corruption anyway. It looks like a signal correcting itself against feedback, which is a very particular kind of behavior. Do you know what that behavior usually is?"],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s2_p1_l5.mp3', 'text' => "No."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p1_l6.mp3', 'text' => "Learning. That's what it usually is. I don't say that to frighten you. I say it because I'd rather you hear the honest word from someone than spend the next hour guessing at a worse one."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p1_l7.mp3', 'text' => "He doesn't flinch saying it, and somehow that steadies you more than if he had. Axiom rises and crosses to an oversized diagnostic reader built into the shelves — the kind of instrument meant for legacy silicon, not modern rigs — already talking through the next step before he reaches it."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p1_l8.mp3', 'text' => "I want to lay your signal against the archive's oldest layers — properly, not a glance. That'll tell us whether it's isolated or spreading, and roughly how fast. It's not a pleasant comparison to run. It isn't a dangerous one either."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p1_l9.mp3', 'text' => "He rests a hand on the housing's access panel. It doesn't open. He tries again, and this time you hear something inside click without engaging — a small, wrong sound in an otherwise silent room."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p1_l10.mp3', 'text' => "Axiom's composure doesn't crack, exactly. It just goes still for a second, the way someone goes still when a plan quietly stops being available."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p1_l11.mp3', 'text' => "Of course. Not today."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s2_p1_l12.mp3', 'text' => "What's wrong with it?"],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p1_l13.mp3', 'text' => "The resonance coil burned out a component I can't route around — the part that reads depth architecture instead of surface noise. Without it I can hand you a very confident guess. I don't deal in those if I can help it. The replacement's been sitting at a courier depot two nodes from here for three days. My usual runner won't touch it — the depot's changed hands and nobody's sure who's actually holding the access rights anymore."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p1_l14.mp3', 'text' => "He turns back to you, and for a moment the warmth comes back into focus, deliberate, like he's choosing to spend it on you specifically."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p1_l15.mp3', 'text' => "I know I'm asking you to run errands while something is actively happening to you. I don't love that either. But I would rather have the real number than a fast one, and right now the real number is on the other side of that depot door."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s2_p1_l16.mp3', 'text' => "Where do I go."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p1_l17.mp3', 'text' => "UD-v9. Fetch the part, bring it back to the archive, and we'll find out what's actually happening to you."],
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
                'title'              => 'The Courier Depot',
                'objective_text'     => "Courier depot, UD-v9. Ownership's disputed and nobody's sure who's holding the access rights — pull the part out before that becomes your problem too.\n\n[WARNING] — Contested territory. Move deliberate, not fast.",
                'dialogue'           => null,
                'field_comms'        => [
                    ['speaker' => 'doc', 'text' => "Depot's contested territory right now — move deliberate, not fast."],
                    ['speaker' => 'doc', 'text' => "That's the part. Confirm the serial before you pull it."],
                    ['speaker' => 'doc', 'text' => "Good. Bring it back whole. I'd rather wait an extra minute than reseat something cracked."],
                    ['speaker' => 'player', 'text' => "Copy. Heading back now."],
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
                'node_canvas_id'     => 'UD-v9',
                'minigame_type'      => 'archive_extraction',
            ],
            [
                'stage_number'       => 3,
                'title'              => 'Report Back',
                'objective_text'     => "Part in hand. Get back to Axiom's archive and let him ask the question properly this time.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p2_l1.mp3', 'text' => "The part is smaller than expected—a matte-grey cylinder no bigger than a fingertip, its edges worn smooth from three days of sitting unclaimed. Axiom takes it with both hands, cradling it like something that used to belong to someone who mattered."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p2_l2.mp3', 'text' => "Good. No detours, no complications. I was almost disappointed—I'd already started composing the apology I was going to owe you."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p2_l3.mp3', 'text' => "He steps back to the reader, working the panel open with the patient, exact precision of a man who has done this a thousand times and will do it a thousand more. The replacement seats with a small, clean click—the sound the original should have made."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p2_l4.mp3', 'text' => "There. Now we ask the question properly."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p2_l5.mp3', 'text' => "He presses a lead against your temple—cold, sterile metal, nothing like Float's tangled nest of braided wire. The reader hums alive, low and resonant, shivering through your cheekbone. For a second, nothing happens."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p2_l6.mp3', 'text' => "Then the shelves shudder—then go still. Not the room. The sound: a hundred folders that were always faintly turning, rustling, snap silent at once. You feel it before you understand it—pressure behind your eyes, like the room just changed altitude."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p2_l7.mp3', 'text' => "Pain shears through your skull, sharp and blinding—a line of white, electric heat drawn directly from ear to ear, searing along the neural housing at the base of your brain. It vanishes before you can gasp, but your hand flies to your temple. Your fingers come away trembling."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p2_l8.mp3', 'text' => "Steady. That was the archive, not you—it doesn't like being asked questions it can't answer. And neither, apparently, do you."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p2_l9.mp3', 'text' => "His breathing doesn't change. If anything it slows—the sound of a man leaning into a problem instead of flinching from it. As the reader finishes its pass, a dense column of raw telemetry scrolls beside him. He goes quiet—a heavy, sudden silence that has nothing to do with reading speed."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p2_l10.mp3', 'text' => "Well."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p2_l11.mp3', 'text' => "I told you it referenced things that no longer exist. I was wrong about the shape of that. It isn't referencing old architecture the way a museum references history. It's referencing it the way flesh references a frame it was built around."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s2_p2_l12.mp3', 'text' => "Meaning what."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p2_l13.mp3', 'text' => "Meaning it isn't passive. I laid it against the deepest layer in this archive expecting alignment or silence. I got neither. For about four seconds, it reached."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p2_l14.mp3', 'text' => "He says the word deliberately, weighing it against three others he liked less."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p2_l15.mp3', 'text' => "Not spreading. Not corrupting anything of mine, you'll be relieved to hear. Reaching—the way a signal reaches for an origin point, or a dying limb twitches toward a pulse. It wasn't interested in my archive at all. It was using my system as a conduit to touch something further out."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s2_p2_l16.mp3', 'text' => "Further out than your archive."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p2_l17.mp3', 'text' => "Further out than anything in this room. That's a grid question, not an archivist's question—whether a payload can reach through a rig, down a person's spine, and out into the public frequency. I study what things were. I don't study what they're actively doing to city infrastructure while anchored in living tissue."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s2_p2_l18.mp3', 'text' => "He sets the lead down. Doesn't reach for the next tool. The quiet stretches long enough that you start counting your own heartbeat before he breaks it."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p2_l19.mp3', 'text' => "I'd like to tell you this narrows it down. It doesn't. It widens it. Twenty minutes ago, I thought you were carrying a dead artifact. Now I know you're carrying something ancient that is currently, actively trying to go somewhere else—and I have no idea whether that's better news, or considerably worse."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s2_p2_l20.mp3', 'text' => "Who do I see about the grid question."],
                    ['speaker' => 'AXIOM',    'audio' => 'axiom/chapter_1/c1_s2_p2_l21.mp3', 'text' => "Veil. Downtown Core. If something is using the frequency itself to reach for a destination, she's the only one left who'd recognize the footprint before it arrives. Tell her I said it reached—she'll know exactly how unhappy that should make her."],
                ],
                'rep_reward'         => 25,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => 'DT-hub',
                'referral_text'      => "[ SPLICE ] — Axiom flags the reading upstream before you've even left — keyword: DOWNTOWN CORE // VEIL",
                'reward_creds'       => 100,
                'reward_tech_points' => 1,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'UD-hub',
                'minigame_type'      => null,
            ],
        ],

        // ── Chapter 1, Quest 3: The Persistence Theory ────────────────────────────
        // Contractor: Veil | District: Downtown | non-contiguous: stage 2 is the
        // chapter close and does NOT become reachable just by finishing stage 1 —
        // Game.vue additionally gates it on Knuckle's Chapter 1 arc (BA-hub|2) being
        // fully complete, per CHAPTER_1_SCRIPT.md's "both loose ends land within one
        // scene of each other" note. No node_canvas_id on stage 2: it's delivered
        // unprompted via FieldCommsWindow wherever the player is, then handed off to
        // WatcherSignal mid-call, then ChapterTitleCard. See QuestService.php /
        // Game.vue for the wiring.
        // ───────────────────────────────────────────────────────────────────────────
        'DT-hub|2' => [
            [
                'stage_number'       => 1,
                'title'              => 'Reaching',
                'objective_text'     => "Axiom sent you here to ask a grid question: can something reach through a rig, down a person's spine, and out into the public frequency. Veil's the only one left who'd recognize that footprint.\n\nGet to the DT-Hub.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s3_p1_l1.mp3', 'text' => "Veil's node renders the way it always has — a sprawling workspace under warm hanging lights, exposed conduits and maintenance terminals crowding every surface, status boards and infrastructure maps floating overhead like constellations nobody's bothered to name. Massive windows look out over the distant glow of the Frequency, rain drifting lazily against glass that was never meant to be decorative and isn't. Every cable here is labeled. Every tool has a place. The whole room still carries that same feeling — a station that was supposed to close years ago, and never did."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s3_p1_l2.mp3', 'text' => "She's watching six terminals at once when you arrive, dark hair loosely tied back, the long coat she wears instead of armor hung with pockets and utility straps. One display shifts to your signal. Her eyes hold on it half a second longer than the others got."],
                    ['speaker' => 'VEIL',     'audio' => 'veil/chapter_1/c1_s3_p1_l3.mp3', 'text' => "Axiom's package. Twenty minutes ago. Four reads. Still not enough."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s3_p1_l4.mp3', 'text' => "She finally looks up. Not startled. Not concerned. Just tired, the way someone gets when strange problems stopped surprising them a long time ago."],
                    ['speaker' => 'VEIL',     'audio' => 'veil/chapter_1/c1_s3_p1_l5.mp3', 'text' => "Sit. I'd rather look at you than a report about you."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s3_p1_l6.mp3', 'text' => "A chair rises from the floor, spare, unpadded — nothing like Axiom's or Float's. She runs a slow pass with something that reads more like a level than a scanner, checking you against a baseline only she can see."],
                    ['speaker' => 'VEIL',     'audio' => 'veil/chapter_1/c1_s3_p1_l7.mp3', 'text' => "He's right that it's reaching. Wrong that it's rare."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s3_p1_l8.mp3', 'text' => "An alarm flashes amber somewhere behind her. She kills it with a flick of two fingers, without looking."],
                    ['speaker' => 'VEIL',     'audio' => 'veil/chapter_1/c1_s3_p1_l9.mp3', 'text' => "I've seen this pattern. Once. A substation. Grid-scale. It doesn't run inside a person. Or it didn't."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s3_p1_l10.mp3', 'text' => "Where was it running?"],
                    ['speaker' => 'VEIL',     'audio' => 'veil/chapter_1/c1_s3_p1_l11.mp3', 'text' => "Years ago. Before I did this for a living instead of against it."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s3_p1_l12.mp3', 'text' => "Something in how she says it tells you not to ask further, so you don't."],
                    ['speaker' => 'VEIL',     'audio' => 'veil/chapter_1/c1_s3_p1_l13.mp3', 'text' => "There's a name for it. On the boards where people build religions out of infrastructure failures. They call it—"],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s3_p1_l14.mp3', 'text' => "She doesn't finish. The word gets halfway out — the Persist— — and something inside your skull goes off like a struck bell."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s3_p1_l15.mp3', 'text' => "Pain doesn't describe it. Closer to feedback — a shriek pitched straight into the base of your neural housing, gone as fast as it hit, a thin thread of smoke curling up behind your left ear. Something back there just stopped working. Mid-sentence. Same as her."],
                    ['speaker' => 'VEIL',     'audio' => 'veil/chapter_1/c1_s3_p1_l16.mp3', 'text' => "—Theory."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s3_p1_l17.mp3', 'text' => "She finishes it anyway. Quieter now. Like completing the sentence outranks whatever just happened to you."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s3_p1_l18.mp3', 'text' => "What was that?"],
                    ['speaker' => 'VEIL',     'audio' => 'veil/chapter_1/c1_s3_p1_l19.mp3', 'text' => "Resonance dampener. Failed. Hold still."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s3_p1_l20.mp3', 'text' => "She's already moving — fast, precise, the reflex of someone who's handled hardware failure a thousand times and refuses to let this be anything else. Pulse. Burn. Readout again. By the time she's done, her voice is flat, controlled, all the way back inside its usual register."],
                    ['speaker' => 'VEIL',     'audio' => 'veil/chapter_1/c1_s3_p1_l21.mp3', 'text' => "Rig under stress. Threw an error. Bad timing. That's all this is."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s3_p1_l22.mp3', 'text' => "She doesn't sound like she believes it. She sounds like someone choosing, on purpose, not to say the rest of it twice."],
                    ['speaker' => 'VEIL',     'audio' => 'veil/chapter_1/c1_s3_p1_l23.mp3', 'text' => "Dampener's interface hardware. Not mine to fix. Knuckle can patch you functional. Go."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s3_p1_l24.mp3', 'text' => "And the name. The one you didn't finish."],
                    ['speaker' => 'VEIL',     'audio' => 'veil/chapter_1/c1_s3_p1_l25.mp3', 'text' => "A mistake. Fringe theory. Debunked years ago — fabricated, by people who wanted a better story than the boring truth. Not relevant."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s3_p1_l26.mp3', 'text' => "She's back at the readouts before you're fully standing, already pulling the next task into the air — but her hand, for one second before it steadies, isn't as sure of itself as the rest of her."],
                ],
                'rep_reward'         => 0,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => 'BA-hub',
                'referral_text'      => "[ SPLICE ] — a dampener failure logged against your own signature — keyword: BROWNE'S ADDITION // KNUCKLE",
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'DT-hub',
                'minigame_type'      => null,
            ],
            [
                // CHAPTER CLOSE. Unprompted FieldCommsWindow delivery — no node
                // requirement. Only reachable once Knuckle's BA-hub|2 arc is fully
                // complete (Game.vue guard, not expressible via linear per-arc
                // stage gating). Its FieldCommsWindow @complete is what Game.vue
                // hooks to fire WatcherSignal (signal_text "PERSISTENCE THEORY"),
                // then ChapterTitleCard ("Chapter 2 — Persistence") on reboot.
                'stage_number'       => 2,
                'title'              => 'We Need to Speak',
                'objective_text'     => "Veil calls. She ran your telemetry against the theory she called fabricated three days ago.",
                'dialogue'           => null,
                'field_comms'        => [
                    ['speaker' => 'doc', 'audio' => 'veil/chapter_1/c1_s3_p2_l1.mp3', 'text' => "Well. Look at that."],
                    ['speaker' => 'doc', 'audio' => 'veil/chapter_1/c1_s3_p2_l2.mp3', 'text' => "Ran your telemetry against every pattern I could pull. Couldn't find a clean line between your signal and a theory I called fabricated three days ago."],
                    ['speaker' => 'doc', 'audio' => 'veil/chapter_1/c1_s3_p2_l3.mp3', 'text' => "Axiom's archive did the actual sorting. I just asked it the right question."],
                    ['speaker' => 'player', 'audio' => 'player/chapter_1/c1_s3_p2_l4.mp3', 'text' => "What. Out with it already."],
                    ['speaker' => 'doc', 'audio' => 'veil/chapter_1/c1_s3_p2_l5.mp3', 'text' => "...Persistence Theory.", 'fx' => ['type' => 'static(4),flicker(3),bars(2)', 'duration' => 900]],
                ],
                'rep_reward'         => 50,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 150,
                'reward_tech_points' => 2,
                'reward_node_access' => null,
                'reward_lore_key'    => 'chapter_1_complete',
                'node_canvas_id'     => null,
                'minigame_type'      => null,
            ],
        ],

        // ── Chapter 1, Quest 4: The Dead End ──────────────────────────────────────
        // Contractor: Knuckle | District: Browne's Addition
        // Stage 1 completion also grants Patch's one-time "Resonance Dampener" catalog
        // item (QuestService special-case -> CyberDocInventoryService::grantCatalogItem,
        // source: 'mission:c1_s4_p1'). Stage 3 is Knuckle's unprompted "Still Live"
        // field-comms callback — no node requirement, same unprompted-delivery pattern
        // as Veil's chapter close. Knuckle's arc reaching 'complete' (all 3 stages) is
        // the gate Veil's chapter-close stage waits on.
        // ───────────────────────────────────────────────────────────────────────────
        'BA-hub|2' => [
            [
                'stage_number'       => 1,
                'title'              => 'Interface, Not Chassis',
                'objective_text'     => "Whatever failed behind your ear burned out — Knuckle can patch the housing, but the part itself is interface hardware, not chassis. He doesn't stock it.\n\nGet to North Spokane. Tell Patch he sent you for a standard dampener, not a consult.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s4_p1_l1.mp3', 'text' => "Knuckle's wagon is the same cramped, low-ceiling space it's always been — walls patched together from stolen network architecture, the seams still visible where different systems were forced to talk to each other. Medical readouts float at chest height, most of them running amber. His hulking asymmetrical frame doesn't turn when you walk in. One of his two diagnostic arms is already extended, pulling your signal before you've said a word."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s4_p1_l2.mp3', 'text' => "The readout it throws onto the wall comes back red, then narrows, then goes still on one specific spot behind your ear."],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/chapter_1/c1_s4_p1_l3.mp3', 'text' => "Whoever told you to hold still was right. You're lucky it cauterized instead of spreading."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s4_p1_l4.mp3', 'text' => "He pulls a handheld scanner into the other arm and runs it slow along the burn, the way he runs everything — no wasted motion, no urgency he hasn't decided to have. A burner cigarette materializes between two fingers of the arm he isn't using. He doesn't light it yet."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s4_p1_l5.mp3', 'text' => "Can you fix it?"],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/chapter_1/c1_s4_p1_l6.mp3', 'text' => "Housing, yeah. Whatever burned out inside it, no."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s4_p1_l7.mp3', 'text' => "He pulls the burned component free with a short mechanical click, turns it over once in the scanner's light, and doesn't bother hiding his opinion of it."],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/chapter_1/c1_s4_p1_l8.mp3', 'text' => "This isn't chassis. This is interface — the part that talks to your head, not the part that holds you together. I don't stock interface. Never have."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s4_p1_l9.mp3', 'text' => "So who does?"],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/chapter_1/c1_s4_p1_l10.mp3', 'text' => "Patch. North Spokane. Tell her Knuckle sent you for a standard dampener, not a consult — she'll try to turn it into one anyway."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s4_p1_l11.mp3', 'text' => "He finally lights the cigarette, already turning back to the readouts, done with you in the specific, efficient way of a man who never had more than two minutes to spend on anyone."],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/chapter_1/c1_s4_p1_l12.mp3', 'text' => "Bring it back. I'll seat it. Won't take long."],
                ],
                'rep_reward'         => 0,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => 'NS-hub',
                'referral_text'      => "[ SPLICE ] — Knuckle flags the part request ahead of you — keyword: NORTH SPOKANE // PATCH",
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'BA-hub',
                'minigame_type'      => null,
            ],
            [
                'stage_number'       => 2,
                'title'              => 'Repair',
                'objective_text'     => "Dampener in hand. Get back to Knuckle so he can seat it.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s4_p2_l1.mp3', 'text' => "Same wagon, same amber readouts drifting at chest height. Knuckle doesn't ask if you got it — just holds one of his diagnostic arms out, palm up, waiting."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s4_p2_l2.mp3', 'text' => "Patch says hi. Sort of."],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/chapter_1/c1_s4_p2_l3.mp3', 'text' => "She never says hi. Sit."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s4_p2_l4.mp3', 'text' => "He has the panel open before you're fully seated, the burned housing already out and set aside like he's been holding the shape of it in his head since you left. The new component goes in without ceremony — one motion, a short mechanical click."],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/chapter_1/c1_s4_p2_l5.mp3', 'text' => "Give it a second."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s4_p2_l6.mp3', 'text' => "You feel it before the readout even changes — a cold, precise pressure behind your ear, there and gone, like something exhaling on your behalf. The low, constant wrongness that's been sitting in that spot since Veil's office goes with it. On the wall, the readout follows a beat later: red, then amber, then a flat, uneventful green."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s4_p2_l7.mp3', 'text' => "That's it?"],
                    ['speaker' => 'KNUCKLE',  'audio' => 'knuckle/chapter_1/c1_s4_p2_l8.mp3', 'text' => "That's it. Told you it wouldn't take long."],
                ],
                'rep_reward'         => 25,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 50,
                'reward_tech_points' => 1,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => 'BA-hub',
                'minigame_type'      => null,
            ],
            [
                // Unprompted field-comms callback — no node requirement.
                'stage_number'       => 3,
                'title'              => 'Still Live',
                'objective_text'     => "Knuckle calls. Says it's not about the new part.",
                'dialogue'           => null,
                'field_comms'        => [
                    ['speaker' => 'doc', 'audio' => 'knuckle/chapter_1/c1_s4_p3_l1.mp3', 'text' => "Hey. It's me. Nothing's wrong with the new part — don't worry about that."],
                    ['speaker' => 'doc', 'audio' => 'knuckle/chapter_1/c1_s4_p3_l2.mp3', 'text' => "The old one. The one I pulled out of you. I didn't scrap it — left it on the bench, figured I'd strip it for parts later."],
                    ['speaker' => 'doc', 'audio' => 'knuckle/chapter_1/c1_s4_p3_l3.mp3', 'text' => "It's still drawing current. No host. No reason to. Sitting on my bench pulling power like it thinks it's still seated in you.", 'fx' => ['type' => 'flicker(1)', 'duration' => 300]],
                    ['speaker' => 'doc', 'audio' => 'knuckle/chapter_1/c1_s4_p3_l4.mp3', 'text' => "Don't know what that means. Not gonna pretend I do. Just didn't feel right sitting on it."],
                    ['speaker' => 'player', 'audio' => 'player/chapter_1/c1_s4_p3_l5.mp3', 'text' => "...Yeah. Good call. Thanks, Knuckle."],
                ],
                'rep_reward'         => 10,
                'is_branch'          => false,
                'branch_options'     => null,
                'referral_canvas_id' => null,
                'referral_text'      => null,
                'reward_creds'       => 0,
                'reward_tech_points' => 0,
                'reward_node_access' => null,
                'reward_lore_key'    => null,
                'node_canvas_id'     => null,
                'minigame_type'      => null,
            ],
        ],

        // ── Chapter 1, Quest 5: The Pickup ────────────────────────────────────────
        // Contractor: Patch | District: North Spokane
        // Simplification (flagged, not silently glossed over): the script's mechanic
        // note says this scene should fire only after the store purchase completes.
        // The engine doesn't currently have a "purchased this catalog item" stage
        // gate, so this dialogue instead unlocks the normal way (referral from
        // Knuckle's stage 1) and the dampener is simply available in Patch's catalog
        // by the time the player arrives — same one-time item, same scene, just not
        // hard-sequenced behind the purchase itself.
        // ───────────────────────────────────────────────────────────────────────────
        'NS-hub|2' => [
            [
                'stage_number'       => 1,
                'title'              => "Twenty Minutes",
                'objective_text'     => "Knuckle called ahead. Patch has a standard resonance dampener waiting — and, apparently, an opinion about how you got here.",
                'dialogue'           => [
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s5_p1_l1.mp3', 'text' => "Patch's station is an old maintenance dig buried beneath North Spokane — exposed pipes, concrete walls, bundles of cable disappearing into the dark like roots. Nothing here was built to be lived in. Somebody clearly changed their mind anyway: plants grow under grow-lamps in the corner, a kettle simmers on a hotplate that has no business still working, and half a dozen terminals drift lazily through the air, opening and closing to a logic only she seems to track."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s5_p1_l2.mp3', 'text' => "She's got both arms inside an open panel when you arrive, one sleeve rolled up, the other forgotten, dark hair tied back with something that wasn't originally meant for hair."],
                    ['speaker' => 'PATCH',    'audio' => 'patch/chapter_1/c1_s5_p1_l3.mp3', 'text' => "Knuckle called ahead. Said 'dampener, not a consult,' like that's a sentence he gets to finish for me."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s5_p1_l4.mp3', 'text' => "It's just the part. I'm kind of in a hurry."],
                    ['speaker' => 'PATCH',    'audio' => 'patch/chapter_1/c1_s5_p1_l5.mp3', 'text' => "Sure. Course you are."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s5_p1_l6.mp3', 'text' => "The sale's barely finished processing — the case already in your hand — when she does the thing every Doc apparently can't help doing: stops treating you like a transaction and starts treating you like a patient."],
                    ['speaker' => 'PATCH',    'audio' => 'patch/chapter_1/c1_s5_p1_l7.mp3', 'text' => "Huh."],
                    ['speaker' => 'PLAYER_SAID', 'audio' => 'player/chapter_1/c1_s5_p1_l8.mp3', 'text' => "What?"],
                    ['speaker' => 'PATCH',    'audio' => 'patch/chapter_1/c1_s5_p1_l9.mp3', 'text' => "Nothing you need to hear standing up in my doorway. Take the part. Get it seated. Come back when you've got twenty minutes I can actually use."],
                    ['speaker' => 'NARRATOR', 'audio' => 'narrator/chapter_1/c1_s5_p1_l10.mp3', 'text' => "She's already back at the panel, not waiting for you to agree — but she says it again anyway, quieter, like she wants to make sure it landed."],
                    ['speaker' => 'PATCH',    'audio' => 'patch/chapter_1/c1_s5_p1_l11.mp3', 'text' => "I mean that. Not a threat. Just — twenty minutes. I think I want to see this properly."],
                ],
                'rep_reward'         => 25,
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
                        'field_comms'         => isset($stageData['field_comms']) ? json_encode($stageData['field_comms']) : null,
                        'rep_reward'          => $stageData['rep_reward'],
                        'is_branch'           => $stageData['is_branch'],
                        'branch_options'      => $branchOptions,
                        'referral_doc_id'     => $referralDocId,
                        'referral_text'       => $stageData['referral_text'],
                        'reward_creds'        => $stageData['reward_creds']       ?? 0,
                        'reward_tech_points'  => $stageData['reward_tech_points'] ?? 0,
                        'reward_node_access'  => $stageData['reward_node_access'] ?? null,
                        'reward_lore_key'     => $stageData['reward_lore_key']    ?? null,
                        'codex_thread_key'    => $stageData['codex_thread_key']   ?? null,
                        'node_canvas_id'      => $stageData['node_canvas_id']     ?? null,
                        'minigame_type'       => $stageData['minigame_type']      ?? null,
                    ],
                );
            }
        }
    }
}
