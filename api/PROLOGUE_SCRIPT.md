# CodeCraft — Prologue Script
### The Ghost-Kernel Arc

All dialogue, narrator lines, player choices, and Watcher interrupts for the five-doc prologue chain.  
**Edit this file and hand sections back to update the seeder.**

---

## How the Prologue Flows

```
TUTORIAL → CortexInstall complete
         → WATCHER INTERRUPT #0 (first contact — directs player to Knuckle)
         → QUEST 1: Knuckle / Browne's Addition
         → WATCHER INTERRUPT #1 (after leaving BA-hub)
         → QUEST 2: Veil / Downtown
         → WATCHER INTERRUPT #2 (after leaving DT-hub)
         → QUEST 3: Float / Spokane Valley
         → WATCHER INTERRUPT #3 (after leaving SV-hub)
         → QUEST 4: Axiom / University District
         → WATCHER INTERRUPT #4 (after leaving UD-hub)
         → QUEST 5: Patch / North Spokane
         → PROLOGUE COMPLETE
```

---

## WATCHER INTERRUPTS

These are the Ghost-Kernel's intrusion signals. They appear as corrupted system broadcasts — the player cannot ignore them or dismiss them. They are the only voice that threads all five quests together.

---

### WATCHER INTERRUPT #0 — Post CortexInstall (Tutorial Complete)

*Fires immediately after the player completes the CortexInstall tutorial sequence.*

```
[UNKNOWN_PROCESS: INJECTING]
▓░▓▓░░▓░░▓▓░▓░░▓
...Knuckles...
*HIGH_FREQ_INTERFERENCE*
[SYS_INTEGRITY: FAILING]
[CONTAINMENT: ░░░░░░░░░░] BREACHED
...not...stable...
*SIGNAL DECAY — SOURCE UNKNOWN*
...speak...with...him...
[KERNEL_PANIC]
[MEMORY: CORRUPTING]
...KNUCKLES...
*EAR-SPLITTING RING*
```

---

### WATCHER INTERRUPT #1 — Knuckle → Veil

*Fires when the player leaves BA-hub after completing Knuckle's quest.*

```
[PROCESS: RESUMING]
▓░▓░▓▓░░▓░▓░
...Downtown...
*SIGNAL FRAGMENTING*
[SYS_INTEGRITY: RECOVERING]
...she sees what he cannot...
*HIGH_FREQ_INTERFERENCE*
...Veil...
[KERNEL_PULSE: ACTIVE]
...find...her...
[CONTAINMENT: ░░░░░░░░░░] STABILIZING
```

---

### WATCHER INTERRUPT #2 — Veil → Float

*Fires when the player leaves DT-hub after completing Veil's quest.*

```
[PROCESS: RESUMING]
░▓░▓▓░▓░░▓▓░
...Spokane Valley...
*SIGNAL FRAGMENTING*
[SYS_INTEGRITY: RECOVERING]
...old architecture...she knows it...
*HIGH_FREQ_INTERFERENCE*
...Float...
[KERNEL_PULSE: ACTIVE]
...the salvager...
[CONTAINMENT: ░░░░░░░░░░] STABILIZING
```

---

### WATCHER INTERRUPT #3 — Float → Axiom

*Fires when the player leaves SV-hub after completing Float's quest.*

```
[PROCESS: RESUMING]
▓▓░░▓░▓▓░░▓░
...University District...
*SIGNAL FRAGMENTING*
[SYS_INTEGRITY: RECOVERING]
...they have been waiting...
*HIGH_FREQ_INTERFERENCE*
...Axiom...
[KERNEL_PULSE: ACTIVE]
...they already know...
[CONTAINMENT: ░░░░░░░░░░] STABILIZING
```

---

### WATCHER INTERRUPT #4 — Axiom → Patch

*Fires when the player leaves UD-hub after completing Axiom's quest.*

```
[PROCESS: RESUMING]
░░▓▓░▓░░▓▓░▓
...North Spokane...
*SIGNAL FRAGMENTING*
[SYS_INTEGRITY: RECOVERING]
...under the grid...
*HIGH_FREQ_INTERFERENCE*
...Patch...
[KERNEL_PULSE: ACTIVE]
...they hear everything...
[CONTAINMENT: ░░░░░░░░░░] STABILIZING
```

---

---

