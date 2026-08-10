# CodeCraft — Chapter 1 Story
### The Watcher Arc — master planning document
---

## What This Document Is

This is the "everything" document for Chapter 1 — structure, mystery design, character
motivation, symptom tracking, sequencing, open threads, **and a clean read-through of the
story itself.** It has two jobs: let you read Chapter 1 the way a player will experience
it, and let you check that read against the planning material below to confirm it's still
unified and on-target.

This is *not* the production copy. `api/CHAPTER_1_SCRIPT.md` is the master document for
that — every line tagged with a `C{chapter}_S{scene}_P{part}_L{line}` ID and an audio
filename, the copy that goes to ElevenLabs. This document mirrors the same scenes with all
of that stripped out, so it reads like a story instead of a shooting script. **Whenever a
scene changes in `CHAPTER_1_SCRIPT.md`, mirror the update here too** — the read-through
below only stays useful if it matches what's actually written.

Sits alongside `WORLD_PHILOSOPHY.md` (v2.0), which holds the setting/character bible.
This doc is chapter-specific; `WORLD_PHILOSOPHY.md` stays evergreen across chapters. The
same split (a `_STORY.md` read-through next to a `_SCRIPT.md` production copy) repeats for
every future chapter — `CHAPTER_2_STORY.md` alongside `CHAPTER_2_SCRIPT.md`, and so on.

`CHAPTER_1_NOTES.md` is now superseded by this document — see note at the bottom.

---

## The Story So Far

*A clean read-through of every scene locked so far. No IDs, no audio tags — just the
story. Mirrors `api/CHAPTER_1_SCRIPT.md` exactly; if the two ever disagree, the script is
the source of truth and this section is out of date.*

*Read this section as prose, not as a checklist. If a line doesn't flow, if a description
feels generic, or if a beat is underwritten, that's a signal to go fix it in the Script
file and remirror — not to patch it here in isolation. See "WRITING WORKFLOW & PROSE
STANDARD" in `WORLD_PHILOSOPHY.md`: this is meant to read like a novel the player gets to
play a role in, not a functional recap of what happens.*

### Cold Open — Float's repair bay, Spokane Valley

The diagnostic lead clicks into your collar before you're even conscious enough to feel
the cold alloy. You wake up suspended in Float's rig, suspended in static. Your temple is
throbbing — a rhythmic, dull-blade pulse right behind your left eye. The shop's ceiling is
tilting slowly to the right.

**FLOAT:** "Hey. Eyes on me. Stay anchored."

Float isn't looking at your face; she's looking at the diagnostic rack behind you. She
steps in, wrenching a snarl of braided wire out of the way.

**FLOAT:** "You blew through my lock, kicked my door off its track, and spewed three
seconds of garbled machine code before your legs gave out. You want to tell me what that
was?"

You reach for the memory of five minutes ago. There's no door, no walk, no panic. Just an
empty grey void where the timeline should be.

You open your mouth to explain, but your vocal synth stutters. The words fracture into raw
phonemes, lagging half a beat behind your jaw.

**PLAYER:** "I... can't— something's... missing. I don't know how I got here."

Float doesn't look surprised. She stops listening to your voice and starts listening to
the telemetry — the way an engineer ignores a panicked driver to read the oil pressure.

**FLOAT:** "Stop forcing the vocal track. Just breathe. You're dropping frames."

With a sharp gesture, she snaps a holographic telemetry window into the air between you.
Raw data cascades down in harsh amber text — far too fast for your glitched optics to
parse, but Float's eyes track every line.

Her brow hitches. A beat of dead silence hangs in the workshop.

**FLOAT:** "Your core temp is spiking, but that's just collateral. Look at this spike
before you collapsed. Whatever spiked your system wasn't a spike at all. It was a
background process. It was running in your stack for ten minutes before you dropped."

She doesn't wait for your answer. She already knows you don't have one.

Float swaths her hand across the air, dragging a second pane alongside the first — her
personal black-market archive. Decades of black-budget intrusion signatures, dead
megacorp payloads, and corrupt firmware patterns begin cross-referencing against your
spike.

