# CodeCraft — Chapter 1 Planning Notes

> **SUPERSEDED — see `CHAPTER_1_STORY.md` instead.** This file's content (minus the
> embedded early scene draft, since dialogue now lives only in `api/CHAPTER_1_SCRIPT.md`)
> has been consolidated there. Kept here only until confirmed safe to delete.

### The Watcher Arc — supersedes the old "Ghost-Kernel Arc" framing
---

## Status

This is a planning document, not final dialogue. It captures the new Chapter 1 concept
as discussed, for `WORLD_PHILOSOPHY.md` (v2.0) to sit alongside. It does **not** yet
rewrite `api/PROLOGUE_SCRIPT.md` — that file still uses the old "Ghost-Kernel is a
compressed consciousness fragment" framing and the old ideological Cyber Doc voices.
See **Open Work** at the bottom before writing any new dialogue.

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
- General degradation that reads like illness, not damage — fatigue, disorientation, not just "low HP"

None of this should be described as corruption, a virus, or malware in any dialogue. The
player and the Docs should talk about it the way people talk about an illness nobody can
diagnose — because that's what it looks like from the inside, to everyone involved,
including the Docs.

---

## Why the Doc Visits Still Work as a Structure

The five-quest, five-district circuit from the old prologue still makes sense as a
shape: the player, scared and getting worse, goes from specialist to specialist looking
for an answer. What changes is *why* each stop happens and what the player walks away
with.

Old structure: each Doc decrypts one ideological "lens" on a scripted historical event,
building toward a thesis about systemic control.

New structure: each Doc examines the player directly, gives an honest professional read
from their own specialty, and none of the reads fully agree — because none of them have
ever seen this before. The player isn't collecting philosophical lenses. They're
collecting fragments of a diagnosis that doesn't resolve, which is what pushes them (and
the player, meta-textually) toward suspecting this isn't a tech problem at all.

Per `WORLD_PHILOSOPHY.md`, the specialist reads are:

| Doc | District | Specialty | Their read on the player |
|---|---|---|---|
| Knuckle | Browne's Addition | Physical hardware | Rig's under a load with no source. Hardware-honest, mechanism-blind. |
| Veil | Downtown | Infrastructure/grid | Pattern reads as grid-scale intrusion — but it's running inside one person. |
| Axiom | University District | Pre-collapse archives/history | Rig's architecture references systems that no longer exist — and some that never did. |
| Float | Spokane Valley | Memory & data archives | Zero matches against any known signature, ever. |
| Patch | North Spokane | Sensory immersion | Recognizes the shape of his own patients' symptoms — inverted. Closest to the truth, still not there. |

The order these are visited in can stay flexible or follow the existing district chain —
that's a sequencing decision, not a story one.

**Why each quest still involves a job.** The Docs aren't running charity diagnostics.
They want to help — this is a real, strange case and their professional curiosity is
genuinely engaged — but running tests costs them time, materials, and favors, and none of
that is free. So each one puts the player to work: the job pays for the diagnostic effort
the Doc is quietly putting in on the side. The old prologue's job-then-report structure
holds up fine under this motivation almost unchanged — what needs to shift is the
subtext. It's not "prove yourself" or "we're testing you as a subject." It's closer to
"I'll help you figure this out, but I can't do it for free, so here's how you cover it."

**Splice Protocol never comes up as a theory.** It's a fringe conspiracy theory nobody
credible believes in — see `WORLD_PHILOSOPHY.md` §SPLICE PROTOCOL & THE WATCHER. None of
the five Docs should ever suggest, even jokingly, that the player might have someone's
mind stuck in their rig. That idea isn't available to them. They'll reach for real
diagnoses — corrupted firmware, a bad implant, an unknown intrusion pattern — and come up
short, which is what should read as unsettling, not a near-miss on the truth.

---

## Chapter 1 — Cold Open (C1_S1_P1)

Chapter 1 does not start with the player choosing to see a Doc. It starts mid-crisis,
with no memory of how they got there.

**The scene:** The player wakes up at Float's location, already plugged into her
diagnostic rig — she's mid-test when he comes to. He's in agony: covered in sweat,
splitting headache, no memory of arriving. He showed up at her place uninvited, in a full
panic, saying something that sounded like garbled, robotic non-language, then collapsed.
His reflex systems didn't engage to break the fall — he hit the floor hard.