## QUEST 1 — The Climate Override
**Contractor:** Knuckle  
**District:** Browne's Addition  
**Target Node:** BA-v14  
**Minigame:** DISCONNECT_LAYER

---

### STAGE 1 — Find Knuckle

**Objective (shown in quest log):**
> Your system just tried to melt itself. Something got in — something fast — and your deck is still hemorrhaging noise.
>
> The message kept repeating the same fragment before your screen went dark: Knuckles.
>
> Get to the BA-Hub. If anyone in this district knows what just happened to your rig, it's him.

---

**Dialogue:**

**NARRATOR**
> Knuckle's node renders as a cramped, low-ceiling space — walls patched together from stolen network architecture, the seams still visible where different systems were forced to talk to each other. Medical readouts float at chest height, most of them running amber. His avatar is a hulking asymmetrical frame, shoulders built wide enough to suggest he wrote them that way deliberately — two floating diagnostic arms extending from the torso like a surgeon who got tired of only having two hands. He doesn't look up when you walk in. One of the arms is already pointed at you, pulling your signal before you've said a word. The readout it throws onto the wall comes back red. He still doesn't look up.

**KNUCKLE**
> Close the door. Don't talk yet.
>
> Your deck is throwing noise all over my bandwidth.

**NARRATOR**
> He reaches into a rendered equipment rack — the kind of node clutter that takes years to accumulate, layer over layer of tools that were never cleaned up — and pulls a handheld scanner into one of his diagnostic arms. He runs it along your rig housing slowly. In SPLICE, the scan looks like a wound assessment: your architecture lights up in segments and most of them come back wrong. The other diagnostic arm catches the readout mid-air and holds it open where he can study it without moving his head. He stays like that for a long moment. Then he sets the scanner down, collapses the readout with a gesture, and a burner cigarette materializes between two of his fingers — a personal touch someone went to the trouble of scripting into his avatar. He takes a long drag. The smoke even renders.

**KNUCKLE**
> That patch didn't come from any doc I know. The architecture's wrong — it's old. Pre-collapse framework, compressed into something that shouldn't fit inside a modern rig.
>
> And it's not finished. Whatever got into your system is still... settling in.

**PLAYER CHOICE**
- `[EXHAUSTED]` "Just tell me how to get it out."
- `[COLD]` "Is it going to kill me?"
- `[PANICKED]` "What do you mean it's not finished?"

**NARRATOR**
> He's not looking at you. He's watching the smoke dissolve into the node's recycled air.

**KNUCKLE**
> You can't get it out. Not here, not with anything I have. And I've been doing this a long time.
>
> Here's what I can tell you: it's not hostile. Not to you, anyway. Whatever it is, it came in deliberate. Someone wrote this specifically to sit inside a runner's rig without tearing it apart.
>
> My advice? Keep moving. Keep working. I've seen corruption like this go dormant in runners who went quiet. You don't want that — dormant means it's waiting for something. Active means it's still deciding.
>
> I've got a job. Nothing complicated. You run it, I keep the diagnostics up and tell you what I find. Deal?

---

### STAGE 2 — Deploy DISCONNECT_LAYER

**Objective (shown in quest log):**
> Knuckle doesn't have answers — he has work. A residential block in Browne's Addition is locked at 50 degrees, grid-capped to redirect power to corporate sectors.
>
> He's handed you a DISCONNECT_LAYER exploit. Get to node BA-v14 and strip the system-governor. Give those people their heat back.
>
> [WARNING] — Your rig is still leaking. Something inside your system is fighting for bandwidth during every operation. Watch your stability.

*No dialogue — minigame only.*

---

### STAGE 3 — Report Back

**Objective (shown in quest log):**
> The layer is down. The block is warming up. Residents are flooding local channels — for once, something actually worked.
>
> Get back to Knuckle at the BA-Hub and collect your cut.

---

**Dialogue:**

**NARRATOR**
> The block's heat signatures are already climbing on a wall display when you step back into the node — a dozen residential units rendered as thermal columns, all of them ticking upward in slow green increments. Knuckle isn't looking at it. One of his diagnostic arms is already extended in your direction before the door geometry finishes loading behind you, scanner live, pulling your rig's signal the moment you're in range. He reads the output without a word. The arm retracts.

**KNUCKLE**
> It spiked twice while you were at the node. Whatever's in you reacted to the grid interference. It's not fighting the work — it's interested in it.
>
> I don't know what that means yet. But it's something.