The progress bar doesn't even stretch. It snaps instantly to zero.

**FLOAT:** "Zero."

She drops her hand, staring at the empty query result. Float's voice drops half an octave
— stripped of its usual defensive sarcasm, leaving only cold, mechanical calculation.

**FLOAT:** "Not a bad match. Not a partial corruption signature. Zero. I have payloads
cataloged in this rig from before the grid fell, and your footprint doesn't share a single
line of logic with any of them."

She turns away from the floating glass, her gaze drifting toward the heavy steel door you
supposedly forced open. Her hand lingers near the lock manual override.

**FLOAT:** "I built this sanctuary on one rule: if it comes through that door, I know what
it is and I know how to kill it. But whatever is sitting inside your head right now... it
isn't in anyone's system."

Float straightens away from the window, and just like that, something in her posture
resets — the crack sealing back over, replaced by the flat working calm of someone who
fixes problems for a living. She crosses to the rig and starts stripping the diagnostic
leads off you herself, quick and unceremonious.

**FLOAT:** "Here's where we are. I don't know what this is. I don't like not knowing.
Those two things mean I'm going to keep pulling on this thread whether you pay me or not
— but pulling on it costs me rig time, and rig time isn't free."

The last lead comes free from your collar with a small, cold pop. She tosses it onto the
rack without looking.

**FLOAT:** "I've got a job sitting cold because it's not worth my time for what it pays.
It's worth yours. Run it, and I keep digging on this in the background while you're out.
That's the trade."

**PLAYER:** "Where."

**FLOAT:** "Decommissioned relay station, edge of the Valley grid. There's a memory core
still intact in the wreck — old enough nobody's bothered stripping it. I want it before
some scrapper beats you to it and sells it for parts instead of data."

Float pulls up a district map instead of saying anything else, one node glowing cold blue
against the sprawl of the Valley grid.

**FLOAT:** "There. Don't collapse on me twice in one day. I've got a reputation to
protect."

*[Field job: the player retrieves the memory core from the relay station. No dialogue —
see the Stage-by-Stage table below for the objective text.]*

### Report Back — Float's repair bay, Spokane Valley

Float doesn't look up when the heavy door groans open. It's still hanging half an inch off
its mounting track from when you kicked it in earlier. She's elbow-deep in an open chassis
on the main bench, live wire-harbors sparking against her gauntlets.

Without breaking her stride, she extends a grease-stained palm back toward you.

**FLOAT:** "Core. Give."

You drop the warm alloy module into her hand. She doesn't inspect it. It disappears into a
heavy steel drawer with a hydraulic thud — filed away like the job was just a distraction
to keep your hands busy.

**PLAYER:** "Did you pull anything off my read while I was in the field?"

Her pneumatic driver goes silent. Float sets the tool down on the bench — slow, precise,
the calculated pause of someone trying to organize bad news into manageable pieces.

**FLOAT:** "I stopped looking for a match in my archive. I ran your spike against itself.
Evaluated the signature's delta over time instead of static code."

She snaps her fingers, sweeping a multi-layered spectral waveform into the air between
you. It pulses with a frantic, jagged frequency — dense, tight, and unnervingly rhythmic.

**FLOAT:** "Noise is chaotic. It degrades. This isn't degrading. Every iteration of this
wave is cleaner, sharper, and more optimized than the one before it. Like a program
running a self-correction loop. Like something's practicing."

She cuts herself off mid-thought. For a fraction of a second, her jaw sets hard, like she
just glimpsed something through the code she'd rather unsee.

**FLOAT:** "Data behaving like it has intent... that's not a storage problem. That's a
structural one. It's built on something — referencing something — and none of it matches
anything I've got catalogued. I fix hardware, not history. I can't tell you if what's
underneath this is a design nobody's used in decades... or one that was never supposed to
exist."

**PLAYER:** "If you can't read it, who can?"

**FLOAT:** "Axiom. University District. Cross-referencing a pattern against a hundred
years of buried, pre-collapse architecture is his entire business model — he can dig up
parallels out of that archive of his that I wouldn't even know how to search for. I
already sent him the raw telemetry package. Get going."