**FLOAT** (noticing he's awake)
> "Hey. Hey — you okay? You showed up at my place uninvited, in a panic, looking like
> your system was splitting apart, saying something that sounded like robot talk. What
> was that about?"

The player has no memory of any of it. Just the pain.

**FLOAT**
> "You collapsed. Just — dropped. Your head has to be killing you — your reflex systems
> didn't do a thing to stop you from smashing it into the floor."

**Production notes:**
- The "gibberish that sounds like robot talk" is almost certainly the Watcher's signal
  bleeding through mid-collapse — this can likely reuse/adapt the corrupted
  `[UNKNOWN_PROCESS: INJECTING]` broadcast style already written for the old Watcher
  Interrupts in `PROLOGUE_SCRIPT.md`, rather than inventing a new format.
- This answers part of the "how does the player move between Docs" open question from
  above: Doc #1 isn't chosen or routed to at all — the player ends up there involuntarily,
  no memory, no agency. Whatever happens *after* Float (referral, rumor, second collapse
  pointing somewhere else, etc.) is still open.
- This changes quest order — Float, not Knuckle, is now the first Doc the player sees in
  Chapter 1. Confirm whether the remaining four keep their old prologue order after that,
  or need to be resequenced too.

---

## What the Player Should NOT Learn in Chapter 1

- The Watcher's name or the term "the Watcher" as an identity, unless you want to seed it as an overheard/glitched fragment (optional, mirrors the old Watcher Interrupt broadcasts already in `PROLOGUE_SCRIPT.md` — those can likely be preserved almost as-is, since "corrupted signal saying a fragment of a name" already fits the new lore well).
- That Splice Protocol exists, or what it was for.
- That there is a person behind this at all — the horror of Chapter 1 is not knowing, not confirming.
- Any resolution or comfort. Every Doc visit should end with the player *less* certain, not more.

---

## Open Threads (do not resolve early)

- **What the Watcher ultimately wants the player to do** about Splice Protocol — stop a reboot, destroy the device, find him a way out, something else. Explicitly undecided; belongs to a later chapter. Don't let any Chapter 1 or Chapter 2 text commit to an answer.
- **"The true cost" the Watcher learned** from being trapped — what it actually is/means is not yet defined. Leave abstract/ominous in any near-term dialogue rather than specific.
- Whether the old **Watcher Interrupt broadcasts** (the corrupted `[UNKNOWN_PROCESS: INJECTING]` signals between quests in `PROLOGUE_SCRIPT.md`) stay as-is, get attributed more directly to the Watcher, or get reworked — they already fit this concept well and may need only light touch-ups, not a rewrite.
- **How the player moves from Doc to Doc.** In the old prologue, the Watcher's Interrupt broadcasts directly named the next Doc each time — effectively, the Watcher was routing the player himself. That's confirmed to be changing for Chapter 1, since the Watcher isn't supposed to be a visible guiding presence yet. Replacement mechanism not yet decided — candidates to raise with the writer: each Doc refers the player to a colleague who might know more (fits the "specialists cover for each other" dynamic already established), the player chases rumor/word of mouth through the underground fixer scene, or something else entirely.

---

## Open Work — Not Done Yet

`api/PROLOGUE_SCRIPT.md` still reflects the old concept end to end:

- The Docs currently investigate a "Ghost-Kernel" described as compressed consciousness/an artifact placed in the player deliberately by an unknown third party — this contradicts the new lore (it's the Watcher himself, reaching in, not an installed object).
- Doc dialogue still carries some of the old ideological voice and cross-doc tension framing.
- ~~Quest 4 (Axiom) dialogue~~ — **Resolved.** Quest 4 has been rewritten to match Axiom's shipped archivist characterization (see `api/PROLOGUE_SCRIPT.md`); the old "It is not a virus. It is not malware. It is a person" Ghost-Kernel framing and third-party-delivery explanation are gone.

This wasn't in scope for this pass — flagging it so it doesn't get lost. Recommend a
dedicated pass through `PROLOGUE_SCRIPT.md`, quest by quest, once the Doc voice rewrites
in `WORLD_PHILOSOPHY.md` v2.0 are approved.

---

*Document version 1.0 — new, written to capture the Watcher/Splice Protocol Chapter 1 concept.*
