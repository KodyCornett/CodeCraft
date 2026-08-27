# CodeCraft — Chapter 1 Script
### "Static" — The Watcher Arc
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

**Chapter openers** are the one exception — a short flavor/atmosphere piece before Scene 1,
outside the Scene/Part numbering entirely, tagged `C{n}_OPEN`. See CHAPTER OPENERS in
`WORLD_PHILOSOPHY.md`.

**Line-level audio IDs and folder structure.** The Prologue's audio (already recorded,
already referenced in `api/database/seeders/QuestStageSeeder.php`) lives flat inside each
Doc's folder — `axiom/a_s1_l1.mp3`, `narrator/float/f_s3_l2.mp3`, etc. Those files and
their references are untouched by this system.

Going forward, every chapter gets its own subfolder *inside* each existing Doc folder, so
the top-level structure stays organized by character (one voice, one folder) while each
chapter's lines stay self-contained for production. The one exception is the narrator: all
of the narrator's Chapter 1 lines live together in a single flat `narrator/chapter_1/`
folder, not nested per-Doc — the narrator is one voice regardless of whose scene it's
describing, and the `part_id` prefix on each filename already disambiguates which scene a
line belongs to, so per-Doc nesting was redundant folder overhead:

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
      a_s1_l1.mp3           ← Prologue, untouched (per-Doc nesting stays for Prologue)
    chapter_1/               ← flat — every Doc's narrator lines land here together
      c1_open_l1.mp3
      c1_s2_p1_l1.mp3
      c1_s4_p1_l1.mp3
  player/
    chapter_1/               ← new — Prologue has no voiced player lines
      c1_s1_p1_l6.mp3
```

Full path pattern for Chapter 1: `{speaker}/chapter_1/{part_id}_l{n}.mp3` for a Doc's own
lines, `narrator/chapter_1/{part_id}_l{n}.mp3` for narrator lines (flat, no Doc nesting),
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
**NARRATOR** `narrator/chapter_1/c1_s2_p1_l9.mp3`
[FX: static(2) — 250ms]
> He tries again, and this time you hear something inside click without engaging...
```

Format: `[FX: type(level),type(level) — duration]`, matching the `GlitchEffect` component's
own `type="bars(2),chromatic(4)"` syntax (1–5 scale). FX tags are production metadata, same
as audio tags — they get stripped out when a scene mirrors into `CHAPTER_1_STORY.md`.

**FX in field comms.** The same idea applies to `field_comms` lines (the DOC's in-field
voice-call check-ins, rendered by `FieldCommsWindow.vue` — distinct from hub scenes), just
as structured JSON instead of a bracket tag, since those lines live in the database rather
than prose: `{ text, audio?, speaker?, fx?: { type: string, duration?: number } }`. When
drafting field comms content here for reference, write it the same bracket-tag way for
readability; convert to the JSON shape when it's actually seeded.

---

## C1_OPEN — The City, Before

Flavor/atmosphere piece, not narrative — sits before Scene 1, outside the Scene/Part
numbering. See CHAPTER OPENERS in `WORLD_PHILOSOPHY.md`: strict parity with the player, no
outside-POV hints, just mood. Audio lives under the flat `narrator/chapter_1/` folder, same
as every other narrator line in this chapter.

---

**NARRATOR** `narrator/chapter_1/c1_open_l1.mp3`
> Spokane doesn't sleep so much as it flickers — neon breathing through rain nobody's
> bothered to name a color for in years. Underneath the corporate grid everyone agrees to
> see, a second signal runs the way blood runs under skin: unlicensed, unregulated, older
> than anyone currently using it. Runners call it the Splice Frequency. Everyone else
> doesn't call it anything, because everyone else doesn't know it's there.

**NARRATOR** `narrator/chapter_1/c1_open_l2.mp3`
> Tonight it's just another Tuesday on the grid — small hacks, small scores, the ordinary
> hum of a city that never fully goes dark.

**NARRATOR** `narrator/chapter_1/c1_open_l3.mp3`
> Somewhere in Spokane Valley, someone is about to have a very bad night, and won't
> remember how it started.

---

## C1_S1_P1 — Cold Open (Float)
**Location:** Float's repair bay, Spokane Valley
**Contractor:** Float

---

**NARRATOR** `narrator/chapter_1/c1_s1_p1_l1.mp3`
[FX: chromatic(2),flicker(1) — 700ms]
> The diagnostic lead clicks into your collar before you're even conscious enough to feel
> the cold alloy. You wake up suspended in Float's rig, suspended in static. Your temple
> is throbbing — a rhythmic, dull-blade pulse right behind your left eye. The shop's
> ceiling is tilting slowly to the right.

**FLOAT** `float/chapter_1/c1_s1_p1_l2.mp3`
> "Hey. Eyes on me. Stay anchored."