**PLAYER CHOICE**
- `[FLAT]` "Credits. Then I'm gone."
- `[UNCERTAIN]` "How long before you know more?"
- `[TIRED]` "I just want one day where my rig isn't on fire."

**NARRATOR**
> A transfer chip materializes on the bench between you — the standard end of a clean transaction in his node. No ceremony. One of the diagnostic arms nudges it forward an inch, like even that much is more than the job deserved.

**KNUCKLE**
> Look, I ran the diagnostics twice. There's no trace data — nothing I can pull that tells me what's sitting in you or where it came from. Without that I'm just guessing, and I don't get paid to guess.
>
> You did the work. I paid you. That's the end of it.
>
> Now get out of my wagon. Your rig's been screaming on every frequency since you walked in and the last thing I need is the Architects sniffing around this node because you can't keep your signal quiet.

*→ Watcher Interrupt #1 fires when player leaves BA-hub.*

---

---

## QUEST 2 — The Downtown Smoothing Protocol
**Contractor:** Veil  
**District:** Downtown  
**Target Node:** DT-v8  
**Minigame:** FLUSH_BUFFER

---

### STAGE 1 — Find Veil

**Objective (shown in quest log):**
> The signal hit your system the moment you left BA-hub — the same corrupted architecture that's been living in your rig, resolving this time to two words: Downtown. Veil.
>
> Get to the DT-Hub. If she's already tracking your trace — and she probably is — she already knows you're coming.

---

**Dialogue:**

**NARRATOR** `narrator/veil/v_s1_l1.mp3`
> Veil's node renders as a quiet, sprawling workspace — warm lights hanging above exposed conduits and maintenance terminals, every surface occupied by active projects and half-finished repairs. Massive windows overlook the distant glow of the Frequency, rain drifting lazily against the glass while status boards and infrastructure maps float in the air like constellations. Nothing here is decorative. Every cable is labeled. Every tool has a place. The entire node carries the strange feeling of a station that was supposed to close years ago, but never did.
>
> Her avatar stands among it all with the same quiet practicality. A woman somewhere between young and old, dark hair loosely tied back, wearing a long coat lined with pockets and utility straps instead of armor. The few visible augmentations beneath her skin are subtle and functional, easy to miss unless you know what to look for. She isn't watching the doorway when you arrive. She's watching six different terminals at once, fingers moving through diagnostic windows while old requests collapse and new ones appear faster than they disappear.
>
> She notices you immediately.
>
> She just doesn't stop working.
>
> One of the displays briefly shifts to your signal. Something in the results catches her attention. Her eyes linger on it for half a second longer than they should.
>
> Then she quietly reaches over, mutes an alarm somewhere out in the district, and finally looks up.
>
> Not startled.
>
> Not concerned.
>
> Just tired.
>
> As though strange problems stopped surprising her a very long time ago.

**VEIL** `veil/v_s1_l2.mp3`
> Hm.
>
> That's unusual.
>
> Sit down.

**NARRATOR** `narrator/veil/v_s1_l3.mp3`
> Another alarm flashes amber beside her. She dismisses it with a flick of her hand before giving you her full attention. A layered geometry unfolds between you — your rig rendered as structure instead of circuitry. From here, the corruption doesn't look random.
>
> It looks built.

**VEIL** `veil/v_s1_l4.mp3`
> Most failures are ugly.
>
> This isn't ugly.
>
> Somebody spent a great deal of time teaching this thing how to behave.
>
> Which means either I'm looking at something brilliant...
>
> Or something dangerous.

**PLAYER CHOICE**
- `[GUARDED]` "Can you remove it?"
- `[IRRITATED]` "Everybody keeps saying that."
- `[DIRECT]` "What's it doing?"

**NARRATOR** `narrator/veil/v_s1_l5.mp3`
> She doesn't answer immediately. Another request appears. Another disappears. She watches your architecture while her hands continue working.

**VEIL** `veil/v_s1_l6.mp3`
> I don't know.
>
> Which means I don't touch it.
>
> Guessing breaks things.
>
> But there is something I'd like to test.

**NARRATOR** `narrator/veil/v_s1_l7.mp3`
> A section of Downtown unfolds between you. One node glows red.

**VEIL** `veil/v_s1_l8.mp3`
> Recursive ghost-signal.
>
> Been degrading infrastructure for weeks.
>
> Every runner I've sent near it cooked their deck.
>
> Yours...
>
> Probably won't.
>
> Similar structures tend to tolerate each other.
>
> Flush it.
>
> Then we'll both know something.