She's already reaching back into the open chassis on her bench, her fingers darting into
the mechanical guts before you've even taken a step toward the door. But just before the
sparks start flying again, her shoulder hitches.

**FLOAT:** "And don't tell him I care what he finds in that architecture of yours. He'll
think I'm going soft, and I don't need Axiom thinking I have a weak spot."

### Axiom's Broken Rig — Axiom's archive, University District

Axiom's space is nothing like Float's — shelves stretching up into a darkness that never
quite resolves into a ceiling, folders drifting through the air in slow, unhurried orbits,
resettling themselves as though the whole room is quietly filing itself. You feel the
difference in your teeth before you feel it anywhere else: unhurried, precise, the kind of
quiet that comes from centuries of not being interrupted. Axiom looks up from behind a
desk assembled more from memory than furniture, closing whatever he was reading with the
particular care of someone who intends to come back to it.

**AXIOM:** "Float sent the file ahead of you. I've read it twice. Sit — you look like the
walk here cost you more than it should have."

A chair drifts into place opposite the desk. You lower yourself into it before you're
entirely sure it's finished arriving.

**AXIOM:** "She's not wrong to send you to me. Her toolkit is built for structural repair,
not exotic architecture. What she recorded doesn't look like corruption anyway. It looks
like a signal correcting itself against feedback, which is a very particular kind of
behavior. Do you know what that behavior usually is?"

**PLAYER:** "No."

**AXIOM:** "Learning. That's what it usually is. I don't say that to frighten you. I say
it because I'd rather you hear the honest word from someone than spend the next hour
guessing at a worse one."

He doesn't flinch saying it, and somehow that steadies you more than if he had. Axiom
rises and crosses to an oversized diagnostic reader built into the shelves — the kind of
instrument meant for legacy silicon, not modern rigs — already talking through the next
step before he reaches it.

**AXIOM:** "I want to lay your signal against the archive's oldest layers — properly, not
a glance. That'll tell us whether it's isolated or spreading, and roughly how fast. It's
not a pleasant comparison to run. It isn't a dangerous one either."

He rests a hand on the housing's access panel. It doesn't open. He tries again, and this
time you hear something inside click without engaging — a small, wrong sound in an
otherwise silent room.

Axiom's composure doesn't crack, exactly. It just goes still for a second, the way someone
goes still when a plan quietly stops being available.

**AXIOM:** "Of course. Not today."

**PLAYER:** "What's wrong with it?"

**AXIOM:** "The resonance coil burned out a component I can't route around — the part that
reads depth architecture instead of surface noise. Without it I can hand you a very
confident guess. I don't deal in those if I can help it. The replacement's been sitting at
a courier depot two nodes from here for three days. My usual runner won't touch it — the
depot's changed hands and nobody's sure who's actually holding the access rights anymore."

He turns back to you, and for a moment the warmth comes back into focus, deliberate, like
he's choosing to spend it on you specifically.

**AXIOM:** "I know I'm asking you to run errands while something is actively happening to
you. I don't love that either. But I would rather have the real number than a fast one,
and right now the real number is on the other side of that depot door."

**PLAYER:** "Where do I go."

**AXIOM:** "UD-v9. Fetch the part, bring it back to the archive, and we'll find out what's
actually happening to you."

### Axiom's Report Back — Axiom's archive, University District

The part is smaller than expected—a matte-grey cylinder no bigger than a fingertip, its
edges worn smooth from three days of sitting unclaimed. Axiom takes it with both hands,
cradling it like something that used to belong to someone who mattered.

**AXIOM:** "Good. No detours, no complications. I was almost disappointed—I'd already
started composing the apology I was going to owe you."

He steps back to the reader, working the panel open with the patient, exact precision of a
man who has done this a thousand times and will do it a thousand more. The replacement
seats with a small, clean click—the sound the original should have made.

**AXIOM:** "There. Now we ask the question properly."

He presses a lead against your temple—cold, sterile metal, nothing like Float's tangled
nest of braided wire. The reader hums alive, low and resonant, shivering through your
cheekbone. For a second, nothing happens.