**NARRATOR** `narrator/chapter_1/c1_s1_p1_l3.mp3`
> Float isn't looking at your face; she's looking at the diagnostic rack behind you. She
> steps in, wrenching a snarl of braided wire out of the way.

**FLOAT** `float/chapter_1/c1_s1_p1_l4.mp3`
> "You blew through my lock, kicked my door off its track, and spewed three seconds of
> garbled machine code before your legs gave out. You want to tell me what that was?"

**NARRATOR** `narrator/chapter_1/c1_s1_p1_l5.mp3`
> You reach for the memory of five minutes ago. There's no door, no walk, no panic. Just
> an empty grey void where the timeline should be.
>
[FX: scramble(1) — 400ms]
> You open your mouth to explain, but your vocal synth stutters. The words fracture into
> raw phonemes, lagging half a beat behind your jaw.

**PLAYER** `player/chapter_1/c1_s1_p1_l6.mp3`
> "I... can't— something's... missing. I don't know how I got here."

**NARRATOR** `narrator/chapter_1/c1_s1_p1_l7.mp3`
> Float doesn't look surprised. She stops listening to your voice and starts listening to
> the telemetry — the way an engineer ignores a panicked driver to read the oil pressure.

**FLOAT** `float/chapter_1/c1_s1_p1_l8.mp3`
> "Stop forcing the vocal track. Just breathe. You're dropping frames."

**NARRATOR** `narrator/chapter_1/c1_s1_p1_l9.mp3`
> With a sharp gesture, she snaps a holographic telemetry window into the air between
> you. Raw data cascades down in harsh amber text — far too fast for your glitched optics
> to parse, but Float's eyes track every line.
>
> Her brow hitches. A beat of dead silence hangs in the workshop.

**FLOAT** `float/chapter_1/c1_s1_p1_l10.mp3`
> "Your core temp is spiking, but that's just collateral. Look at this spike before you
> collapsed. Whatever spiked your system wasn't a spike at all. It was a background
> process. It was running in your stack for ten minutes before you dropped."

**NARRATOR** `narrator/chapter_1/c1_s1_p1_l11.mp3`
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

**NARRATOR** `narrator/chapter_1/c1_s1_p1_l13.mp3`
> She drops her hand, staring at the empty query result. Float's voice drops half an
> octave — stripped of its usual defensive sarcasm, leaving only cold, mechanical
> calculation.

**FLOAT** `float/chapter_1/c1_s1_p1_l14.mp3`
> "Not a bad match. Not a partial corruption signature. Zero. I have payloads cataloged
> in this rig from before the grid fell, and your footprint doesn't share a single line
> of logic with any of them."

**NARRATOR** `narrator/chapter_1/c1_s1_p1_l15.mp3`
> She turns away from the floating glass, her gaze drifting toward the heavy steel door
> you supposedly forced open. Her hand lingers near the lock manual override.

**FLOAT** `float/chapter_1/c1_s1_p1_l16.mp3`
> "I built this sanctuary on one rule: if it comes through that door, I know what it is
> and I know how to kill it. But whatever is sitting inside your head right now... it
> isn't in anyone's system."

**NARRATOR** `narrator/chapter_1/c1_s1_p1_l17.mp3`
> Float straightens away from the window, and just like that, something in her posture
> resets — the crack sealing back over, replaced by the flat working calm of someone who
> fixes problems for a living. She crosses to the rig and starts stripping the diagnostic
> leads off you herself, quick and unceremonious.

**FLOAT** `float/chapter_1/c1_s1_p1_l18.mp3`
> "Here's where we are. I don't know what this is. I don't like not knowing. Those two
> things mean I'm going to keep pulling on this thread whether you pay me or not — but
> pulling on it costs me rig time, and rig time isn't free."

**NARRATOR** `narrator/chapter_1/c1_s1_p1_l19.mp3`
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

**NARRATOR** `narrator/chapter_1/c1_s1_p1_l23.mp3`
> Float pulls up a district map instead of saying anything else, one node glowing cold
> blue against the sprawl of the Valley grid.

**FLOAT** `float/chapter_1/c1_s1_p1_l24.mp3`
> "There. Don't collapse on me twice in one day. I've got a reputation to protect."

---

## C1_S1_P2 — Report Back (Float)
**Location:** Float's repair bay, Spokane Valley
**Contractor:** Float

---

**NARRATOR** `narrator/chapter_1/c1_s1_p2_l1.mp3`
> Float doesn't look up when the heavy door groans open. It's still hanging half an inch
> off its mounting track from when you kicked it in earlier. She's elbow-deep in an open
> chassis on the main bench, live wire-harbors sparking against her gauntlets.
>
> Without breaking her stride, she extends a grease-stained palm back toward you.

**FLOAT** `float/chapter_1/c1_s1_p2_l2.mp3`
> "Core. Give."

