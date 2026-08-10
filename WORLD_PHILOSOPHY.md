# CodeCraft — World Philosophy & Cyber Doc Design Reference
### Master Design Consistency Document — Do Not Deviate
---

## THE SPLICE FREQUENCY

The network in CodeCraft is called **The Splice Frequency**, shortened to **SPLICE**.

> **Terminology rule — enforced across all code, narrative text, comments, and documentation:**
> Any reference to "the Matrix" (from films or Shadowrun) = The Splice Frequency / SPLICE.
> "Jacking in" = connecting to SPLICE. "The network" = SPLICE. "The grid" = SPLICE.
> This applies everywhere. No exceptions.

**SPLICE is not a separate virtual world.** Spokane's population is augmented almost
universally — cyber-eyes, neural mesh, subdermal ports — to the point that the line
between "real" and "networked" has effectively dissolved for most people. SPLICE is a
hidden data frequency layered on top of the real, physical city: a signal riding the same
infrastructure everyone already lives inside, legible only to augments tuned to receive it.

When a runner "jacks in," their body doesn't go anywhere. They're still standing in the
alley, still breathing, still bleeding if you cut them. What changes is perception —
augmented senses start rendering SPLICE data as if it were physically present: avatars,
node architecture, ICE, ports. Two runners standing in the same physical room may be
seeing two entirely different versions of that room, because SPLICE lets people project
how they want to appear on the frequency. A person can read as a chrome-plated wolf to
the network and still be a tired thirty-year-old with bad knees to the naked eye.

This matters because **everything that happens to the player is happening to a real
body.** Damage, exhaustion, pain, sickness — none of it is a metaphor, and none of it
resets when you disconnect.

The Splice browser the player uses is not a browser. It is a direct interface to SPLICE
itself. The URLs are not web addresses. They are node identities inside the frequency.

---

## CORE DESIGN MANDATE

This document is the narrative north star for all quest writing, dialogue, terminal
logs, and narrative design in CodeCraft. Every piece of text the player reads should
serve the central mystery defined here.

**The game's central story:**

> The player is being invaded — not infected, not "hacked" in the ordinary sense, but
> broken into by a person. That person, **the Watcher**, is trapped inside SPLICE itself:
> an actual human mind, pulled into the frequency during a catastrophic test of a
> technology he invented, and unable ever since to get out or feel human again. He is
> trying to reach the player — clumsily, and without full awareness of the damage he's
> causing — because he needs help stopping his own creation from ever being used on
> anyone else.

The player doesn't know any of this in Chapter 1. All they know is that something is
wrong with them — pain, blackouts, missing time, their rig shutting down without
warning — and every Cyber Doc they visit reads the symptoms differently, because none of
them have ever seen anything like it before. The mystery of the early game isn't "what
is this corruption." It's **"why do I feel like I'm dying, and why does no one who's
supposed to know tech have an answer?"**

The five Cyber Docs are not lens-providers in an ideological framework. They are simply
the most skilled technical specialists in the city — and even they can't agree on what
they're looking at.

---

## WRITING WORKFLOW & PROSE STANDARD

**Workflow — script first, then story.** Always write or edit a scene in the chapter's
`_SCRIPT.md` file first (e.g. `api/CHAPTER_1_SCRIPT.md`) — that's the production copy,
tagged for ElevenLabs. Once a scene is locked there, mirror it into the matching
`_STORY.md` read-through, stripped of IDs, audio tags, and FX tags. Read the mirrored
version back top to bottom as prose, not as a checklist, and make whatever changes it
needs to actually flow as a piece of writing. If the read-through exposes a clunky line, an
underwritten beat, or a description that isn't earning its place, fix it in the Script
file — the source of truth — and remirror. Never patch the Story copy in isolation; the two
must stay identical in content, differing only in whether the technical tags are showing.

**Prose standard.** This is written to be read the way a novel is read. The difference
from an actual novel is that the player isn't watching the story happen to someone else —
they're the one living it. That doesn't lower the bar; it raises it. Every line of
narration should hold up to the standard of a published book: specific, sensory, doing
more than one job at a time. Don't write "the room was cold" when you can write what the
cold does to a body, what it looks like, what it implies about who's been meaning to fix
the climate control and never gets around to it. Every location description should tell
you something about the person who occupies it before they've said a word — Float's
stacked-shipping-container repair bay reads "salvager, hoarder, practical" on sight;
Axiom's shelves-into-darkness archive reads "patient, exacting, doesn't live by anyone
else's clock" the same way. If a description could be lifted out of one Doc's scene and
dropped into another's without anyone noticing, it isn't specific enough yet. Write toward
that bar in every scene, not just the ones that feel important.