Then the shelves shudder—then go still. Not the room. The sound: a hundred folders that
were always faintly turning, rustling, snap silent at once. You feel it before you
understand it—pressure behind your eyes, like the room just changed altitude.

Pain shears through your skull, sharp and blinding—a line of white, electric heat drawn
directly from ear to ear, searing along the neural housing at the base of your brain. It
vanishes before you can gasp, but your hand flies to your temple. Your fingers come away
trembling.

**AXIOM:** "Steady. That was the archive, not you—it doesn't like being asked questions it
can't answer. And neither, apparently, do you."

His breathing doesn't change. If anything it slows—the sound of a man leaning into a
problem instead of flinching from it. As the reader finishes its pass, a dense column of
raw telemetry scrolls beside him. He goes quiet—a heavy, sudden silence that has nothing to
do with reading speed.

**AXIOM:** "Well. I told you it referenced things that no longer exist. I was wrong about
the shape of that. It isn't referencing old architecture the way a museum references
history. It's referencing it the way flesh references a frame it was built around."

**PLAYER:** "Meaning what."

**AXIOM:** "Meaning it isn't passive. I laid it against the deepest layer in this archive
expecting alignment or silence. I got neither. For about four seconds, it reached."

He says the word deliberately, weighing it against three others he liked less.

**AXIOM:** "Not spreading. Not corrupting anything of mine, you'll be relieved to hear.
Reaching—the way a signal reaches for an origin point, or a dying limb twitches toward a
pulse. It wasn't interested in my archive at all. It was using my system as a conduit to
touch something further out."

**PLAYER:** "Further out than your archive."

**AXIOM:** "Further out than anything in this room. That's a grid question, not an
archivist's question—whether a payload can reach through a rig, down a person's spine, and
out into the public frequency. I study what things were. I don't study what they're
actively doing to city infrastructure while anchored in living tissue."

He sets the lead down. Doesn't reach for the next tool. The quiet stretches long enough
that you start counting your own heartbeat before he breaks it.

**AXIOM:** "I'd like to tell you this narrows it down. It doesn't. It widens it. Twenty
minutes ago, I thought you were carrying a dead artifact. Now I know you're carrying
something ancient that is currently, actively trying to go somewhere else—and I have no
idea whether that's better news, or considerably worse."

**PLAYER:** "Who do I see about the grid question."

**AXIOM:** "Veil. Downtown Core. If something is using the frequency itself to reach for a
destination, she's the only one left who'd recognize the footprint before it arrives. Tell
her I said it reached—she'll know exactly how unhappy that should make her."

*[End of scenes locked so far. Veil's Scene 3 not yet started.]*

---

## The Core Idea

The player isn't infected. They're being reached.

The Watcher — architect of Splice Protocol, trapped inside SPLICE since a catastrophic
test of his own mind-transfer device — is trying to force a connection into the player's
rig. He's exploiting a weakness he's found. He doesn't fully understand, or doesn't yet
care, what that intrusion is doing to a living body on the other end.

The player experiences this as illness. Not metaphorically — actual physical symptoms,
because SPLICE damage is real-body damage in this world (see `WORLD_PHILOSOPHY.md` §THE
SPLICE FREQUENCY).

**Chapter 1 symptom list** (for quest text, environmental text, and UI/status framing):

- Sharp, unexplained pain with no visible cause
- Losing track of time — gaps, missing minutes, sometimes longer
- Rig shutting down without warning, mid-task
- Vocal/motor stutter under stress — output lagging behind intent (established in C1_S1_P1)
- General degradation that reads like illness, not damage — fatigue, disorientation, not just "low HP"

None of this should be described as corruption, a virus, or malware in any dialogue. The
player and the Docs should talk about it the way people talk about an illness nobody can
diagnose — because that's what it looks like from the inside, to everyone involved,
including the Docs.

---

## Why the Doc Visits Still Work as a Structure

The five-quest, five-district circuit from the prologue still makes sense as a shape:
the player, scared and getting worse, goes from specialist to specialist looking for an
answer. What changes from the prologue is *why* each stop happens and what the player
walks away with.

Prologue structure: each Doc decrypts one ideological "lens" on a scripted historical
event, building toward a thesis about systemic control.

Chapter 1 structure: each Doc examines the player directly, gives an honest professional
read from their own specialty, and none of the reads fully agree — because none of them
have ever seen this before. The player isn't collecting philosophical lenses. They're
collecting fragments of a diagnosis that doesn't resolve, which is what pushes them (and
the player, meta-textually) toward suspecting this isn't a tech problem at all.