**NARRATOR** `narrator/chapter_1/c1_s1_p2_l3.mp3`
> You drop the warm alloy module into her hand. She doesn't inspect it. It disappears into
> a heavy steel drawer with a hydraulic thud — filed away like the job was just a
> distraction to keep your hands busy.

**PLAYER** `player/chapter_1/c1_s1_p2_l4.mp3`
> "Did you pull anything off my read while I was in the field?"

**NARRATOR** `narrator/chapter_1/c1_s1_p2_l5.mp3`
> Her pneumatic driver goes silent. Float sets the tool down on the bench — slow, precise,
> the calculated pause of someone trying to organize bad news into manageable pieces.

**FLOAT** `float/chapter_1/c1_s1_p2_l6.mp3`
> "I stopped looking for a match in my archive. I ran your spike against itself. Evaluated
> the signature's delta over time instead of static code."

**NARRATOR** `narrator/chapter_1/c1_s1_p2_l7.mp3`
> She snaps her fingers, sweeping a multi-layered spectral waveform into the air between
> you. It pulses with a frantic, jagged frequency — dense, tight, and unnervingly
> rhythmic.

**FLOAT** `float/chapter_1/c1_s1_p2_l8.mp3`
> "Noise is chaotic. It degrades. This isn't degrading. Every iteration of this wave is
> cleaner, sharper, and more optimized than the one before it. Like a program running a
> self-correction loop. Like something's practicing."

**NARRATOR** `narrator/chapter_1/c1_s1_p2_l9.mp3`
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

**NARRATOR** `narrator/chapter_1/c1_s1_p2_l13.mp3`
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

**NARRATOR** `narrator/chapter_1/c1_s2_p1_l1.mp3`
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

**NARRATOR** `narrator/chapter_1/c1_s2_p1_l3.mp3`
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

**NARRATOR** `narrator/chapter_1/c1_s2_p1_l7.mp3`
> He doesn't flinch saying it, and somehow that steadies you more than if he had. Axiom
> rises and crosses to an oversized diagnostic reader built into the shelves — the kind of
> instrument meant for legacy silicon, not modern rigs — already talking through the next
> step before he reaches it.

**AXIOM** `axiom/chapter_1/c1_s2_p1_l8.mp3`
> "I want to lay your signal against the archive's oldest layers — properly, not a
> glance. That'll tell us whether it's isolated or spreading, and roughly how fast. It's
> not a pleasant comparison to run. It isn't a dangerous one either."

**NARRATOR** `narrator/chapter_1/c1_s2_p1_l9.mp3`
[FX: static(2) — 250ms, on "click without engaging"]
> He rests a hand on the housing's access panel. It doesn't open. He tries again, and this
> time you hear something inside click without engaging — a small, wrong sound in an
> otherwise silent room.

**NARRATOR** `narrator/chapter_1/c1_s2_p1_l10.mp3`
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

**NARRATOR** `narrator/chapter_1/c1_s2_p1_l14.mp3`
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

**NARRATOR** `narrator/chapter_1/c1_s2_p2_l1.mp3`
> The part is smaller than expected—a matte-grey cylinder no bigger than a fingertip, its
> edges worn smooth from three days of sitting unclaimed. Axiom takes it with both hands,
> cradling it like something that used to belong to someone who mattered.

**AXIOM** `axiom/chapter_1/c1_s2_p2_l2.mp3`
> "Good. No detours, no complications. I was almost disappointed—I'd already started
> composing the apology I was going to owe you."

**NARRATOR** `narrator/chapter_1/c1_s2_p2_l3.mp3`
> He steps back to the reader, working the panel open with the patient, exact precision of
> a man who has done this a thousand times and will do it a thousand more. The replacement
> seats with a small, clean click—the sound the original should have made.

**AXIOM** `axiom/chapter_1/c1_s2_p2_l4.mp3`
> "There. Now we ask the question properly."

**NARRATOR** `narrator/chapter_1/c1_s2_p2_l5.mp3`
> He presses a lead against your temple—cold, sterile metal, nothing like Float's tangled
> nest of braided wire. The reader hums alive, low and resonant, shivering through your
> cheekbone. For a second, nothing happens.

**NARRATOR** `narrator/chapter_1/c1_s2_p2_l6.mp3`
[FX: static(3),flicker(2) — 500ms, on "snap silent at once"]
> Then the shelves shudder—then go still. Not the room. The sound: a hundred folders that
> were always faintly turning, rustling, snap silent at once. You feel it before you
> understand it—pressure behind your eyes, like the room just changed altitude.

**NARRATOR** `narrator/chapter_1/c1_s2_p2_l7.mp3`
> Pain shears through your skull, sharp and blinding—a line of white, electric heat drawn
> directly from ear to ear, searing along the neural housing at the base of your brain. It
> vanishes before you can gasp, but your hand flies to your temple. Your fingers come away
> trembling.