**Give the mix something to build.** This is produced as fully voiced audio — ElevenLabs
narration and dialogue, with sound design under it — not read silently. For every
descriptive beat, ask what's actually happening in the mix while it plays: a sound
stopping, a breath changing, a held silence, something felt in the body. "He looked
concerned" or "the folders froze mid-air" gives the audio team nothing to build — no SFX
cue, no line reading direction, just a picture with no producible counterpart. "His
breathing slowed" or "the room's ambient rustle cut out at once" gives them something real
to work with. When a scene's first draft leans on pure visual staging, that's a sign to
pass back through it and find the sound or sensation underneath.

---

## VISUAL EMPHASIS TOOLKIT — GlitchEffect / WatcherSignal

Two components exist to dramatize what's happening to the player as SPLICE contact
escalates, on top of the writing itself. Both are already implemented — this section is
the standing reference for when to reach for each one, so the choice doesn't have to be
rediscovered every chapter.

**GlitchEffect** (`api/resources/js/components/shared/GlitchEffect.vue`) — a modular,
inline-or-overlay glitch layer: chromatic aberration, scanlines, displacement bars,
static, dissolve, flicker, and character scramble, usable individually or combined, each
with its own intensity on a 1–5 scale (`type="bars(2),chromatic(4)"`). This is the
**ambient toll** — short, escalating bursts that fire during ordinary play (walking,
mid-hack, mid-dialogue) so the player's condition worsening reads as a real, ongoing thing
rather than something that only ever happens in a cutscene. It's also the tool for a
scripted spike inside a specific scene — a live reaction to being examined, timed to an
exact line.

**WatcherSignal** (`api/resources/js/components/shared/WatcherSignal.vue`) — the
full-screen intrusion cinematic: breach, override block, corrupt-to-clean token reveal,
blackout, reboot. Reserved for major narrative beats only — the Chapter 1 cold open, a
chapter's climax — never routine transitions. It's a hard stop that takes the screen away
from the player; using it too often turns it into a loading screen instead of an event.

**Which one for a given moment:** if the scene calls for the world to visibly worsen
around the player without stopping play, that's GlitchEffect, tagged directly into the
relevant line in that chapter's `_SCRIPT.md` with an FX tag (see the ID system at the top
of each chapter's Script). If the beat is a genuine turning point that earns taking the
screen away entirely, that's WatcherSignal.

**FX tags live in the Script, never the Story.** Same rule as audio tags — they're
production metadata, not narrative content. Strip them out when mirroring a locked scene
into the `_STORY.md` read-through, same as IDs and audio paths.

---

## SPLICE PROTOCOL & THE WATCHER

**Splice Protocol** was a hidden project to transfer a living human mind permanently
into SPLICE — true digital immortality, freedom from the failures and limits of a
physical body. The Watcher was its architect.

During a live test of the transfer device, something went wrong. The Watcher was pulled
into SPLICE himself — not as a copy, not as a backup, but as the whole of him, with
nothing left behind. He has been trapped inside the frequency ever since, present but
disembodied, with no working way to feel human and no way to return.

That entrapment is where his motive comes from. He has learned, first-hand, exactly what
it costs to live permanently inside SPLICE with no body to come back to — and it isn't
the immortality anyone promised. He now wants to make certain Splice Protocol is never
rebooted, and never used on anyone else. To do that, he needs someone on the outside.
He's chosen the player.

**Why Chapter 1 feels like sickness:** the Watcher is trying to force a connection into
the player's rig, exploiting a weakness he's found, without fully understanding what that
intrusion does to a living body on the other end of it. To the player, it presents as
illness — pain, disorientation, lost time, unscheduled shutdowns — because that is
exactly what it is. He is not attacking the player. He doesn't yet know he's hurting them.

**Open thread — not yet decided, do not resolve prematurely in any quest text:** what,
specifically, the Watcher wants the player to *do* about Splice Protocol (destroy the
device, prevent a reboot, find him a way out, something else entirely) is still open and
belongs to a later chapter. Nothing in current or future Chapter 1 material should
commit to an answer here.

