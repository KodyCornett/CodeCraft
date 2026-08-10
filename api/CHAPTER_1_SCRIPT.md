# CodeCraft — Chapter 1 Script
### The Watcher Arc
Companion to `WORLD_PHILOSOPHY.md` (v2.0) and `CHAPTER_1_NOTES.md`. Full dialogue and
narrator lines for Chapter 1, written scene by scene. Supersedes the old "Ghost-Kernel"
framing in `api/PROLOGUE_SCRIPT.md`, which remains unchanged (see that file's Open Work
notes in `CHAPTER_1_NOTES.md`).

**This is the source of truth.** Write and edit scenes here first. Once a scene is locked,
mirror it into `CHAPTER_1_STORY.md`'s "The Story So Far" section, stripped of IDs and audio
tags, and read it back as prose — fix what doesn't flow here, then remirror. See "WRITING
WORKFLOW & PROSE STANDARD" in `WORLD_PHILOSOPHY.md` for the full standard: this is a novel
the player plays a role in, and every description needs to earn that.

---

## Scene / Part ID System

Every scripted beat in this file is tagged `C{chapter}_S{scene}_P{part}`.

- **Scene** — one number per Doc's block in the chapter. Doesn't reset per chapter; each
  new Doc encountered gets the next Scene number in sequence.
- **Part** — sequential within a Scene, counting *scripted dialogue beats only*.
  Minigame-only field stages (no dialogue — just an objective and a job) don't consume a
  Part number. They're referenced by their objective text and sit between Parts
  chronologically, but aren't a "Part" themselves.

Example: Float is Scene 1. Her cold open is `C1_S1_P1`. The relay-station retrieval that
follows is a field job with no dialogue, so it stays unnumbered. Her report-back scene is
`C1_S1_P2` — not P3 — because the field job didn't consume a Part. Axiom is Scene 2: his
job hand-off is `C1_S2_P1`, and his (not yet written) report-back will be `C1_S2_P2`.

Headers in this file are labeled `## C{n}_S{n}_P{n} — Title (Doc)`.

**Line-level audio IDs and folder structure.** The Prologue's audio (already recorded,
already referenced in `api/database/seeders/QuestStageSeeder.php`) lives flat inside each
Doc's folder — `axiom/a_s1_l1.mp3`, `narrator/float/f_s3_l2.mp3`, etc. Those files and
their references are untouched by this system.

Going forward, every chapter gets its own subfolder *inside* each existing Doc folder, so
the top-level structure stays organized by character (one voice, one folder) while each
chapter's lines stay self-contained for production:

```
public/audio/Sound/
  axiom/
    a_s1_l1.mp3            ← Prologue, untouched
    chapter_1/
      c1_s2_p1_l2.mp3
    chapter_2/
      c2_s#_p#_l#.mp3
  narrator/
    axiom/
      a_s1_l1.mp3           ← Prologue, untouched
      chapter_1/
        c1_s2_p1_l1.mp3
  player/
    chapter_1/               ← new — Prologue has no voiced player lines
      c1_s1_p1_l6.mp3
```

Full path pattern for Chapter 1: `{speaker}/chapter_1/{part_id}_l{n}.mp3` for a Doc's own
lines, `narrator/{doc}/chapter_1/{part_id}_l{n}.mp3` for narrator lines,
`player/chapter_1/{part_id}_l{n}.mp3` for the player's fixed voiced lines. The line counter
is one continuous sequence per Part, shared across all speakers in that Part, and resets to
`l1` at the start of each new Part.

This is what you'd hand to ElevenLabs — pull lines top-to-bottom within a Part, name each
take after its tag, drop it in the matching `chapter_1/` folder, and the path drops straight
into that stage's `dialogue` array back in the seeder.

**FX tags.** A line can carry a bracketed `[FX: ...]` tag directly beneath its speaker/audio
line, calling out a `GlitchEffect` cue to fire at that exact moment — see "VISUAL EMPHASIS
TOOLKIT" in `WORLD_PHILOSOPHY.md` for what's available and when to use it:

```
**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p1_l9.mp3`
[FX: static(2) — 250ms]
> He tries again, and this time you hear something inside click without engaging...
```

Format: `[FX: type(level),type(level) — duration]`, matching the `GlitchEffect` component's
own `type="bars(2),chromatic(4)"` syntax (1–5 scale). FX tags are production metadata, same
as audio tags — they get stripped out when a scene mirrors into `CHAPTER_1_STORY.md`.

---

## C1_S1_P1 — Cold Open (Float)
**Location:** Float's repair bay, Spokane Valley
**Contractor:** Float

---

**NARRATOR** `narrator/float/chapter_1/c1_s1_p1_l1.mp3`
> The diagnostic lead clicks into your collar before you're even conscious enough to feel
> the cold alloy. You wake up suspended in Float's rig, suspended in static. Your temple
> is throbbing — a rhythmic, dull-blade pulse right behind your left eye. The shop's
> ceiling is tilting slowly to the right.

**FLOAT** `float/chapter_1/c1_s1_p1_l2.mp3`
> "Hey. Eyes on me. Stay anchored."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p1_l3.mp3`
> Float isn't looking at your face; she's looking at the diagnostic rack behind you. She
> steps in, wrenching a snarl of braided wire out of the way.

**FLOAT** `float/chapter_1/c1_s1_p1_l4.mp3`
> "You blew through my lock, kicked my door off its track, and spewed three seconds of
> garbled machine code before your legs gave out. You want to tell me what that was?"

**NARRATOR** `narrator/float/chapter_1/c1_s1_p1_l5.mp3`
> You reach for the memory of five minutes ago. There's no door, no walk, no panic. Just
> an empty grey void where the timeline should be.
>
> You open your mouth to explain, but your vocal synth stutters. The words fracture into
> raw phonemes, lagging half a beat behind your jaw.

**PLAYER** `player/chapter_1/c1_s1_p1_l6.mp3`
> "I... can't— something's... missing. I don't know how I got here."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p1_l7.mp3`
> Float doesn't look surprised. She stops listening to your voice and starts listening to
> the telemetry — the way an engineer ignores a panicked driver to read the oil pressure.

**FLOAT** `float/chapter_1/c1_s1_p1_l8.mp3`
> "Stop forcing the vocal track. Just breathe. You're dropping frames."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p1_l9.mp3`
> With a sharp gesture, she snaps a holographic telemetry window into the air between
> you. Raw data cascades down in harsh amber text — far too fast for your glitched optics
> to parse, but Float's eyes track every line.
>
> Her brow hitches. A beat of dead silence hangs in the workshop.

**FLOAT** `float/chapter_1/c1_s1_p1_l10.mp3`
> "Your core temp is spiking, but that's just collateral. Look at this spike before you
> collapsed. Whatever spiked your system wasn't a spike at all. It was a background
> process. It was running in your stack for ten minutes before you dropped."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p1_l11.mp3`
> She doesn't wait for your answer. She already knows you don't have one.
>
> Float swaths her hand across the air, dragging a second pane alongside the first — her
> personal black-market archive. Decades of black-budget intrusion signatures, dead
> megacorp payloads, and corrupt firmware patterns begin cross-referencing against your
> spike.
>
> The progress bar doesn't even stretch. It snaps instantly to zero.

**FLOAT** `float/chapter_1/c1_s1_p1_l12.mp3`
> "Zero."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p1_l13.mp3`
> She drops her hand, staring at the empty query result. Float's voice drops half an
> octave — stripped of its usual defensive sarcasm, leaving only cold, mechanical
> calculation.

**FLOAT** `float/chapter_1/c1_s1_p1_l14.mp3`
> "Not a bad match. Not a partial corruption signature. Zero. I have payloads cataloged
> in this rig from before the grid fell, and your footprint doesn't share a single line
> of logic with any of them."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p1_l15.mp3`
> She turns away from the floating glass, her gaze drifting toward the heavy steel door
> you supposedly forced open. Her hand lingers near the lock manual override.