**AXIOM** `axiom/chapter_1/c1_s2_p2_l8.mp3`
> "Steady. That was the archive, not you—it doesn't like being asked questions it can't
> answer. And neither, apparently, do you."

**NARRATOR** `narrator/chapter_1/c1_s2_p2_l9.mp3`
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

**NARRATOR** `narrator/chapter_1/c1_s2_p2_l14.mp3`
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

**NARRATOR** `narrator/chapter_1/c1_s2_p2_l18.mp3`
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

## C1_S3_P1 — The Persistence Theory (Veil)
**Location:** Veil's node, Downtown Core
**Contractor:** Veil


**NARRATOR** `narrator/chapter_1/c1_s3_p1_l1.mp3`
> Veil's node renders the way it always has — a sprawling workspace under warm hanging
> lights, exposed conduits and maintenance terminals crowding every surface, status boards
> and infrastructure maps floating overhead like constellations nobody's bothered to name.
> Massive windows look out over the distant glow of the Frequency, rain drifting lazily
> against glass that was never meant to be decorative and isn't. Every cable here is
> labeled. Every tool has a place. The whole room still carries that same feeling — a
> station that was supposed to close years ago, and never did.

**NARRATOR** `narrator/chapter_1/c1_s3_p1_l2.mp3`
> She's watching six terminals at once when you arrive, dark hair loosely tied back, the
> long coat she wears instead of armor hung with pockets and utility straps. One display
> shifts to your signal. Her eyes hold on it half a second longer than the others got.

**VEIL** `veil/chapter_1/c1_s3_p1_l3.mp3`
> "Axiom's package. Twenty minutes ago. Four reads. Still not enough."

**NARRATOR** `narrator/chapter_1/c1_s3_p1_l4.mp3`
> She finally looks up. Not startled. Not concerned. Just tired, the way someone gets when
> strange problems stopped surprising them a long time ago.

**VEIL** `veil/chapter_1/c1_s3_p1_l5.mp3`
> "Sit. I'd rather look at you than a report about you."

**NARRATOR** `narrator/chapter_1/c1_s3_p1_l6.mp3`
> A chair rises from the floor, spare, unpadded — nothing like Axiom's or Float's. She runs
> a slow pass with something that reads more like a level than a scanner, checking you
> against a baseline only she can see.

**VEIL** `veil/chapter_1/c1_s3_p1_l7.mp3`
> "He's right that it's reaching. Wrong that it's rare."

**NARRATOR** `narrator/chapter_1/c1_s3_p1_l8.mp3`
> An alarm flashes amber somewhere behind her. She kills it with a flick of two fingers,
> without looking.

**VEIL** `veil/chapter_1/c1_s3_p1_l9.mp3`
> "I've seen this pattern. Once. A substation. Grid-scale. It doesn't run inside a person.
> Or it didn't."

**PLAYER** `player/chapter_1/c1_s3_p1_l10.mp3`
> "Where was it running?"

**VEIL** `veil/chapter_1/c1_s3_p1_l11.mp3`
> "Years ago. Before I did this for a living instead of against it."

**NARRATOR** `narrator/chapter_1/c1_s3_p1_l12.mp3`
> Something in how she says it tells you not to ask further, so you don't.

**VEIL** `veil/chapter_1/c1_s3_p1_l13.mp3`
> "There's a name for it. On the boards where people build religions out of infrastructure
> failures. They call it—"

**NARRATOR** `narrator/chapter_1/c1_s3_p1_l14.mp3`
[FX: static(4),flicker(3),bars(2) — 900ms, on the interrupted word]
> She doesn't finish. The word gets halfway out — *the Persist—* — and something inside
> your skull goes off like a struck bell.

**NARRATOR** `narrator/chapter_1/c1_s3_p1_l15.mp3`
> Pain doesn't describe it. Closer to feedback — a shriek pitched straight into the base of
> your neural housing, gone as fast as it hit, a thin thread of smoke curling up behind
> your left ear. Something back there just stopped working. Mid-sentence. Same as her.

**VEIL** `veil/chapter_1/c1_s3_p1_l16.mp3`
> "—Theory."

**NARRATOR** `narrator/chapter_1/c1_s3_p1_l17.mp3`
> She finishes it anyway. Quieter now. Like completing the sentence outranks whatever just
> happened to you.

**PLAYER** `player/chapter_1/c1_s3_p1_l18.mp3`
> "What was that?"

**VEIL** `veil/chapter_1/c1_s3_p1_l19.mp3`
> "Resonance dampener. Failed. Hold still."

**NARRATOR** `narrator/chapter_1/c1_s3_p1_l20.mp3`
> She's already moving — fast, precise, the reflex of someone who's handled hardware
> failure a thousand times and refuses to let this be anything else. Pulse. Burn. Readout
> again. By the time she's done, her voice is flat, controlled, all the way back inside its
> usual register.

**VEIL** `veil/chapter_1/c1_s3_p1_l21.mp3`
> "Rig under stress. Threw an error. Bad timing. That's all this is."