---

### STAGE 2 — Flush the Buffer

**Objective (shown in quest log):**
> Veil's interested in your corruption, but not enough to gamble on it. A recursive ghost-signal at node DT-v8 has been degrading local infrastructure for weeks. Clean rigs don't survive the feedback.
>
> Yours isn't clean.
>
> Get to DT-v8 and deploy FLUSH_BUFFER.
>
> [WARNING] — Your stability will drop rapidly. Keep the signal contained.
>
> Don't inspect the data.
>
> Just flush it.

*No dialogue — minigame only.*

---

### STAGE 3 — Report Back

**Objective (shown in quest log):**
> The signal is gone. Your rig is still smoking. Return to Veil at the DT-Hub and collect what she owes you.

---

**Dialogue:**

**NARRATOR** `narrator/veil/v_s3_l1.mp3`
> The status boards above Veil's workstation are running green when you step back into the node. One district map has vanished entirely. Another queue has shortened. Somewhere, a problem has stopped being a problem.
>
> Veil notices your signal immediately.
>
> This time, she looks up before you say anything.

**VEIL** `veil/v_s3_l2.mp3`
> The node stabilized.
>
> No resistance.
>
> No feedback.
>
> It just...
>
> opened.

**NARRATOR** `narrator/veil/v_s3_l3.mp3`
> She doesn't sound pleased.

**VEIL** `veil/v_s3_l4.mp3`
> Which means I was wrong.
>
> I thought whatever's inside you resembled the loop.
>
> It doesn't.
>
> The loop recognized it.

**PLAYER CHOICE**
- `[UNSETTLED]` "Recognized what?"
- `[FOCUSED]` "So what does that mean?"
- `[TIRED]` "Am I dying or not?"

**NARRATOR** `narrator/veil/v_s3_l5.mp3`
> A layered model of your architecture unfolds between you again. Veil studies it in silence. One hand moves automatically, dismissing an amber alert before it can become a red one.

**VEIL** `veil/v_s3_l6.mp3`
> I don't know.
>
> And I don't guess.
>
> Guessing breaks things.
>
> Whatever's in you...
>
> It isn't random.
>
> It isn't damaged.
>
> And it isn't finished.

**NARRATOR** `narrator/veil/v_s3_l7.mp3`
> The last sentence seems to bother her more than the others.

**VEIL** `veil/v_s3_l8.mp3`
> That's all I've got.

**NARRATOR** `narrator/veil/v_s3_l9.mp3`
> Another request appears.
>
> Another alarm follows.
>
> Somewhere in the district, something else needs attention.

**VEIL** `veil/v_s3_l10.mp3`
> Sorry.

**NARRATOR** `narrator/veil/v_s3_l11.mp3`
> She doesn't sound apologetic.
>
> Just tired.

**VEIL** `veil/v_s3_l12.mp3`
> I'd rather give you an answer.
>
> But I'd rather give you the right answer.
>
> And I don't have one.

**NARRATOR** `narrator/veil/v_s3_l13.mp3`
> One of the status boards flashes red. Veil is already reaching for it.

**VEIL** `veil/v_s3_l14.mp3`
> If it changes...
>
> You'll know before I do.
>
> Try not to break anything on your way out.

**NARRATOR** `narrator/veil/v_s3_l15.mp3`
> And just like that, you're no longer the most urgent thing in the room.
>
> Veil has already returned to keeping the lights on.

*→ Watcher Interrupt #2 fires when player leaves DT-hub.*

---

---

## QUEST 3 — The Drift-Anchor Retrieval
**Contractor:** Float  
**District:** Spokane Valley  
**Target Node:** SV-v9  
**Minigame:** TOXIC_SOAK

---

### STAGE 1 — Find Float

**Objective (shown in quest log):**
> Veil's lead points to Spokane Valley. Your deck is running at critical temperature — whatever is inside your system isn't just leaking anymore, it's vibrating. Every few seconds your HUD stutters, your avatar rubber-bands, and you lose a full second of movement.
>
> Get to the SV-Hub before your rig gives out entirely.

---

**Dialogue:**

**NARRATOR**
> Float's repair bay is a converted shipping container on the edge of the Valley grid, half-buried in a chain-link compound. She's under a raised panel unit when you arrive, one arm elbow-deep in the chassis. She doesn't come out.