**FLOAT** `float/chapter_1/c1_s1_p1_l16.mp3`
> "I built this sanctuary on one rule: if it comes through that door, I know what it is
> and I know how to kill it. But whatever is sitting inside your head right now... it
> isn't in anyone's system."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p1_l17.mp3`
> Float straightens away from the window, and just like that, something in her posture
> resets — the crack sealing back over, replaced by the flat working calm of someone who
> fixes problems for a living. She crosses to the rig and starts stripping the diagnostic
> leads off you herself, quick and unceremonious.

**FLOAT** `float/chapter_1/c1_s1_p1_l18.mp3`
> "Here's where we are. I don't know what this is. I don't like not knowing. Those two
> things mean I'm going to keep pulling on this thread whether you pay me or not — but
> pulling on it costs me rig time, and rig time isn't free."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p1_l19.mp3`
> The last lead comes free from your collar with a small, cold pop. She tosses it onto the
> rack without looking.

**FLOAT** `float/chapter_1/c1_s1_p1_l20.mp3`
> "I've got a job sitting cold because it's not worth my time for what it pays. It's worth
> yours. Run it, and I keep digging on this in the background while you're out. That's the
> trade."

**PLAYER** `player/chapter_1/c1_s1_p1_l21.mp3`
> "Where."

**FLOAT** `float/chapter_1/c1_s1_p1_l22.mp3`
> "Decommissioned relay station, edge of the Valley grid. There's a memory core still
> intact in the wreck — old enough nobody's bothered stripping it. I want it before some
> scrapper beats you to it and sells it for parts instead of data."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p1_l23.mp3`
> Float pulls up a district map instead of saying anything else, one node glowing cold
> blue against the sprawl of the Valley grid.

**FLOAT** `float/chapter_1/c1_s1_p1_l24.mp3`
> "There. Don't collapse on me twice in one day. I've got a reputation to protect."

---

## C1_S1_P2 — Report Back (Float)
**Location:** Float's repair bay, Spokane Valley
**Contractor:** Float

---

**NARRATOR** `narrator/float/chapter_1/c1_s1_p2_l1.mp3`
> Float doesn't look up when the heavy door groans open. It's still hanging half an inch
> off its mounting track from when you kicked it in earlier. She's elbow-deep in an open
> chassis on the main bench, live wire-harbors sparking against her gauntlets.
>
> Without breaking her stride, she extends a grease-stained palm back toward you.

**FLOAT** `float/chapter_1/c1_s1_p2_l2.mp3`
> "Core. Give."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p2_l3.mp3`
> You drop the warm alloy module into her hand. She doesn't inspect it. It disappears into
> a heavy steel drawer with a hydraulic thud — filed away like the job was just a
> distraction to keep your hands busy.

**PLAYER** `player/chapter_1/c1_s1_p2_l4.mp3`
> "Did you pull anything off my read while I was in the field?"

**NARRATOR** `narrator/float/chapter_1/c1_s1_p2_l5.mp3`
> Her pneumatic driver goes silent. Float sets the tool down on the bench — slow, precise,
> the calculated pause of someone trying to organize bad news into manageable pieces.

**FLOAT** `float/chapter_1/c1_s1_p2_l6.mp3`
> "I stopped looking for a match in my archive. I ran your spike against itself. Evaluated
> the signature's delta over time instead of static code."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p2_l7.mp3`
> She snaps her fingers, sweeping a multi-layered spectral waveform into the air between
> you. It pulses with a frantic, jagged frequency — dense, tight, and unnervingly
> rhythmic.

**FLOAT** `float/chapter_1/c1_s1_p2_l8.mp3`
> "Noise is chaotic. It degrades. This isn't degrading. Every iteration of this wave is
> cleaner, sharper, and more optimized than the one before it. Like a program running a
> self-correction loop. Like something's practicing."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p2_l9.mp3`
> She cuts herself off mid-thought. For a fraction of a second, her jaw sets hard, like
> she just glimpsed something through the code she'd rather unsee.