**NARRATOR** `narrator/chapter_1/c1_s3_p1_l22.mp3`
> She doesn't sound like she believes it. She sounds like someone choosing, on purpose, not
> to say the rest of it twice.

**VEIL** `veil/chapter_1/c1_s3_p1_l23.mp3`
> "Dampener's interface hardware. Not mine to fix. Knuckle can patch you functional. Go."

**PLAYER** `player/chapter_1/c1_s3_p1_l24.mp3`
> "And the name. The one you didn't finish."

**VEIL** `veil/chapter_1/c1_s3_p1_l25.mp3`
> "A mistake. Fringe theory. Debunked years ago — fabricated, by people who wanted a better
> story than the boring truth. Not relevant."

**NARRATOR** `narrator/chapter_1/c1_s3_p1_l26.mp3`
> She's back at the readouts before you're fully standing, already pulling the next task
> into the air — but her hand, for one second before it steadies, isn't as sure of itself
> as the rest of her.

---

## C1_S4_P1 — The Dead End (Knuckle)
**Location:** Knuckle's wagon, Browne's Addition
**Contractor:** Knuckle

---

**NARRATOR** `narrator/chapter_1/c1_s4_p1_l1.mp3`
> Knuckle's wagon is the same cramped, low-ceiling space it's always been — walls patched
> together from stolen network architecture, the seams still visible where different
> systems were forced to talk to each other. Medical readouts float at chest height, most
> of them running amber. His hulking asymmetrical frame doesn't turn when you walk in. One
> of his two diagnostic arms is already extended, pulling your signal before you've said a
> word.

**NARRATOR** `narrator/chapter_1/c1_s4_p1_l2.mp3`
> The readout it throws onto the wall comes back red, then narrows, then goes still on one
> specific spot behind your ear.

**KNUCKLE** `knuckle/chapter_1/c1_s4_p1_l3.mp3`
> "Whoever told you to hold still was right. You're lucky it cauterized instead of
> spreading."

**NARRATOR** `narrator/chapter_1/c1_s4_p1_l4.mp3`
> He pulls a handheld scanner into the other arm and runs it slow along the burn, the way
> he runs everything — no wasted motion, no urgency he hasn't decided to have. A burner
> cigarette materializes between two fingers of the arm he isn't using. He doesn't light it
> yet.

**PLAYER** `player/chapter_1/c1_s4_p1_l5.mp3`
> "Can you fix it?"

**KNUCKLE** `knuckle/chapter_1/c1_s4_p1_l6.mp3`
> "Housing, yeah. Whatever burned out inside it, no."

**NARRATOR** `narrator/chapter_1/c1_s4_p1_l7.mp3`
> He pulls the burned component free with a short mechanical click, turns it over once in
> the scanner's light, and doesn't bother hiding his opinion of it.

**KNUCKLE** `knuckle/chapter_1/c1_s4_p1_l8.mp3`
> "This isn't chassis. This is interface — the part that talks to your head, not the part
> that holds you together. I don't stock interface. Never have."

**PLAYER** `player/chapter_1/c1_s4_p1_l9.mp3`
> "So who does?"

**KNUCKLE** `knuckle/chapter_1/c1_s4_p1_l10.mp3`
> "Patch. North Spokane. Tell her Knuckle sent you for a standard dampener, not a consult —
> she'll try to turn it into one anyway."

**NARRATOR** `narrator/chapter_1/c1_s4_p1_l11.mp3`
> He finally lights the cigarette, already turning back to the readouts, done with you in
> the specific, efficient way of a man who never had more than two minutes to spend on
> anyone.

**KNUCKLE** `knuckle/chapter_1/c1_s4_p1_l12.mp3`
> "Bring it back. I'll seat it. Won't take long."

---

## C1_S5_P1 — Patch's Pickup (Patch)
**Location:** Patch's station, North Spokane
**Contractor:** Patch

**Mechanic note:** This scene fires only after the player completes the store purchase.
Knuckle's job unlocks the resonance dampener as a one-time item in Patch's catalog (via
`CyberDocInventoryService::grantCatalogItem()`, `stock_limit: 1`, `is_exclusive: true`,
`source: 'mission:c1_s4_p1'`). If the player can't afford it, they have to earn the creds
first — the scene below assumes the sale has already gone through.
**NOT YET IMPLEMENTED:** this is design-only. The dampener has no `Peripheral`/item row,
no seeder or mission-handler call to `grantCatalogItem()`, and no trigger wiring it to
Knuckle's referral. Dialogue is locked; the purchase mechanic still needs real engine work.

---

**NARRATOR** `narrator/chapter_1/c1_s5_p1_l1.mp3`
> Patch's station is an old maintenance dig buried beneath North Spokane — exposed pipes,
> concrete walls, bundles of cable disappearing into the dark like roots. Nothing here was
> built to be lived in. Somebody clearly changed their mind anyway: plants grow under
> grow-lamps in the corner, a kettle simmers on a hotplate that has no business still
> working, and half a dozen terminals drift lazily through the air, opening and closing to
> a logic only she seems to track.