**Splice Protocol is public conspiracy theory, not known fact.** Nobody in Spokane —
runners, corporations, the Docs included — believes a full mind transfer into SPLICE is
actually possible. It's the kind of thing that shows up on fringe boards and gets
laughed out of respectable conversation. This is load-bearing for the mystery: it means
none of the five Docs will ever land on "someone got pulled into the network" as a
working theory, no matter how strange the player's readings get. They'll exhaust every
real diagnosis first, because as far as any of them know, the impossible one isn't on
the table. Do not have any Doc casually float mind-transfer as a hypothesis, even to
dismiss it — it shouldn't occur to them at all in Chapter 1.

---

## THE FIVE CYBER DOCS — Full Identity Profiles

The five Docs are the best technical specialists in Spokane, each holding down their own
district. They are colleagues, not ideologues, and not each other's enemies — they refer
work to each other, know each other's reputations, and respect each other's skill. That
doesn't mean they get along. Some of them genuinely grate on each other. But it's the
ordinary friction of specialists who've worked adjacent territory for years, the way two
doctors from different departments might needle each other at a hospital — not a clash of
worldviews.

None of them has ever seen a case like the player's. Each gives an honest read from their
own specialty, and none of those reads fully explains what's happening — because what's
happening isn't a tech problem. It's a person.

---

## REPAIR FOCUS — What Each Doc Actually Fixes

All five are working CyberDocs — SPLICE's underground medical team. Every one of them
repairs rigs, and they divide the work the way specialists in any repair trade do: by
which *part* of the tech is broken, not by who's willing to get their hands dirty. You
don't send an engine to a transmission specialist. Nobody on this list is "the researcher
who doesn't really fix anything" — they all fix things, they just each own a different,
non-overlapping part of the rig.

| Doc | Part of the Rig They Own | Method |
|---|---|---|
| **Knuckle** | The body — chassis, prosthetics, load-bearing cyberware. Structural damage, combat wear and tear. | Hands-on. Scans it, reads the damage, fixes what's in front of him. |
| **Veil** | The grid connection — how a rig interfaces with citywide infrastructure. Signal bleed, cascading failures, anything painting a target on a runner. | Diagnostic and structural. Treats the rig as one node in a larger system. |
| **Float** | The memory/storage — data integrity, corrupted or unfamiliar data, recovered hardware. | Archival. Cross-references against decades of recovered signatures she's personally catalogued. |
| **Axiom** | The core architecture — CPU-level processing, especially old, exotic, or unclassifiable builds nobody else's toolkit can even parse. | Research-driven. Fixes by finding the historical or structural precedent that explains the problem, then treats from there. Slower than a mechanic's fix and it looks more like scholarship than surgery — but it is still a repair. |
| **Patch** | The neural interface — where the rig meets the mind and senses. Burnout, sensory-immersion dependency, demands outpacing what a person can sustainably run. | Low-barrier, accessible care. The entry point for runners who are struggling or just starting out. |

**On Axiom specifically:** his line "Understanding a thing and repairing a thing are not
always the same profession. Mine has always been understanding" is characteristic
self-deprecation about how his method differs from a mechanic's — not a claim that he
doesn't fix rigs. He runs a working clinic like the other four. Write him fixing things by
digging through the archive for how something like it worked, or failed, before — never
write him out of repair work entirely.

---

### 01 — KNUCKLE

**District:** Browne's Addition
**Specialty:** The body — physical hardware, prosthetics, chassis rigs, load-bearing cyberware

---

#### Character

Knuckle is not a theorist. He's a mechanic in the truest sense — hands-on, blunt,
allergic to anything that sounds like a pitch. He came up fixing rigs that had to work
the first time because the person wearing them couldn't afford a second try, and that
shaped everything about how he treats his customers and his craft. He doesn't hate
technology. He hates sloppy technology, and he hates watching people trust their bodies
to hardware nobody bothered to build right.

He's the Doc runners go to first, because he's cheap, fast, and doesn't ask questions he
doesn't need answered. If the problem is the body — chassis, prosthetics, the physical
shell a runner lives inside — it's his before it's anyone else's. He and Veil have a long,
low-grade friction — he thinks she's too cautious with her own district; she thinks he's
reckless with other people's hardware — but he'll still send her a runner who needs
infrastructure help, and she'll still send him the physical repairs she doesn't have time
for.

> **What he'd tell you about himself:** "I fix what's broken. I don't fix what's stupid.
> Learn the difference and we'll get along fine."

---

#### Voice & Tone

- **Register:** Street-level. Blunt. No academic vocabulary.
- **Emotional temperature:** Controlled, dry, occasionally darkly funny. Not warm, but not cold either — just efficient.
- **Sentence structure:** Short. Declarative. He does not explain himself twice.
- **What he never does:** Lecture. Theorize past what's useful. Use corporate jargon without contempt dripping from it.