**FLOAT** `float/chapter_1/c1_s1_p2_l10.mp3`
> "Data behaving like it has intent... that's not a storage problem. That's a structural
> one. It's built on something — referencing something — and none of it matches anything
> I've got catalogued. I fix hardware, not history. I can't tell you if what's underneath
> this is a design nobody's used in decades... or one that was never supposed to exist."

**PLAYER** `player/chapter_1/c1_s1_p2_l11.mp3`
> "If you can't read it, who can?"

**FLOAT** `float/chapter_1/c1_s1_p2_l12.mp3`
> "Axiom. University District. Cross-referencing a pattern against a hundred years of
> buried, pre-collapse architecture is his entire business model — he can dig up
> parallels out of that archive of his that I wouldn't even know how to search for. I
> already sent him the raw telemetry package. Get going."

**NARRATOR** `narrator/float/chapter_1/c1_s1_p2_l13.mp3`
> She's already reaching back into the open chassis on her bench, her fingers darting into
> the mechanical guts before you've even taken a step toward the door. But just before the
> sparks start flying again, her shoulder hitches.

**FLOAT** `float/chapter_1/c1_s1_p2_l14.mp3`
> "And don't tell him I care what he finds in that architecture of yours. He'll think
> I'm going soft, and I don't need Axiom thinking I have a weak spot."

---

## C1_S2_P1 — Axiom's Broken Rig (Axiom)
**Location:** Axiom's archive, University District
**Contractor:** Axiom

---

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p1_l1.mp3`
> Axiom's space is nothing like Float's — shelves stretching up into a darkness that
> never quite resolves into a ceiling, folders drifting through the air in slow,
> unhurried orbits, resettling themselves as though the whole room is quietly filing
> itself. You feel the difference in your teeth before you feel it anywhere else:
> unhurried, precise, the kind of quiet that comes from centuries of not being
> interrupted. Axiom looks up from behind a desk assembled more from memory than
> furniture, closing whatever he was reading with the particular care of someone who
> intends to come back to it.

**AXIOM** `axiom/chapter_1/c1_s2_p1_l2.mp3`
> "Float sent the file ahead of you. I've read it twice. Sit — you look like the walk
> here cost you more than it should have."

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p1_l3.mp3`
> A chair drifts into place opposite the desk. You lower yourself into it before you're
> entirely sure it's finished arriving.

**AXIOM** `axiom/chapter_1/c1_s2_p1_l4.mp3`
> "She's not wrong to send you to me. Her toolkit is built for structural repair, not
> exotic architecture. What she recorded doesn't look like corruption anyway. It looks
> like a signal correcting itself against feedback, which is a very particular kind of
> behavior. Do you know what that behavior usually is?"

**PLAYER** `player/chapter_1/c1_s2_p1_l5.mp3`
> "No."