**NARRATOR** `narrator/chapter_1/c1_s5_p1_l2.mp3`
> She's got both arms inside an open panel when you arrive, one sleeve rolled up, the other
> forgotten, dark hair tied back with something that wasn't originally meant for hair.

**PATCH** `patch/chapter_1/c1_s5_p1_l3.mp3`
> "Knuckle called ahead. Said 'dampener, not a consult,' like that's a sentence he gets to
> finish for me."

**PLAYER** `player/chapter_1/c1_s5_p1_l4.mp3`
> "It's just the part. I'm kind of in a hurry."

**PATCH** `patch/chapter_1/c1_s5_p1_l5.mp3`
> "Sure. Course you are."

**NARRATOR** `narrator/chapter_1/c1_s5_p1_l6.mp3`
> The sale's barely finished processing — the case already in your hand — when she does
> the thing every Doc apparently can't help doing: stops treating you like a transaction
> and starts treating you like a patient.

**PATCH** `patch/chapter_1/c1_s5_p1_l7.mp3`
> "Huh."

**PLAYER** `player/chapter_1/c1_s5_p1_l8.mp3`
> "What?"

**PATCH** `patch/chapter_1/c1_s5_p1_l9.mp3`
> "Nothing you need to hear standing up in my doorway. Take the part. Get it seated. Come
> back when you've got twenty minutes I can actually use."

**NARRATOR** `narrator/chapter_1/c1_s5_p1_l10.mp3`
> She's already back at the panel, not waiting for you to agree — but she says it again
> anyway, quieter, like she wants to make sure it landed.

**PATCH** `patch/chapter_1/c1_s5_p1_l11.mp3`
> "I mean that. Not a threat. Just — twenty minutes. I think I want to see this properly."

---

## C1_S4_P2 — Repair (Knuckle)
**Location:** Knuckle's wagon, Browne's Addition
**Contractor:** Knuckle

---

**NARRATOR** `narrator/chapter_1/c1_s4_p2_l1.mp3`
> Same wagon, same amber readouts drifting at chest height. Knuckle doesn't ask if you got
> it — just holds one of his diagnostic arms out, palm up, waiting.

**PLAYER** `player/chapter_1/c1_s4_p2_l2.mp3`
> "Patch says hi. Sort of."

**KNUCKLE** `knuckle/chapter_1/c1_s4_p2_l3.mp3`
> "She never says hi. Sit."

**NARRATOR** `narrator/chapter_1/c1_s4_p2_l4.mp3`
> He has the panel open before you're fully seated, the burned housing already out and set
> aside like he's been holding the shape of it in his head since you left. The new
> component goes in without ceremony — one motion, a short mechanical click.

**KNUCKLE** `knuckle/chapter_1/c1_s4_p2_l5.mp3`
> "Give it a second."

**NARRATOR** `narrator/chapter_1/c1_s4_p2_l6.mp3`
> You feel it before the readout even changes — a cold, precise pressure behind your ear,
> there and gone, like something exhaling on your behalf. The low, constant wrongness
> that's been sitting in that spot since Veil's office goes with it. On the wall, the
> readout follows a beat later: red, then amber, then a flat, uneventful green.

**PLAYER** `player/chapter_1/c1_s4_p2_l7.mp3`
> "That's it?"

**KNUCKLE** `knuckle/chapter_1/c1_s4_p2_l8.mp3`
> "That's it. Told you it wouldn't take long."

---

## C1_S4_P3 — Still Live (Knuckle)
**Delivery:** `FieldCommsWindow` — unprompted field comms check-in, not a hub scene. Fires
wherever the player currently is, not tied to a specific node arrival. Voice-only, one-way,
with an optional trailing player acknowledgment per `useFieldComms.js`'s design. Drafted
here in bracket-tag shorthand per the FX-in-field-comms convention; convert to the
`field_comms` JSON shape (`{ text, audio?, speaker?, fx? }`) when actually seeded.

---

**KNUCKLE** `knuckle/chapter_1/c1_s4_p3_l1.mp3`
> "Hey. It's me. Nothing's wrong with the new part — don't worry about that."

**KNUCKLE** `knuckle/chapter_1/c1_s4_p3_l2.mp3`
> "The old one. The one I pulled out of you. I didn't scrap it — left it on the bench,
> figured I'd strip it for parts later."

**KNUCKLE** `knuckle/chapter_1/c1_s4_p3_l3.mp3`
[FX: flicker(1) — 300ms, on "still drawing"]
> "It's still drawing current. No host. No reason to. Sitting on my bench pulling power
> like it thinks it's still seated in you."