Per `WORLD_PHILOSOPHY.md`, the specialist reads are:

| Doc | District | Specialty | Their read on the player |
|---|---|---|---|
| Float | Spokane Valley | Memory & data archives | Zero matches against any known signature, ever. (Confirmed in C1_S1_P1.) |
| Axiom | University District | Pre-collapse archives/history | Rig's architecture references systems that no longer exist — and some that never did. Refined in C1_S2_P2: it isn't static, it's actively *reaching* for something outside the rig — a grid-scale question outside his specialty. |
| Veil | Downtown | Infrastructure/grid | Pattern reads as grid-scale intrusion — but it's running inside one person. |
| Knuckle | Browne's Addition | Physical hardware | Rig's under a load with no source. Hardware-honest, mechanism-blind. |
| Patch | North Spokane | Sensory immersion | Recognizes the shape of his own patients' symptoms — inverted. Closest to the truth, still not there. |

**Sequencing note:** Confirmed order so far — Float → Axiom → Veil, each one a direct
colleague referral rather than a fixed prologue-style route. Axiom's report-back
(C1_S2_P2) closes on an explicit hand-off to Veil (Downtown Core), so she's locked as the
third Doc. Knuckle and Patch remain unordered after her — see Open Threads below.

**Why each quest still involves a job.** The Docs aren't running charity diagnostics.
They want to help — this is a real, strange case and their professional curiosity is
genuinely engaged — but running tests costs them time, materials, and favors, and none of
that is free. So each one puts the player to work: the job pays for the diagnostic effort
the Doc is quietly putting in on the side. It's not "prove yourself" or "we're testing
you as a subject." It's closer to "I'll help you figure this out, but I can't do it for
free, so here's how you cover it."

**Splice Protocol never comes up as a theory.** It's a fringe conspiracy theory nobody
credible believes in — see `WORLD_PHILOSOPHY.md` §SPLICE PROTOCOL & THE WATCHER. None of
the five Docs should ever suggest, even jokingly, that the player might have someone's
mind stuck in their rig. That idea isn't available to them. They'll reach for real
diagnoses — corrupted firmware, a bad implant, an unknown intrusion pattern — and come up
short, which is what should read as unsettling, not a near-miss on the truth.

---

## What the Player Should NOT Learn in Chapter 1

- The Watcher's name or the term "the Watcher" as an identity, unless seeded as an overheard/glitched fragment (optional — mirrors the old Watcher Interrupt broadcasts already in `api/PROLOGUE_SCRIPT.md`, which fit this concept well and may need only light touch-ups).
- That Splice Protocol exists, or what it was for.
- That there is a person behind this at all — the horror of Chapter 1 is not knowing, not confirming.
- Any resolution or comfort. Every Doc visit should end with the player *less* certain, not more.

---

## Stage-by-Stage Progress

Scene/Part IDs follow `C{chapter}_S{scene}_P{part}` — one Scene per Doc, Parts number
scripted dialogue beats only (minigame-only field jobs stay unnumbered). See "Scene / Part
ID System" at the top of `api/CHAPTER_1_SCRIPT.md`.