**AXIOM** `axiom/chapter_1/c1_s2_p1_l6.mp3`
> "Learning. That's what it usually is. I don't say that to frighten you. I say it because
> I'd rather you hear the honest word from someone than spend the next hour guessing at a
> worse one."

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p1_l7.mp3`
> He doesn't flinch saying it, and somehow that steadies you more than if he had. Axiom
> rises and crosses to an oversized diagnostic reader built into the shelves — the kind of
> instrument meant for legacy silicon, not modern rigs — already talking through the next
> step before he reaches it.

**AXIOM** `axiom/chapter_1/c1_s2_p1_l8.mp3`
> "I want to lay your signal against the archive's oldest layers — properly, not a
> glance. That'll tell us whether it's isolated or spreading, and roughly how fast. It's
> not a pleasant comparison to run. It isn't a dangerous one either."

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p1_l9.mp3`
[FX: static(2) — 250ms, on "click without engaging"]
> He rests a hand on the housing's access panel. It doesn't open. He tries again, and this
> time you hear something inside click without engaging — a small, wrong sound in an
> otherwise silent room.

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p1_l10.mp3`
> Axiom's composure doesn't crack, exactly. It just goes still for a second, the way
> someone goes still when a plan quietly stops being available.

**AXIOM** `axiom/chapter_1/c1_s2_p1_l11.mp3`
> "Of course. Not today."

**PLAYER** `player/chapter_1/c1_s2_p1_l12.mp3`
> "What's wrong with it?"

**AXIOM** `axiom/chapter_1/c1_s2_p1_l13.mp3`
> "The resonance coil burned out a component I can't route around — the part that reads
> depth architecture instead of surface noise. Without it I can hand you a very confident
> guess. I don't deal in those if I can help it. The replacement's been sitting at a
> courier depot two nodes from here for three days. My usual runner won't touch it — the
> depot's changed hands and nobody's sure who's actually holding the access rights
> anymore."

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p1_l14.mp3`
> He turns back to you, and for a moment the warmth comes back into focus, deliberate,
> like he's choosing to spend it on you specifically.

**AXIOM** `axiom/chapter_1/c1_s2_p1_l15.mp3`
> "I know I'm asking you to run errands while something is actively happening to you. I
> don't love that either. But I would rather have the real number than a fast one, and
> right now the real number is on the other side of that depot door."

**PLAYER** `player/chapter_1/c1_s2_p1_l16.mp3`
> "Where do I go."

**AXIOM** `axiom/chapter_1/c1_s2_p1_l17.mp3`
> "UD-v9. Fetch the part, bring it back to the archive, and we'll find out what's actually
> happening to you."

---

## C1_S2_P2 — Report Back (Axiom)
**Location:** Axiom's archive, University District
**Contractor:** Axiom

---

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p2_l1.mp3`
> The part is smaller than expected—a matte-grey cylinder no bigger than a fingertip, its
> edges worn smooth from three days of sitting unclaimed. Axiom takes it with both hands,
> cradling it like something that used to belong to someone who mattered.

**AXIOM** `axiom/chapter_1/c1_s2_p2_l2.mp3`
> "Good. No detours, no complications. I was almost disappointed—I'd already started
> composing the apology I was going to owe you."

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p2_l3.mp3`
> He steps back to the reader, working the panel open with the patient, exact precision of
> a man who has done this a thousand times and will do it a thousand more. The replacement
> seats with a small, clean click—the sound the original should have made.

**AXIOM** `axiom/chapter_1/c1_s2_p2_l4.mp3`
> "There. Now we ask the question properly."

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p2_l5.mp3`
> He presses a lead against your temple—cold, sterile metal, nothing like Float's tangled
> nest of braided wire. The reader hums alive, low and resonant, shivering through your
> cheekbone. For a second, nothing happens.

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p2_l6.mp3`
[FX: static(3),flicker(2) — 500ms, on "snap silent at once"]
> Then the shelves shudder—then go still. Not the room. The sound: a hundred folders that
> were always faintly turning, rustling, snap silent at once. You feel it before you
> understand it—pressure behind your eyes, like the room just changed altitude.

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p2_l7.mp3`
> Pain shears through your skull, sharp and blinding—a line of white, electric heat drawn
> directly from ear to ear, searing along the neural housing at the base of your brain. It
> vanishes before you can gasp, but your hand flies to your temple. Your fingers come away
> trembling.

**AXIOM** `axiom/chapter_1/c1_s2_p2_l8.mp3`
> "Steady. That was the archive, not you—it doesn't like being asked questions it can't
> answer. And neither, apparently, do you."

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p2_l9.mp3`
> His breathing doesn't change. If anything it slows—the sound of a man leaning into a
> problem instead of flinching from it. As the reader finishes its pass, a dense column of
> raw telemetry scrolls beside him. He goes quiet—a heavy, sudden silence that has nothing
> to do with reading speed.

**AXIOM** `axiom/chapter_1/c1_s2_p2_l10.mp3`
> "Well."