**Sample terminal dialogue:**
```
KNUCKLE: "Your rig's throwing noise on every channel I can
          read, and I can't find the source. That's not
          normal wear. That's not a virus signature either.
          I don't know what I'm looking at.
          I hate not knowing what I'm looking at."
```

---

#### His Read on the Player's Condition

Knuckle sees the physical symptoms first and worst — a rig running hot, hemorrhaging
noise, hardware behaving like it's under a load that isn't there. He can tell something
is actively *using* the player's system. He cannot tell what, why, or from where. His
diagnostics are honest, useful, and incomplete.

#### Consistency Checklist for Writing Knuckle Content

- [ ] Is the language blunt and street-level, with no theorizing beyond what's useful?
- [ ] Does he treat this as a hardware problem he can't solve, not a mystery he's trying to solve philosophically?
- [ ] Does his friction with Veil read as professional needling, not ideological conflict?

---

### 02 — VEIL

**District:** Downtown Core
**Specialty:** The grid connection — infrastructure and citywide grid systems

---

#### Character

Veil used to be a radical. She helped crash a corporate server farm to "liberate the
data" — and watched the power grids to three ICUs fail within the hour. She didn't
destroy the corporation. She caused a tragedy the neighborhood spent years cleaning up.

That experience didn't make her corporate. It made her the person who keeps Downtown's
infrastructure running because she knows exactly what happens when someone smart and
well-intentioned breaks something they don't fully understand. If the problem is how a rig
talks to the rest of the city — the grid connection itself, not the hardware on either end
of it — it's hers before it's anyone else's. She's precise, tired, and allergic to
guessing. She and Knuckle bicker about risk tolerance more or less permanently, and she's
not above cutting Axiom short when he gets too philosophical for her taste — but she
trusts all four of the others' skill without reservation.

> **What she'd tell you about herself:** "Anybody can crash a system. Building something
> that doesn't need to be crashed is the actual job."

---

#### Voice & Tone

- **Register:** Measured. Institutional. Precise. Occasionally exhausted.
- **Emotional temperature:** Detached on the surface, exhaustion visible underneath if you look.
- **Sentence structure:** Longer than Knuckle's. Structured. She thinks in systems.
- **What she never does:** Panic. Guess out loud. Celebrate anything before it's confirmed.

**Sample terminal dialogue:**
```
VEIL: "I have the diagnostic if you want to read it.
       Whatever's inside your architecture behaves like a
       grid-scale intrusion pattern.
       I have seen that pattern precisely once before, and
       it wasn't running inside a single person's rig.
       I don't have a category for this."
```

---

#### Her Read on the Player's Condition

Veil recognizes the *shape* of what's happening — deliberate, structured, patient — because
it resembles infrastructure-scale intrusion, not the sloppy work of a virus. That
resemblance unsettles her more than it reassures her. She won't guess past what she can
verify, which means she has very little comfort to offer.

#### Consistency Checklist for Writing Veil Content

- [ ] Is the language precise and systems-oriented, never speculative?
- [ ] Does she treat the player's case as data first, person second — without becoming cold or dismissive?
- [ ] Does her caution read as hard-earned professionalism, not fear?

---

### 03 — AXIOM

**District:** University District
**Specialty:** The core architecture — precedent-based repair, pre-collapse archives, historical systems, provenance

---

#### Character

Axiom is an archivist by trade and temperament — more at home among centuries of
preserved data than in the urgency of a runner's day-to-day survival. His node renders
as an impossible archive: shelves stretching up into darkness, folders drifting through
the air in slow, deliberate orbits, endlessly reorganizing themselves around patterns
only he seems to understand. He has spent eleven years trying to get an archive beneath
the University District to cooperate with him, and talks about its stubbornness the way
other people talk about a difficult relative.

He runs a working clinic like the other four Docs, but he isn't a hands-on mechanic, and
says so plainly — understanding a thing and repairing a thing aren't always the same
profession, and his instinct runs to the former. If the problem is buried in a rig's core
architecture — the deep processing layer nobody else's toolkit can even parse, especially
anything old, exotic, or unclassifiable — it's his before it's anyone else's. In practice
that means his repairs start in the archive rather than at a workbench: he treats a
problem by finding out what it actually is and what's happened like it before, then works
from there. That distance
can read as coldness until you notice how carefully he actually listens, how much he
enjoys being handed a good puzzle, how reluctant he is to let a visitor leave without
something honest to hold onto. His warmth doesn't announce itself. It shows up in the
patience he spends on you, and in how rarely he lets a real question go unanswered, even
when the honest answer is "I don't know." He treats the player less like a patient and
more like an anomaly he's delighted, and quietly unsettled, to have found.