| ID | Status | Doc | Summary |
|---|---|---|---|
| C1_S1_P1 — Cold Open | **Scripted** (see `api/CHAPTER_1_SCRIPT.md`) | Float | Player wakes mid-diagnostic at Float's, no memory of arriving — he broke her lock, kicked her door off its track, and collapsed after seconds of garbled speech. She finds a pre-collapse background process running 10 minutes before he dropped. Zero archive matches. She agrees to keep digging in the background if he covers the cost with a job. |
| C1_S1 — Relay Retrieval (field job, no dialogue) | Objective written, field scene not yet written | Float | Player travels to a decommissioned relay station on the edge of the Valley grid to retrieve an intact memory core before scrappers strip it. Mundane job on the surface — deliberately unconnected to the player's condition or Splice Protocol (see "What the Player Should NOT Learn," above). Real purpose: covers the cost of Float's background diagnostics. |
| C1_S1_P2 — Report Back | **Scripted** (see `api/CHAPTER_1_SCRIPT.md`) | Float | Player hands off the core. Float reveals her background dig found the spike isn't noise — it corrects itself over repetitions, like something practicing. She's unqualified to say whether it's failing or learning, and refers the player to Axiom (archivist, pre-collapse systems specialty) for a read. Resolves the "how does the player move Doc to Doc" open thread: colleague referral, not Watcher-routed. |
| C1_S2_P1 — Axiom's Broken Rig | **Scripted through job hand-off** (see `api/CHAPTER_1_SCRIPT.md`) | Axiom | Axiom reframes Float's data as a signal correcting itself against feedback — "learning," not corruption, said plainly rather than as a scare. Wants to lay it against the archive's oldest layers, but his diagnostic reader's depth-read component is burned out. Replacement is stuck at a courier depot two nodes away with a disputed access-rights problem. New job: retrieve the part from UD-v9. |
| C1_S2_P2 — Report Back | **Scripted** (see `api/CHAPTER_1_SCRIPT.md`) | Axiom | Player returns with the part; Axiom repairs the reader and runs the real scan. Result: the architecture isn't just old, it's actively *reaching* — a four-second attempt to touch something beyond the rig itself, mid-scan. That's a grid question, outside Axiom's specialty. He refers the player to Veil (Downtown Core), locking her as the third Doc. Escalation: the diagnostic-failure spike from C1_S2_P1 (light) is followed here by a stronger pain/pressure spike (FX-tagged, worse than before) — the pattern of each examination provoking a harder response is now established across two scenes. |
| C1_S3 — Veil | Not yet written | Veil | — |
| C1_S4+ | Not yet written | Knuckle, Patch (order TBD) | — |

**C1_S1 field job objective text — Relay Retrieval (quest log copy, not narration):**

> Something dragged you to Float's door in the middle of a blackout you don't remember.
> She doesn't know what's wrong with you — nobody does — but she's willing to keep
> digging if you cover the cost.
>
> She's got a memory core sitting in a stripped relay station on the edge of the Valley
> grid. Old, low-value, not worth her time. Get it before a scrapper beats you to it and
> guts it for parts.
>
> [WARNING] — Whatever's running in your system doesn't stop just because you're working.
> Expect the same symptoms from C1_S1_P1 to resurface in the field: lost time, sudden
> pain, your rig dropping out without warning.

**C1_S2 field job objective text — Depot Retrieval, target node UD-v9 (quest log copy, not
narration):**

> Axiom can't run the scan that actually matters without a burned-out component replaced
> first. The part's sitting at a courier depot two nodes from the University District —
> access rights are in dispute since the depot changed hands, and Axiom's usual runner
> won't touch it.
>
> Get to UD-v9. Get the part, get it back to the archive.
>
> [WARNING] — Same as before: expect symptoms to hit mid-job. This isn't getting better
> on its own.

Keep this table updated as scenes get locked in `CHAPTER_1_SCRIPT.md`, so this doc stays
an accurate map without needing to read the full script.

---

## Open Threads (do not resolve early)