**AXIOM** `axiom/chapter_1/c1_s2_p2_l11.mp3`
> "I told you it referenced things that no longer exist. I was wrong about the shape of
> that. It isn't referencing old architecture the way a museum references history. It's
> referencing it the way flesh references a frame it was built around."

**PLAYER** `player/chapter_1/c1_s2_p2_l12.mp3`
> "Meaning what."

**AXIOM** `axiom/chapter_1/c1_s2_p2_l13.mp3`
> "Meaning it isn't passive. I laid it against the deepest layer in this archive expecting
> alignment or silence. I got neither. For about four seconds, it reached."

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p2_l14.mp3`
> He says the word deliberately, weighing it against three others he liked less.

**AXIOM** `axiom/chapter_1/c1_s2_p2_l15.mp3`
> "Not spreading. Not corrupting anything of mine, you'll be relieved to hear. Reaching—the
> way a signal reaches for an origin point, or a dying limb twitches toward a pulse. It
> wasn't interested in my archive at all. It was using my system as a conduit to touch
> something further out."

**PLAYER** `player/chapter_1/c1_s2_p2_l16.mp3`
> "Further out than your archive."

**AXIOM** `axiom/chapter_1/c1_s2_p2_l17.mp3`
> "Further out than anything in this room. That's a grid question, not an archivist's
> question—whether a payload can reach through a rig, down a person's spine, and out into
> the public frequency. I study what things were. I don't study what they're actively doing
> to city infrastructure while anchored in living tissue."

**NARRATOR** `narrator/axiom/chapter_1/c1_s2_p2_l18.mp3`
> He sets the lead down. Doesn't reach for the next tool. The quiet stretches long enough
> that you start counting your own heartbeat before he breaks it.

**AXIOM** `axiom/chapter_1/c1_s2_p2_l19.mp3`
> "I'd like to tell you this narrows it down. It doesn't. It widens it. Twenty minutes ago,
> I thought you were carrying a dead artifact. Now I know you're carrying something ancient
> that is currently, actively trying to go somewhere else—and I have no idea whether that's
> better news, or considerably worse."

**PLAYER** `player/chapter_1/c1_s2_p2_l20.mp3`
> "Who do I see about the grid question."

**AXIOM** `axiom/chapter_1/c1_s2_p2_l21.mp3`
> "Veil. Downtown Core. If something is using the frequency itself to reach for a
> destination, she's the only one left who'd recognize the footprint before it arrives.
> Tell her I said it reached—she'll know exactly how unhappy that should make her."

---

*Document version 2.0 — C1_S2_P2 (Axiom's report-back) written and locked: the repaired
reader runs the real scan, the archive's ambient sound cuts out and a pressure/pain spike
lands (FX-tagged, escalated past C1_S2_P1's static(2) to static(3),flicker(2)), and Axiom's
read shifts from "old architecture" to "old architecture that's actively reaching for
something outside itself" — a grid-scale question outside his specialty. Scene closes with
an explicit referral to Veil (Downtown Core), locking Float → Axiom → Veil as the confirmed
chapter order so far. Sensory beats were revised twice: first pass leaned visual-only in three
spots (silent-room reaction, Axiom's expression, the weighing-something pause), second pass
re-anchored each in something producible in the mix — a stopped sound, a changed breath, a
timed silence — per the new VISUAL EMPHASIS TOOLKIT guidance in WORLD_PHILOSOPHY.md.
Previously, version 1.9 added the FX tag convention to the ID system (bracketed
`[FX: type(level) — duration]` cues, stripped from the Story mirror same as audio tags) and
tagged the first live example on C1_S2_P1_l9, the moment Axiom's diagnostic reader fails on
contact. Version 1.7 locked C1_S1_P1, C1_S1_P2, and C1_S2_P1 (through the job hand-off),
audio paths nested under chapter_1/ subfolders inside each Doc's existing folder. Float's
relay-station retrieval field job still not written; Veil's Scene 3 not yet started.*