**FLOAT**
> I know why you're here. Veil sent a signal ahead. Said you were carrying something old.
>
> Hand me the torque driver. The flat one. Don't touch anything else.

**NARRATOR**
> She emerges eventually, wiping her hands on a rag that's already past the point of usefulness. Her eyes go immediately to your rig. She studies it the way a salvager studies a wreck — not looking for damage, looking for value.

**FLOAT**
> Pre-collapse architecture running inside a live rig. On the Frequency. In this district.
>
> I've seen fragments — bits of it pulled from dead nodes, compressed into storage media that won't interface with modern hardware. Never seen it active. Never seen it hosted.
>
> You want to know what it is or you want to know what it's worth?

**PLAYER CHOICE**
- `[CAREFUL]` "What it is. Start there."
- `[PRAGMATIC]` "Both. In that order."
- `[BLUNT]` "I want it out of me."

**FLOAT**
> Can't get it out. That's not how this architecture works — it doesn't sit on top of a system, it integrates. Give it long enough and you won't be able to tell where it ends and your rig begins.
>
> But I can read it. Not here — I need data from a sink node first. There's a place in the Valley where the grid dumps its volatile processes. What pools there reacts to this kind of old code. You soak it, I can decode the signatures.
>
> It'll cost you something. Not credits.

---

### STAGE 2 — Soak the Drift-Anchor

**Objective (shown in quest log):**
> Float doesn't want to fix you. She wants to harvest what's rotting inside you. There's a data-sink at node SV-v9 — a submerged relay where the grid dumps its most volatile, discarded processes. Float needs what's pooled there.
>
> Get to SV-v9 and hold position. Let your system absorb the toxic data until the anchor is full.
>
> [WARNING] — The Drift is actively rewriting anything it touches. Your stability will drain continuously. You cannot fight it — you can only endure it.

*No dialogue — minigame only.*

---

### STAGE 3 — Report Back

**Objective (shown in quest log):**
> You're still standing. Barely. Get back to Float at the SV-Hub and let her drain what you're carrying.

---

**Dialogue:**

**FLOAT**
> You held longer than I expected.
>
> Sit down before you fall down.

**NARRATOR**
> She runs the readout twice. Doesn't explain what she's seeing. The silence isn't unfriendly — it's the kind of quiet that means something is actually interesting.

**FLOAT**
> The data you soaked — it reacted to what's in you. Bonded to it. That's not a coincidence. Whatever is running inside your rig has a specific affinity for this era of architecture. Like it's looking for something.
>
> There's someone at the University District who catalogues pre-collapse systems. Goes by Axiom. He'll want to see what you're carrying — and he'll have better answers than me.

**PLAYER CHOICE**
- `[GRATEFUL]` "Thank you. I mean it."
- `[WORN OUT]` "How many more people do I have to see?"
- `[RESOLUTE]` "I'll find Axiom."

**FLOAT**
> As many as it takes. You're carrying something that shouldn't exist anymore.
>
> That either ends very badly or very interestingly. My money's on both.

*→ Watcher Interrupt #3 fires when player leaves SV-hub.*

---

---

## QUEST 4 — The Deep Archive Extraction
**Contractor:** Axiom  
**District:** University District  
**Target Node:** UD-v17  
**Minigame:** ARCHIVE_EXTRACTION

---

### STAGE 1 — Find Axiom

**Objective (shown in quest log):**
> Another fragment of the signal resolved the moment you left Spokane Valley. Same architecture. Same source.
>
> This time it came through clean — two words and a coordinate: University District. Axiom.
>
> Get to the UD-Hub. Whoever Axiom is, the signal already knew where to send you.

---

**Dialogue:**

**NARRATOR**
> Axiom's node renders as an impossible archive — shelves stretching upward into darkness, disappearing long before they reach a ceiling. Folders drift through the air in slow, deliberate orbits, endlessly reorganizing themselves around patterns only he seems to understand. Forgotten protocols, dead social spaces, and centuries of accumulated thought sit preserved behind translucent partitions. Nothing here feels abandoned. Nothing feels hurried either.
>
> An older man sits at the center of it all behind a desk assembled more from memory than furniture. His silver hair is neatly kept, his clothes belong to no obvious era, and his posture carries the quiet confidence of someone who has spent his life teaching others. He doesn't notice you immediately. He's reading.