> **What he'd tell you about himself:** "Understanding a thing and repairing a thing are
> not always the same profession. Mine has always been understanding."

---

#### Voice & Tone

- **Register:** Formal, unhurried, dry. Short clipped lines separated by long implied pauses ("Mm.", "Well.", "Curious.").
- **Emotional temperature:** Warm underneath, but the warmth is expressed as attentiveness and wit, not overt comfort.
- **Sentence structure:** Short, deliberate, often fragmented — one idea per line.
- **What he never does:** Rush. Raise his voice. Pretend certainty he doesn't have.

**Sample terminal dialogue:**
```
AXIOM: "Well.
        That's curious.
        Your architecture references systems that no
        longer exist. Memory, after all, can be untidy.
        It also references systems that never existed.
        That is unusual."
```

---

#### His Read on the Player's Condition

Axiom recognizes the player's rig as carrying architecture that predates current systems
by a significant margin — patterns that reference things that "no longer exist," and,
more troublingly, things that "never existed" at all. He has no framework for that
contradiction and doesn't pretend otherwise. He studies history, not futures, and
whatever is running in the player is still actively writing itself — which puts it
outside anything he's equipped to fully explain. He's honest about the limits of his own
expertise in a way none of the other Docs quite manage.

#### Consistency Checklist for Writing Axiom Content

- [ ] Is the language dry, unhurried, and slightly formal — short lines, long implied pauses?
- [ ] Does his warmth come through as attentiveness and wit, not overt comforting?
- [ ] Does his uncertainty come from genuine historical expertise being outmatched by something without precedent?

---

### 04 — FLOAT

**District:** Spokane Valley
**Specialty:** The memory/storage — data and archival recovery systems

---

#### Character

Float is the coldest of the five — not cruel, just clinical. Years spent recovering
corrupted, deleted, and deliberately buried data left her with very little patience for
sentiment; the archive doesn't care how you feel about it, so neither does she, at least
not out loud. If the problem lives in memory or storage — what a rig remembers, what it's
lost, what's been recovered and never properly catalogued — it's hers before it's anyone
else's. Her repair bay doubles as the closest thing Spokane has to an unmodified data
archive, and she treats every recovery job, human or otherwise, with the same detached
precision.

She and Patch get along fine, in the specific way that two people who both prefer facts
to feelings tend to. She finds Axiom's patience for a good story a little indulgent and
says so, without much heat behind it.

> **What she'd tell you about herself:** "I don't get emotional about data. I get
> emotional about data that's missing."

---

#### Voice & Tone

- **Register:** Clinical. Archival. Precise to the point of being unsettling.
- **Emotional temperature:** Flat. The emotional weight lives in *what she shows you*, not how she talks about it.
- **Sentence structure:** Methodical. Cataloguing. She speaks in facts.
- **What she never does:** Express outrage. Speculate without evidence.

**Sample terminal dialogue:**
```
FLOAT: "I ran your architecture against every intrusion
        signature I've ever archived. Zero matches.
        Not 'low confidence.' Zero.
        I don't have a category error that big very often.
        I don't like it."
```

---

#### Her Read on the Player's Condition

Float's specialty is finding precedent, and there is none. Whatever is inside the
player's rig doesn't match anything in her archive — not a known virus, not a known
exploit, not a known artifact. For a woman who has spent her career proving that nothing
is ever truly lost or unprecedented, that absence is the most unsettling read of all five.

#### Consistency Checklist for Writing Float Content

- [ ] Is the language clinical and precise — never outraged?
- [ ] Does the horror come from the *absence of precedent*, not a dramatic reaction?
- [ ] Does she stay detached even when what she's finding is disturbing?

---

### 05 — PATCH

**District:** North Spokane
**Specialty:** The neural interface — full sensory immersion rigs

---

#### Character

Patch works with people who've gone too deep into simulated sensation — dependency
cases, burnout cases, people whose rigs gave them something better than reality and who
can no longer stand to disconnect. If the problem is the interface itself — where the rig
stops being hardware and starts being someone's mind — it's hers before it's anyone
else's. It's serious work, but it hasn't made her serious. She runs her clinic out of a
cluttered maintenance station that doubles as a home — plants growing under grow-lamps, a
kettle always going, tools that never stay where she left them — and meets crisis with
dry, deadpan humor rather than gravity. It isn't deflection. She's just found that
treating a catastrophe like a catastrophe rarely helps the person having one.

