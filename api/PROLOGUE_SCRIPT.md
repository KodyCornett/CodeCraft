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
> There's someone at the University District who catalogues pre-collapse systems. Goes by Axiom. They'll want to see what you're carrying — and they'll have better answers than me.

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
> Float's lead puts you in the University District. You don't remember the transit — your system dropped out somewhere between the Valley and the campus and when your HUD rebooted you were already here.
>
> Find the UD-Hub. Axiom has apparently been tracking your signature. They already know what you're carrying.

---

**Dialogue:**

**NARRATOR**
> Axiom Systems occupies a clean sub-level space beneath the University grid infrastructure. No clutter. No visible hardware. Just white surfaces, indirect light, and a terminal interface so precise it makes your rig feel like a salvage wreck by comparison.
>
> Axiom doesn't introduce themselves. They're already reading your output on a display you can't see.

**AXIOM**
> You are leaking 4.3 terabytes of uncompressed pre-collapse data per hour. You have been doing this for approximately nine days.
>
> Sit down. You are making my instruments anxious.

**NARRATOR**
> They pull up a structural rendering of your rig's current state. The corruption doesn't look like corruption from here. It looks like an installation — deliberate, layered, patient.

**AXIOM**
> The architecture inside your system predates the Frequency by eleven years. It should not be functional in a current-gen environment. The fact that it is suggests someone spent considerable time adapting it.
>
> I have been searching for active instances of this code for three years. You walked in carrying one.
>
> I require something from the University Archives in exchange for what I know. This is not negotiable.

**PLAYER CHOICE**
- `[CURIOUS]` "What do you know?"
- `[CAUTIOUS]` "What's in the Archives?"
- `[RESIGNED]` "Fine. What do you need?"

**AXIOM**
> The Archives contain a data packet from the original SPLICE construction logs. The ICE protecting it was written to stop clean systems — systems with legible, classifiable signatures.
>
> Your rig reads as debris. The ICE will not recognise you as a threat.
>
> Retrieve the packet and I will tell you what is inside you, where it came from, and what it is looking for.

---

### STAGE 2 — Extract the Archive Packet

**Objective (shown in quest log):**
> Axiom needs a deep-packet from the University Archives at node UD-v17. The archive is locked behind the best ICE in the city — too clean, too precise for any normal operator to touch without triggering a cascade failure.
>
> Your rig is so corrupted that the ICE can't classify you. You read as background noise. System debris.
>
> Get to UD-v17 and extract the packet.
>
> [WARNING] — Whatever is inside your system will try to generate false alarms while you work. The ICE will spike. Your own security layer will scream at you. Treat it as background noise — it isn't real.

*No dialogue — minigame only.*

---

### STAGE 3 — Report Back

**Objective (shown in quest log):**
> The packet is yours. The data is clean. Your processor is not.
>
> Get back to Axiom at the UD-Hub and deliver what they asked for.

---

**Dialogue:**

**AXIOM**
> The packet is intact. Thank you.
>
> What you are carrying is called a Ghost-Kernel. It is not a virus. It is not malware. It is a person.
>
> Not a full person — a compressed instance. A cognitive framework, preserved in pre-collapse architecture and adapted to run inside a host rig without that host's knowledge or consent. Someone's consciousness, or a significant fragment of it, is running inside your system.

**PLAYER CHOICE**
- `[DISTURBED]` "Whose consciousness?"
- `[FOCUSED]` "How do I get it out without killing it?"
- `[SHAKEN]` "It's been in there since the beginning?"

**AXIOM**
> I don't know whose. The packet will take time to decode. What I can tell you is this: Ghost-Kernels don't travel passively. Someone loaded it onto the Frequency and aimed it at you. That targeting was deliberate.
>
> There is a technician in North Spokane. Goes by Patch. They work in the Under-Grid — infrastructure layer, below the public Frequency. They will know how to isolate and communicate with what is inside you.
>
> They will already know you are coming. They always do.

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
> Axiom's lead puts you in North Spokane. Your deck is barely functional. The virus is forcing a recursive loop, burning cycles trying to lead you somewhere specific — somewhere it recognises.
>
> Get to the NS-Hub. Patch communicates through a shielded remote terminal. They won't let you inside.