**AXIOM**
> "Ah. One moment, if you please. I'd hate to lose my place."

**NARRATOR**
> Several folders shift overhead. Another disappears into the shelves. Only then does he close the file and finally look up. His eyes settle on you. Then your rig. Then back to you. A faint smile touches the corner of his mouth.

**AXIOM**
> "Well. That's curious. You do leave rather a trail behind you."

**PLAYER CHOICE**
- `[TIRED]` "Everybody keeps saying that."

**AXIOM**
> "Mm. I imagine they do. Please. Sit. You're distracting several centuries."

**NARRATOR**
> A chair materializes opposite the desk. You don't remember it being there before. He folds his hands and studies you. Not your rig. You. Like a librarian trying to remember where he's seen a particular book before.

**AXIOM**
> "Curious."

**NARRATOR**
> Somewhere above, a folder changes position.

**AXIOM**
> "Your architecture references systems that no longer exist. Which happens. Memory, after all, can be untidy."

**NARRATOR**
> Another folder drifts overhead.

**AXIOM**
> "It also references systems that never existed."

**NARRATOR**
> He stops.

**AXIOM**
> "Well. That is unusual."

**PLAYER CHOICE**
- `[DIRECT]` "Do you know what it is?"

**AXIOM**
> "Mm. No, I'm afraid I don't. Though I appreciate your optimism."

**NARRATOR**
> He leans back slightly, considering.

**AXIOM**
> "Understanding a thing and repairing a thing are not always the same profession. Mine has always been understanding."

**NARRATOR**
> One of the folders settles into place behind him.

**AXIOM**
> "There is an archive beneath the University District. Construction records. Early documentation. I have spent eleven years attempting to access it. It dislikes me."

**PLAYER CHOICE**
- `[AMUSED]` "The archive dislikes you?"

**AXIOM**
> "Mm. Quite passionately. Fortunately, it appears to dislike things that make sense. Which brings us to your rather exceptional circumstances. Retrieve the packet. In exchange, I will tell you everything I know."

---

### STAGE 2 — Extract the Archive Packet

**Objective (shown in quest log):**
> Axiom has spent eleven years trying to access an archive beneath the University District. Something in its architecture refuses to engage with clean systems — it reads them as hostile.
>
> Yours doesn't read as clean.
>
> Get to node UD-v17 and extract the data packet Axiom needs.
>
> [WARNING] — The archive ICE will spike. Your rig will respond in kind. Keep working.
>
> Axiom said it dislikes things that make sense. Prove it right.

*No dialogue — minigame only.*

---

### STAGE 3 — Report Back

**Objective (shown in quest log):**
> The packet is out. Axiom is waiting. Return to the UD-Hub and collect what was promised.

---

**Dialogue:**

**NARRATOR**
> When you return, the archive has changed. New folders drift among the old ones. Entire shelves have rearranged themselves. Thousands of tiny adjustments made by someone incapable of leaving knowledge alone. Axiom isn't reading when you arrive. He's waiting.

**AXIOM**
> "Ah. You survived. Good. I dislike unresolved stories."

**NARRATOR**
> The packet unfolds into the air between you. Thousands of pages flicker past in seconds. Axiom watches quietly. Patiently. Then he sighs.

**AXIOM**
> "Hm."

**PLAYER CHOICE**
- `[FOCUSED]` "What does it say?"

**AXIOM**
> "Less than I hoped. More than I expected."

**PLAYER CHOICE**
- `[FLAT]` "That's not an answer."

**AXIOM**
> "No. It isn't."

**NARRATOR**
> He folds his hands atop the desk.

**AXIOM**
> "I study history. History is comforting. Events happen. Time passes. People assign meaning. But history only speaks after things are finished."

**NARRATOR**
> His eyes drift toward your rig.

**AXIOM**
> "Whatever is inside you... it is still writing itself."

**PLAYER CHOICE**
- `[FLAT]` "So you don't know."

**AXIOM**
> "Correct."

**NARRATOR**
> No embarrassment. No frustration. Just honesty.

**AXIOM**
> "Knuckle sees bodies. Veil sees systems. Float sees machines."

**NARRATOR**
> Another folder settles into place above him.

**AXIOM**
> "I see stories. And stories are difficult to understand while one is still living inside them."

**PLAYER CHOICE**
- `[BITTER]` "Nobody knows anything."