**KNUCKLE** `knuckle/chapter_1/c1_s4_p3_l4.mp3`
> "Don't know what that means. Not gonna pretend I do. Just didn't feel right sitting on
> it."

**PLAYER** `player/chapter_1/c1_s4_p3_l5.mp3` *(acknowledgment)*
> "...Yeah. Good call. Thanks, Knuckle."

---

## C1_S3_P2 — We Need to Speak (Veil) — CHAPTER CLOSE
**Delivery:** Starts in `FieldCommsWindow` (unprompted, wherever the player is), same as
Knuckle's callback — then hands off to the full `WatcherSignal` cinematic mid-call, then
`ChapterTitleCard` for Chapter 2. This is the first time `WatcherSignal` fires anywhere in
Chapter 1 — every prior escalation beat stayed in the ambient `GlitchEffect` tier
deliberately, saving the full cinematic for exactly this moment. Ends Chapter 1.

**NOT YET IMPLEMENTED:** design-only, same as every other cross-component trigger in this
chapter. Nothing currently wires `FieldCommsWindow` into `WatcherSignal`, or `WatcherSignal`'s
`reboot` phase into `ChapterTitleCard`. Chapter 1 has no seed data at all yet regardless.

---

**VEIL** `veil/chapter_1/c1_s3_p2_l1.mp3`
> "Well. Look at that."

**VEIL** `veil/chapter_1/c1_s3_p2_l2.mp3`
> "Ran your telemetry against every pattern I could pull. Couldn't find a clean line
> between your signal and a theory I called fabricated three days ago."

**VEIL** `veil/chapter_1/c1_s3_p2_l3.mp3`
> "Axiom's archive did the actual sorting. I just asked it the right question."

**PLAYER** `player/chapter_1/c1_s3_p2_l4.mp3`
> "What. Out with it already."

**VEIL** `veil/chapter_1/c1_s3_p2_l5.mp3`
> "...Persistence Theory."

**[WATCHER SIGNAL TRIGGER]** — Veil's line cuts dead mid-word, the call ripped away rather
than ended. `WatcherSignal` takes the full screen through its existing phase machine:

- **breach** — full-assault glitch + red strobe, `Intrusion.mp3` plays, same as any other
  breach.
- **override** — the override box slams in as usual.
- **intrusion** — token reveal fires against a `signal_text` of **"PERSISTENCE THEORY"**
  instead of the Watcher's usual generic corrupted text. Pass 1 snaps to the corrupted
  (zalgo) form; ~2s later, Pass 2 re-scrambles and snaps clean — the player watches the
  Watcher itself resolve the exact words Veil just said, as if it heard her say them and is
  handing them back. No further explanation attached — the theory's actual content stays
  undefined per "What the Player Should NOT Learn in Chapter 1."
- **blackout** → **reboot** — standard reboot sequence, existing player-data reboot lines
  (`handle` / `persona` / `persona_desc`), unchanged from how `WatcherSignal` already works
  elsewhere.

**[CHAPTER TITLE CARD]** — The instant `WatcherSignal` emits `complete`, `ChapterTitleCard`
fires: `chapterNumber="2"`, `title="Persistence"`. Chapter 1 ends here — no further dialogue,
no player acknowledgment, no resolution. The title card is the last thing on screen.

---

*Document version 2.9 — Flattened the narrator audio folder convention: all of Chapter 1's
narrator lines now live together under a single `narrator/chapter_1/` folder instead of
being nested per-Doc (`narrator/{doc}/chapter_1/`), matching how the files were actually
organized during production. The `part_id` prefix on each filename already disambiguates
which scene a line belongs to, so per-Doc nesting was redundant. Updated the documented
convention and folder-tree example, and rewrote all 56 existing narrator audio tags across
every locked scene (`C1_S1_P1` through `C1_S3_P2`) to drop the `{doc}` path segment.
C1_OPEN's narrator tags were already flat and needed no change. Previously, version 2.8 — C1_S3_P2 ("We Need to Speak," Veil) written and locked: 5 spoken
lines, closing Chapter 1. Delivered as a `FieldCommsWindow` call that gets forcibly taken
over by `WatcherSignal` mid-word — the Watcher reacting to its own signal being named by two
Docs cross-referencing data, the biggest examination of the pattern yet, which is why this
is the chapter's first and only full `WatcherSignal` use rather than another ambient
`GlitchEffect` escalation. The intrusion phase's token reveal is fed "PERSISTENCE THEORY" as
its `signal_text`, so the corrupt-to-clean resolve reads as the Watcher echoing back exactly
what Veil just said — confirmation that it's listening, not confirmation of what the theory
means. `ChapterTitleCard` fires immediately after reboot for Chapter 2 ("Persistence") — the
first real use of the trigger, deliberately not touching the already-shipped Prologue flow
per the earlier caution, since this is new Chapter 1→2 content. Entire sequence flagged
NOT YET IMPLEMENTED — no engine wiring exists between these three components yet, and
Chapter 1 has no seed data at all regardless. This closes the Chapter 1 closing arc that
began with C1_S3_P1: Veil is now non-contiguous across the chapter exactly as planned in
INVESTIGATIVE STRUCTURE, and both loose ends (Knuckle's still-live part, Veil's Persistence
Theory match) land within one scene of each other rather than resolving separately.*

