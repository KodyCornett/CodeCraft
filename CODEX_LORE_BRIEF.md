You're being brought in to help write lore documents for a system called the **Codex
Archive** in a cyberpunk hacking game called CodeCraft. This brief explains what the
system is, why it exists, what a "document" actually needs to contain mechanically, and
the hard rules you need to follow so nothing you write breaks the game's central mystery.

---

## Required reading first

Before writing anything, read these two files from the project (ask whoever's pasting
this in to attach them):

- **`WORLD_PHILOSOPHY.md`** — the world and character bible. SPLICE Frequency
  terminology, the Watcher / Splice Protocol mystery, full voice/personality profiles for
  the five Cyber Docs, and the prose standard everything in this game is held to.
- **`CHAPTER_1_STORY.md`** — the current, locked narrative canon, plus a section called
  "What the Player Should NOT Learn in Chapter 1." That section is the hard boundary for
  everything you write — the Codex system exists to hint at things just past that
  boundary, never to cross it outright.

Do **not** use `api/PROLOGUE_SCRIPT.md` as a reference for tone or lore — it still
contains an older, superseded concept (an artifact "installed" in the player by a third
party) that directly contradicts the current lore (a trapped person, the Watcher, reaching
in through the network). If you see anything resembling that framing, it's wrong; ignore it.

---

## What the Codex Archive actually is

CodeCraft's main story is deliberately withholding its own central mystery from the
player — the Watcher, Splice Protocol, the Persistence Theory. Every mandatory story beat
is written so the player ends up *less* certain, not more. That's intentional, and nothing
you write should shortcut it.

The Codex Archive is the pressure-release valve for that design: a completely optional
side-system where a curious player can go dig for early breadcrumbs about exactly the
stuff the main story won't hand them yet, plus personal history on the five Docs that the
critical path never has time to explore (Veil's hinted-at past with a substation incident
is the existing example — see `codex-veil-substation` in `SplicePageSeeder.php`).

Nothing in the Codex Archive is ever required. No timers, no penalties, no locked
progress behind it. It exists purely to make the world feel like it keeps going when the
player isn't looking, for the players who want to keep pulling on a thread.

---

## The mechanical loop (so you know what shape a "document" needs)

1. Completing certain missions makes a Codex "available" for that player.
2. The player earns **keys** by playing the Archive Extraction minigame at nodes while a
   Codex is active.
3. A key gets taken to the Codex Archive and resolved. This can come back as: the real,
   correct document; a **red herring** document (readable, real content, just not the
   one that matters); or nothing at all.
4. Any document, real or red herring, can be read purely for flavor once found.
5. Some documents — the actual Codex-tier ones — have a login with the password missing.
   The password (or, for harder threads, several separate credentials) is hidden inside
   *other* documents the player has to find and read. Some of those other documents are
   themselves red herrings.
6. Difficulty scales by how many separate documents a player has to cross-reference to
   solve it: an easy Codex is solvable from a single document; a hard one needs several,
   read in any order, pieced together by the player themselves.
7. Solving pays a small reward (creds and/or tech points) and unlocks further content.

The credential check is a plain text match — never gated by whether the player
"officially" found the answer through the intended path. If the right word is sitting
somewhere and a sharp reader spots it, that counts.

---

## What to actually write

Three categories, and most real documents should do double duty across more than one of
these rather than living in only one lane:

**Doc-personal threads** — backstory on Knuckle, Veil, Float, Axiom, or Patch that the
main story hints at but never has room to resolve. Needs to sound like *that specific
Doc's world*, not generic lore — reread their profile in `WORLD_PHILOSOPHY.md` before
writing anything tied to them.

**The bigger mystery** — breadcrumbs about the Watcher, Splice Protocol, and the
Persistence Theory. These should never confirm anything outright. A name half-mentioned,
a date that lines up with something found three documents later, a detail that only means
something once the player has read enough elsewhere. This is meant to reward players who
piece it together themselves, not hand them the answer.

**World texture** — the early history of the Splice Frequency, corporate/political
cover-ups, "dirty doings by the elite," the kind of ambient stuff that makes Spokane feel
like a real, lived-in city with more going on than the five districts the player visits.
This is the best place to plant the mystery breadcrumbs above — an old blog post about the
Frequency's early days is a much better hiding spot for a real clue than a document that
announces itself as important.

Format each document as: a **title**, its **type** (flavor or codex), which mission/node
context it's tied to if any, the **body text** itself (written like a real found
artifact — a news article, an internal memo, a forum post, an incident report, not
expository lore-dump prose), and if it's codex-tier: the missing credential(s), and which
other documents (by title/slug) contain the answer versus which are red herrings.

---

## Hard rules — do not break these

- **Never confirm the Watcher's identity, that Splice Protocol exists, or what it's for.**
  Hints and half-glimpses only. If a piece of writing would make a careful player *certain*
  rather than suspicious, it's gone too far.
- **The network is always called SPLICE / the Splice Frequency.** Never "the Matrix,"
  never anything that reads like a generic cyberspace cliché.
- **Every document has to be skippable.** Nothing here ever gates required progress.
- **Match the established voice for anything tied to a specific Doc.** Don't invent new
  personality traits that contradict their profile in `WORLD_PHILOSOPHY.md`.
- **Prose standard matches the rest of the game** — specific and sensory, not generic
  filler. If a document could be swapped into a different thread without anyone noticing,
  it isn't specific enough yet.