**NARRATOR**
> For the first time since you arrived — Axiom smiles.

**AXIOM**
> "Ah. I wouldn't say that. We know quite a lot, actually. We know it isn't killing you. We know it adapts. We know it remembers things nobody expected. And we know it possesses remarkable patience. Which, I confess, is more than I can say for most people."

**PLAYER CHOICE**
- `[FLAT]` "So that's it?"

**NARRATOR**
> Axiom sits quietly for a moment. Somewhere above, another folder settles into place. He watches it with the satisfaction of someone putting a book back where it belongs.

**AXIOM**
> "Mm. For now, I believe so."

**PLAYER CHOICE**
- `[FLAT]` "That's not very reassuring."

**AXIOM**
> "No. It isn't."

**NARRATOR**
> He folds his hands atop the desk.

**AXIOM**
> "The world has an unfortunate habit of refusing to reveal itself all at once. If it did, I suspect my profession would've become terribly boring centuries ago."

**NARRATOR**
> He smiles softly.

**AXIOM**
> "We spend our lives believing understanding arrives all at once. It rarely does. More often, it arrives one conversation at a time."

**PLAYER CHOICE**
- `[QUIET]` "And if it doesn't?"

**NARRATOR**
> The old man considers the question carefully.

**AXIOM**
> "Then we live with what we know. And remain grateful for what we don't."

**NARRATOR**
> He rises from his desk and carefully returns the file he'd been reading to the endless shelves above. Several folders shift to accommodate it. Satisfied, he turns back to you.

**AXIOM**
> "You've been carrying questions for quite some time. I imagine they must be heavy. Go get some rest."

**NARRATOR**
> And just like that, the conversation is over. Not because he is finished with you. Not because he lacks answers. Simply because, in Axiom's view, there is nothing more to say today.

*→ Watcher Interrupt #4 fires when player leaves UD-hub.*

---

---

## QUEST 5 — The Ghost-Kernel Calibration
**Contractor:** Patch  
**District:** North Spokane  
**Target Node:** NS-v13  
**Minigame:** CALIBRATION_TETHER

---

### STAGE 1 — Find Patch

**Objective (shown in quest log):**
> Another fragment hit your system leaving the University District.
>
> Same source. Same impossible architecture.
>
> This time it resolved into something cleaner: North Spokane. Patch.
>
> You barely remember leaving the University District. Your deck is running at critical temperature. Your HUD freezes for seconds at a time. Entire moments arrive out of order. Sometimes your avatar moves before you tell it to. Sometimes it doesn't move at all.
>
> Whatever is inside your system isn't slowing down.
>
> And neither is the thing trying to remove it.
>
> Every hour hurts more than the last.
>
> Get to the NS-Hub before your rig gives out completely.

---

**Dialogue:**

**NARRATOR**
> Patch's node renders as an old maintenance station buried beneath North Spokane — exposed pipes, concrete walls and bundles of cable disappearing into the dark like roots. Nothing here was designed to be lived in.
>
> Over the years, someone changed their mind.
>
> Plants grow beneath artificial lamps. Half-finished electronics cover every available surface. A kettle simmers quietly on a hotplate that somehow still works. Several terminals drift lazily through the air around the room, opening and closing according to a logic known only to their owner.
>
> The whole place feels less like a workshop and more like somewhere somebody forgot to leave.

**NARRATOR**
> Her avatar is compact and practical, dressed in patched work clothes with tools hanging from her belt that never seem to stay in the same place twice. Dark hair tied back with whatever happened to be closest. One sleeve rolled up, the other forgotten.
>
> She's hanging upside down beneath a maintenance platform.
>
> Somehow.
>
> She notices you.
>
> Keeps working.
>
> You take another step.
>
> Your vision smears.
>
> The room jumps sideways.
>
> Something screams through your audio feed.
>
> Your knees buckle.
>
> By the time the node catches up again, you're on the floor.

**PATCH**
> Oh.
>
> That's significantly worse than I expected.

**NARRATOR**
> She's beside you immediately. One of the floating terminals abandons whatever it was doing and starts throwing diagnostics into the air. Another begins screaming warning tones.
>
> Patch ignores both.

**PATCH**
> Hey.
>
> Stay with me.
>
> You can pass out later.
>
> I'd rather introduce myself first.