- **What the Watcher ultimately wants the player to do** about Splice Protocol — stop a reboot, destroy the device, find him a way out, something else. Explicitly undecided; belongs to a later chapter. Don't let any Chapter 1 or Chapter 2 text commit to an answer.
- **"The true cost" the Watcher learned** from being trapped — what it actually is/means is not yet defined. Leave abstract/ominous in any near-term dialogue rather than specific.
- Whether the old **Watcher Interrupt broadcasts** (the corrupted `[UNKNOWN_PROCESS: INJECTING]` signals between quests in `api/PROLOGUE_SCRIPT.md`) stay as-is, get attributed more directly to the Watcher, or get reworked.
- ~~How the player moves from Doc to Doc after Float~~ — **Resolved.** Colleague referral: Float can't interpret pattern-shaped data against pre-collapse architecture, refers the player to Axiom directly (see C1_S1_P2). Confirmed pattern going forward — each Doc refers to whichever specialist their dead end points toward, rather than a fixed prologue-style order.
- ~~Doc order after Axiom~~ — **Partially resolved.** Axiom's report-back (C1_S2_P2) closes on an explicit referral to Veil — his "reaching" result is a grid-scale question, which is her specialty, not his. Order locked so far: Float → Axiom → Veil. Knuckle and Patch remain unordered after Veil's scene.
- **Escalation pattern, now established.** C1_S2_P1 and C1_S2_P2 both land a spike tied directly to the diagnostic attempt itself (the reader failing on contact, then a stronger pain/pressure spike once it actually runs) — each examination provoking a harder response than the last. Veil's scene should continue that ladder at a higher intensity; see VISUAL EMPHASIS TOOLKIT in `WORLD_PHILOSOPHY.md` for the FX tag mechanics.
- **Player voice/audio pipeline.** The player now has fixed, non-branching, voiced lines (C1_S1_P1 establishes this) — this doesn't exist anywhere in the current game engine. `PLAYER_CHOICE` options in the real seeder (`api/database/seeders/QuestStageSeeder.php`) have no audio field, and chosen options only ever echo back as silent text (`PLAYER_SAID`). Supporting a voiced player line is new engine work, not yet scoped or scheduled.
- **Chapter/arc data structure.** There is no "chapter" concept in the schema yet (`quest_arcs` / `player_arc_progress` currently only model the prologue). Chapter 1 will need its own arc/seeding setup before any of this becomes playable. Not yet scoped.

---

## Open Work — Not Done Yet

`api/PROLOGUE_SCRIPT.md` still reflects the old prologue concept end to end and has not
been touched:

- The Docs currently investigate a "Ghost-Kernel" described as compressed consciousness/an artifact placed in the player deliberately by an unknown third party — this contradicts the new lore (it's the Watcher himself, reaching in, not an installed object).
- Doc dialogue still carries some of the old ideological voice and cross-doc tension framing from the pre-v2.0 `WORLD_PHILOSOPHY.md`.
- ~~Quest 4 (Axiom) dialogue~~ — **Resolved.** Quest 4 has been rewritten to match Axiom's shipped archivist characterization (see `api/PROLOGUE_SCRIPT.md`); the old "It is not a virus. It is not malware. It is a person" Ghost-Kernel framing and third-party-delivery explanation are gone.
- Separately, `api/PROLOGUE_SCRIPT.md` was found to be stale against the actual shipped implementation in `QuestStageSeeder.php`: Quest 1 (Knuckle) is missing audio tags the seeder has, and the doc shows 3 `PLAYER CHOICE` options per branch where the seeder only implements 1. Worth fixing regardless of the narrative rewrite.

Recommend a dedicated pass through `PROLOGUE_SCRIPT.md`, quest by quest, once Chapter 1
is further along and the Doc voices in `WORLD_PHILOSOPHY.md` v2.0 are proven out in
actual scenes.

---

## Note on Superseded Files

`CHAPTER_1_NOTES.md` (project root) was the first draft of this document and also
contained an early, now-replaced draft of the C1_S1_P1 scene. This document replaces it.
`CHAPTER_1_NOTES.md` can be deleted once confirmed — flag to Claude/Code to remove it.

---

*Document version 1.3 — Mirrored C1_S2_P2 (Axiom's Report Back) into "The Story So Far,"
matching `api/CHAPTER_1_SCRIPT.md` v2.0. Locked Float → Axiom → Veil as the confirmed Doc
order (Axiom's scene closes on an explicit referral to Veil), updated the specialist-read
table and Stage-by-Stage Progress table, and noted the now-established escalation pattern
across C1_S2_P1/P2 in Open Threads. Previously, version 1.2 replaced `CHAPTER_1_NOTES.md`
and added "The Story So Far" clean read-through section, mirroring the three scenes locked
at the time; C1_S2_P1 (Axiom's Broken Rig) was expanded to match script v1.7.*