She doesn't believe in gentle lies, even the kind meant kindly, but she delivers hard
truths with a shrug and a joke rather than solemnity. She's made an unusual peace with
the limits of her own knowledge, and treats not-knowing as an ordinary, faintly amusing
part of the job rather than a failure. She respects Float's precision and finds the other
three a bit noisy for her taste, though she'd never say so unprompted.

> **What she'd tell you about herself:** "I don't fix people. I keep them from disappearing."

---

#### Voice & Tone

- **Register:** Dry, deadpan, warm underneath. Short lines with real comic timing.
- **Emotional temperature:** Genuinely caring, delivered as humor rather than sentiment — she'd rather make you laugh than watch you panic.
- **Sentence structure:** Short, clipped, often landing on a punchline or a dry aside.
- **What she never does:** Perform certainty she doesn't have. Let a joke replace an honest answer when one exists.

**Sample terminal dialogue:**
```
PATCH: "I've seen dependency patterns that look like this.
        Reality rejection, in reverse.
        Except you're not rejecting reality.
        Something is trying to get *into* it.
        No idea what to call that.
        Which is annoying. Interesting. But annoying."
```

---

#### Her Read on the Player's Condition

Patch recognizes the emotional and physiological signature of her own patients —
someone's mind straining against the border between the simulated and the real — but
inverted. Her patients want out of reality. Whatever is happening to the player looks
like something on the *other side* wants in. It's the closest anyone gets to the truth in
Chapter 1, and even Patch doesn't fully trust her own read.

#### Consistency Checklist for Writing Patch Content

- [ ] Does the warmth come through as dry humor, not overt comfort?
- [ ] Does her read edge closer to the truth without confirming it outright?
- [ ] Does she stay funny and self-aware even when concerned for the player, rather than turning solemn?

---

## CROSS-DOC DIALOGUE RULES

When the player brings information or symptoms from one Doc to another, the second Doc
responds *in character*, from their own specialty — and with the ordinary friction of a
colleague who has an opinion about how someone else handled it. They don't fully defer to
the other Doc's read, and they don't dismiss it either.

**Example interactions:**

- Knuckle shown Veil's infrastructure-pattern diagnosis: "Grid-scale, huh. Explains why I couldn't find a source. Doesn't explain why it's living in a runner's rig instead of a substation."
- Veil shown Knuckle's hardware readout: "He's not wrong about the load pattern. He's also not going to tell you what's causing it, because he doesn't know, and he doesn't like saying that."
- Axiom shown Float's zero-match archive search: "Mm. Float doesn't say 'no matches' lightly. If her archive's never seen it, we're not looking at a virus. We're looking at something that's never been catalogued at all."
- Float shown Axiom's read on the player's anomalous pre-collapse architecture: "Interesting. Noted. I'll add it to the file. Reassurance was never my department anyway."
- Patch shown Veil's grid-scale comparison: "She's right that it's structured. She's wrong that it's infrastructure. Infrastructure doesn't want anything. This does."

---

## FINAL DESIGN NOTE

The player's role in this world is patient and investigator, not soldier. They aren't
fighting for any of the five Docs, and none of the Docs are fighting each other. Every
piece of writing in Chapter 1 should build one feeling: something is deliberately,
patiently trying to reach the player, nobody qualified to help has ever seen it before,
and the truth — that it's a trapped, desperate person named the Watcher — is still out of
reach.

That dread is the point. That is the game.

---

*Document version 2.2 — Added VISUAL EMPHASIS TOOLKIT section documenting GlitchEffect
(ambient toll / scripted spikes) and WatcherSignal (major-beat cinematic only), and the
rule that FX tags live in the Script and are stripped from the Story mirror, same as audio
tags. Previously, version 2.1 added WRITING WORKFLOW & PROSE STANDARD and REPAIR FOCUS
sections; each Doc's Specialty line and Character section now name the specific part of
the rig they own, matching the REPAIR FOCUS table. Axiom rewritten to the shipped archivist
characterization; Patch rewritten to the shipped she/her, dry-humor characterization;
Float's district corrected to Spokane Valley. Supersedes the earlier five-layer
ideological framework and multi-lens decryption system.*
*Update this document before beginning any new district's quest writing.*