*Document version 2.7 — C1_S4_P3 ("Still Live," Knuckle) written and locked: 5 lines, his
shortest beat yet, delivered through `FieldCommsWindow` rather than a hub scene per the
Open Threads plan — unprompted, landing wherever the player is. He reports the fact and
refuses to theorize, per PROBLEM-SOLVING STYLE, which is what makes the moment land. FX is
a single light flicker, not the static escalation vocabulary — this is an ambient wrongness
in the world, not the Watcher reacting to an examination, so it stays in the "sensory
disorientation" bucket per the two-vocabulary rule. No narrator lines available (voice-only
delivery), so the eeriness is carried entirely through Knuckle's word choice instead — per
"the prose is the whole experience" and "gameplay alone can't build the world."*

*Document version 2.6 — C1_S4_P2 ("Repair," Knuckle) written and locked: 8 lines, shorter
than his first scene per PROBLEM-SOLVING STYLE. Adds a felt-sensation beat to the repair
(cold pressure behind the ear, tied explicitly back to the injury site from Veil's scene)
so the moment isn't purely external/visual — per the "give the mix something to build" rule
and the reminder that these are body-connected parts the player experiences, not props.*

*Document version 2.5 — C1_S5_P1 ("Patch's Pickup," Patch) written and locked: 11 lines.
Verified against Patch's shipped Prologue introduction before drafting (maintenance dig,
grow-lamps, kettle, drifting terminals, clipped/staccato dry voice). Structured around a
real store purchase, not a free handoff — the resonance dampener is a one-time catalog item
gated by `CyberDocInventoryService::grantCatalogItem()`, so a short-on-cash player has to
earn it first. The scene's opening reaction beat is written to fire as a direct consequence
of the completed sale (she looks closely at what she just sold into, not at a handed-over
part), per the user's sequencing note. Saves her real diagnostic read for later — this is a
dead-end-fast intro per PROBLEM-SOLVING STYLE, same as Knuckle's.*

*Document version 2.4 — C1_S4_P1 ("The Dead End," Knuckle) written and locked: 12 lines,
noticeably shorter than Veil's scene per PROBLEM-SOLVING STYLE. Verified against Knuckle's
shipped Prologue introduction before drafting — added the hulking asymmetrical frame, two
diagnostic arms, handheld scanner motion, and burner cigarette detail that a first pass had
missed, and picked up "wagon" as what he calls his own node. Confirms the resonance
dampener is interface hardware, not chassis, and refers the player to Patch by name for the
part specifically — not a full diagnostic referral. Previously, version 2.3 — C1_S3_P1 (Veil, "The Persistence Theory") written and locked: her
node and physical description matched against the shipped Prologue seeder (warm
maintenance-workspace environment, six-terminal multitasking, clipped staccato dialogue
style) rather than invented fresh. Scene lands the chapter's biggest escalation yet —
Veil's mid-word interruption saying the fringe theory's name, a fried "resonance dampener"
(deliberate echo of Axiom's burned-out resonance coil), FX escalated past C1_S2_P2's peak
to static(4),flicker(3),bars(2). She rationalizes the reaction in the moment and refers the
player to Knuckle without revealing what she was about to say — sets up her later
unprompted transmission (C1_S3_P2, not yet written). Previously, version 2.2 added
C1_OPEN, the chapter's flavor/atmosphere opener (3 lines,
sits before Scene 1, outside Scene/Part numbering) — city-scale mood-setting, strict parity
with the player, no hint of an external agent. See CHAPTER OPENERS in WORLD_PHILOSOPHY.md.
Previously, version 2.1 added FX tags to C1_S1_P1's cold open (l1: chromatic(2),flicker(1)
on waking mid-collapse; l5: scramble(1) on the vocal stutter), using a deliberately
different effect vocabulary than Axiom's static-based response cues — these are sensory
disorientation, not the signal reacting to examination, and shouldn't read as part of that
escalation ladder. Also documented FX authoring for field_comms (structured JSON, same
bracket-tag shorthand for drafting) now that `useFieldComms.js`/`FieldCommsWindow.vue` are
confirmed built (FX support added to FieldCommsWindow.vue to match). Float's and Axiom's
field comms content discussed but not yet written in; still pending, along with Chapter 1's
arc/seeder. See VISUAL EMPHASIS TOOLKIT in WORLD_PHILOSOPHY.md for the two-vocabulary rule.
Previously, version 2.0 locked C1_S2_P2 (Axiom's report-back): the repaired
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