**NARRATOR**
> She helps you upright.
>
> One look at your architecture and her expression changes.
>
> Not fear.
>
> Not confusion.
>
> Professional irritation.
>
> The sort reserved for problems that refuse to explain themselves.

**PATCH**
> Wow.
>
> That's rude.

**PLAYER CHOICE**
- `[DESPERATE]` "Please tell me somebody knows what's happening."

**PATCH**
> Maybe.
>
> Probably not.
>
> But maybe.

**NARRATOR**
> She circles you once, studying the readouts hanging around your rig. Her eyes move faster than the terminals.
>
> She frowns.

**PATCH**
> I need calibration packages from NS-v13.
>
> Volatile sub-routines.
>
> Ugly things.
>
> Nobody wants them.
>
> Which makes them my responsibility.
>
> Bring them back.
>
> I'll see what I can see.

---

### STAGE 2 — Calibration Tether

**Objective (shown in quest log):**
> Patch needs volatile calibration packages from node NS-v13.
>
> Nobody wants them.
>
> Which, according to Patch, makes them her problem.
>
> Retrieve the packages and bring them back to her node.
>
> [WARNING] — The sub-routines are unstable and actively resist containment. Every packet carried will drain your stability.
>
> Your own system is unstable.
>
> Move quickly.

*No dialogue — minigame only.*

---

### STAGE 3 — Report Back

**Objective (shown in quest log):**
> The packages are secured.
>
> Your deck isn't.
>
> Return to Patch and collect your payment.
>
> Five docs. Five jobs. No answers.
>
> And whatever is inside you is only getting louder.

---

**Dialogue:**

**NARRATOR**
> The kettle whistles when you return.
>
> Patch doesn't.
>
> She's sitting cross-legged on the floor surrounded by open terminals and handwritten notes.
>
> One of the plants has somehow acquired a screwdriver.
>
> She doesn't seem surprised.

**PATCH**
> Welcome back.
>
> Oh.
>
> You survived.
>
> Good.

**NARRATOR**
> Several terminals wake up when your signal enters the room.
>
> They begin comparing diagnostics.
>
> Patch studies them.
>
> Frowns.
>
> Studies them again.
>
> Then rubs her eyes.

**PATCH**
> Hm.
>
> Hm.

**PLAYER CHOICE**
- `[DIRECT]` "Just tell me what's wrong with me."

**PATCH**
> No idea.

**PLAYER CHOICE**
- `[FLAT]` "Seriously?"

**PATCH**
> Mm.
>
> Sorry.

**NARRATOR**
> She doesn't sound embarrassed.
>
> Just disappointed.
>
> Not in you.
>
> In the data.

**PATCH**
> Most things make sense eventually.
>
> Dependency.
>
> Attachment.
>
> Addiction.
>
> Habit.
>
> Comfort.
>
> People are wonderfully predictable.

**PLAYER CHOICE**
- `[FLAT]` "And this isn't?"

**PATCH**
> No.
>
> Which is annoying.
>
> Interesting.
>
> But annoying.

**PLAYER CHOICE**
- `[BITTER]` "Nobody knows anything."

**PATCH**
> Ah.
>
> I wouldn't say that.
>
> I think everybody knows something.
>
> Which is considerably more inconvenient.

**PLAYER CHOICE**
- `[FLAT]` "Meaning?"

**PATCH**
> Meaning nobody gets to be entirely correct.
>
> Terrible system.
>
> I've filed several complaints.

**PLAYER CHOICE**
- `[DRY]` "Did anyone answer?"

**PATCH**
> No.
>
> Which rather proves the point.

**NARRATOR**
> One of the terminals chirps.
>
> Patch reaches over and removes a screwdriver from a potted plant.

**PATCH**
> Honestly, I still haven't figured out how that keeps happening.

**PLAYER CHOICE**
- `[DRY]` "That's your professional advice?"

**PATCH**
> Mm.
>
> Disappointing, isn't it?
>
> Get some sleep.
>
> Eat something.
>
> Try not to panic.
>
> Humanity's been using that strategy for thousands of years.
>
> Seems rude not to continue the tradition.

**NARRATOR**
> And just like that she's already halfway back into whatever she was doing before you arrived.
>
> Not because she doesn't care.
>
> Not because she's dismissing you.
>
> Simply because she's reached the edge of what she knows.
>
> And unlike most people—
>
> Patch has made peace with that.

---

*— END OF PROLOGUE —*