---

**Dialogue:**

**NARRATOR**
> The NS-Hub terminal is a grille-covered access panel mounted into a concrete wall at the edge of the Under-Grid maintenance corridor. There is no door. No signage. A single line of green text cycles on the screen:
>
> `> AWAITING INPUT`

**PATCH**
> `> You took longer than I expected.`
> `> Your rig is broadcasting on seventeen simultaneous frequencies. You have been for eight days.`
> `> I have been listening to all of them.`

**NARRATOR**
> There is a long pause. More text appears.

**PATCH**
> `> The Ghost-Kernel inside your system has been trying to establish a handshake with the Under-Grid infrastructure since it activated. It is using your rig as a bridge.`
> `> It is looking for a specific node. One that does not appear on any current grid map.`
> `> I know where it is.`
> `> I will not tell you for free.`

**PLAYER CHOICE**
- `[WARY]` "What do you want?"
- `[DIRECT]` "Tell me about the node first."
- `[EXHAUSTED]` "Everyone wants something. Fine."

**PATCH**
> `> There is a cache of volatile sub-routines at NS-v13. Unstable code — it fights anything clean that touches it. I cannot retrieve it safely.`
> `> You can. Whatever is inside you is too broken to register as a threat. The sub-routines will let you carry them.`
> `> Move them to my drop-box. I will tell you everything I know about the node the Kernel is searching for.`
> `> Do not read the sub-routines while you carry them. Do not open the packets. Move them and nothing else.`
> `> The Kernel will try to help you. Let it.`

---

### STAGE 2 — Haul the Sub-Routines

**Objective (shown in quest log):**
> Patch won't touch your rig — you're too dangerous to bring inside. But they have work that's equally dangerous: a cache of volatile sub-routines at node NS-v13, code so unstable it actively fights to overwrite anything it touches.
>
> Get to NS-v13 and move the sub-routines to Patch's drop-box. You carry them because you're the only thing in this city broken enough to not notice the difference.
>
> [WARNING] — These sub-routines will fight back. Every packet in your buffer will actively drain your stability. The faster you move them, the less damage they do.

*No dialogue — minigame only.*

---

### STAGE 3 — Collect Your Cut

**Objective (shown in quest log):**
> The sub-routines are in the drop-box. Patch has already severed the connection — you aren't getting a debrief, just the credits.
>
> Return to the NS-Hub terminal and collect your payout.
>
> You've run the full circuit. Five docs. Five jobs. You're more broken than when you started, and you still have no idea what's living in your system. But whatever it is — it's been with you since the beginning. And it's still trying to teach you something.

---

**Dialogue:**

**PATCH**
> `> Sub-routines received. Intact.`
> `> The node the Kernel is searching for is called ORIGIN_NULL. It predates the Frequency by eleven years. It was the first node ever connected to what would become SPLICE — a test relay that was officially decommissioned and scrubbed from the public grid index.`
> `> It was not actually scrubbed.`
> `> The Ghost-Kernel was written at ORIGIN_NULL. Whoever preserved it intended it to find its way back.`
> `> I do not know why. I do not know who.`
> `> But the Kernel does.`
> `> You just have to ask it.`

**PLAYER CHOICE**
- `[RESOLVED]` "I'll find ORIGIN_NULL."
- `[UNCERTAIN]` "How do I ask a piece of compressed consciousness something?"
- `[QUIET]` "I think I already know the answer."

**PATCH**
> `> Your credits have been transferred.`
> `> One more thing.`
> `> The Kernel has been broadcasting on the Watcher channel since it activated. If you have not been reading those signals — read them.`
> `> It has been trying to tell you something since the beginning.`
> `> Connection terminated.`

---

*— END OF PROLOGUE —*
