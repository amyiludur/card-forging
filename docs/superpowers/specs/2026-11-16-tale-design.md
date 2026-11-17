# Tales — design

*Approved 2026-11-16. A project, built in two parts. Part 1 is built (plan: `docs/superpowers/plans/2026-11-16-tale-part-1.md`).*

**Changed in building Part 1:** reward cards print and pool as group `player`, like every player card, rather than a `reward` group of their own; the story prints on the Tale's own book page (`/print/tale/{slug}/book`) with the log sheet after it, rather than through the rulebook printer.

## Intent

The designer wants to design **campaign boxes**, the way Marvel Champions sells them: a box holding
several scenarios played in order, a story running through them, and cards that only come out when
the campaign is played. The game calls one of these a **Tale** (the designer's choice; *Arc* was the
other candidate). The tool's job is to let a Tale be written, printed and playtested — not to decide
how any Tale plays.

## Decided by the designer

- **Name:** Tale.
- **Carry-over between scenarios exists.** When a scenario ends the players write things down, and
  the next scenario reads them. **Each Tale has its own way of doing it** — its own entries and its
  own rewards — so nothing about carry-over is built in.
- **Difficulty modes:** a Tale has 2 or 3 of them, and they change what happens between scenarios
  (example: hard keeps your damage, easy heals it).
- **Rewards** are earned through the Tale.
- **Three kinds of Tale card:** reward cards for players; entity-side cards that work like a
  module's (any entity card type, split cards, board cards); and Tale cards of the Tale's own (story
  cards, the box's own mechanic).
- **Both a printed log sheet and a table that keeps the log.**
- **The table applies only what it can, and it is not important that it applies anything by
  itself.** It records the log and shows it; the players apply what an entry means.
- **Approach A:** a Tale's entity-side cards are **modules the Tale owns**.

## Goals

1. Create and edit a Tale: its story, its scenarios in order, its difficulty modes, its log
   entries, and its three kinds of card.
2. Print everything a Tale box holds, including a log sheet laid out from the Tale's own entries.
3. Round-trip a Tale through `design/` like everything else.
4. Playtest a Tale on the goldfish table across several sittings, with the log kept between them.

## Non-goals

- Wiring a log entry to a table effect ("this number becomes starting damage"). Possible later, per
  entry, if one keeps coming up.
- Any rule for what a mode does. A mode is a name and the designer's text.
- A Tale editor that writes scenarios. A Tale **lists** scenarios; they stay ordinary scenarios.

## Anti-goals (a failure even if it works)

- **Hardcoding a carry-over rule**, such as "health carries over". Each box is its own.
- **A Tale owning or copying a scenario.** The Kraken stays the Kraken whether or not a Tale plays
  it, and stays playable on its own.
- **Tale cards leaking into ordinary play** — a Tale's modules appearing in a scenario's normal
  module picker.
- **Turning a placeholder into a decision.** Every mode, entry and card defaults to placeholder.

## Approaches

- **A — a Tale owns modules (picked).** `modules.tale_id`, nullable. A module already holds every
  entity card type, split cards, board cards, beat-added cards, a set icon and a town district;
  printing, deck assembly and the design file for it already exist. What is new is the Tale itself.
  Trade-off: a set of Tale cards *is* a module underneath, so the editor must name it as the Tale's.
- **B — entity cards get a third owner, the Tale.** Rejected: every "scenario or module" rule
  (print, assembly, set icon, town, export) grows a third case for the same result.
- **C — scenario, module and Tale become one generic card set.** Rejected: the cleanest long-run
  shape, but a rework of things that work, and the Tale does not need it.

## Design — Part 1: the Tale

### Data

| Table | What | Notes |
|---|---|---|
| `tales` | `slug`, `name`, `status`, `overview`, `set_icon`, `sort` | `overview` is the box's opening story, free text |
| `tale_steps` | `tale_id`, `scenario_id`, `sort`, `intro`, `outro`, `notes` | one per scenario in the Tale, in play order; `intro` is read before the scenario, `outro` after it, `notes` is setup changes in the designer's words |
| `tale_step_module` | `tale_step_id`, `module_id` | which modules join that scenario in the Tale |
| `tale_modes` | `tale_id`, `name`, `text`, `sort`, `is_placeholder` | the difficulty modes; `text` is what happens between scenarios in that mode |
| `tale_log_entries` | `tale_id`, `name`, `kind`, `scope`, `sort`, `is_placeholder` | `kind`: `number`, `check`, `note`, `rewards`; `scope`: `party` or `character` |
| `tale_cards` | `tale_id`, `slug`, `name`, `qty`, `type_line`, `text`, `flavour`, `sort`, `is_placeholder` | the Tale's own cards |
| `modules.tale_id` | nullable FK | set = a Tale's module |
| `player_cards.tale_id` | nullable FK | a reward card; role `reward` |

- **A step's modules can be the Tale's own or ordinary ones.** That is what lets scenario 2 of a Tale
  name a shared module too.
- **A step names a scenario, it does not own it.** Deleting a Tale deletes its steps, modes, entries,
  Tale cards, reward cards and **its own modules** — never a scenario, never an ordinary module.
  Deleting a scenario a step names deletes that step (cascade) — *builder's call*; the Tale page
  then shows one step fewer, which is the honest result.
- **A player card belongs to a character, a domain or a Tale — exactly one.** `PlayerCard::owner()`
  learns the third; `Tale::ROLES` is `['reward', 'upgrade']`, so a reward can have an upgrade like
  any other card. `siblings()` scopes by the Tale the same way, so upgrade links stay inside it.
  Every place that says "character or domain" gets the third case — the player-card editor's owner,
  the card face's colours (a reward card has no hero; it falls back to the Tale's set icon in the
  foot and the default dark head — *builder's call*), print, pocket.

### Hiding a Tale's modules from ordinary play

`Module::scopeShared()` (no `tale_id`) is what the scenario module picker, the table's setup screen
and `/modules` index list. The Tale's page lists its own. `DeckAssembly::for()` keeps accepting any
slug — a Tale game passes them explicitly — so assembly itself does not change.

### Pages

- **`/tales`** — the list, like `/modules`.
- **`/tales/{slug}`** — tabs, following the scenario page:
  - **Story:** overview, and each step's intro and outro in order, read top to bottom as the box
    would be read.
  - **Scenarios:** the steps — pick a scenario, reorder, choose which modules join it (the Tale's
    own listed first), notes.
  - **Modes:** add, rename, reorder, the text of each.
  - **Log:** the entries — name, kind, scope — with the printed log sheet previewed beside them.
  - **Cards:** the Tale's modules (link through to the ordinary module page, which shows *"Part of
    the Tale X"*), the Tale cards, and the reward cards.
- **The module page** of a Tale module says whose it is and links back.

### The Tale card face

A new card kind, `kind === 'tale'`: portrait, a head with the name and the Tale's set icon, a type
line (free text — "Story", "Ship" — not a card type row, so no edits in five places), the text
through the markup, and a flavour line above the body like a beat's. Two halves as always: the
`tale` branch of `CardPreview.vue` and `.tale-card` in `resources/views/print/partials/card.blade.php`.

### Printing

- Print groups `tale` (Tale cards) and `reward` (reward cards) join `PrintCatalogue::GROUPS`, so the
  pool and the picker get them for free. Tale modules print as modules already do.
- `/print/tale/{slug}` prints the whole box: Tale cards, reward cards, and every module the Tale owns.
- **The log sheet** is its own printed page, like the rulebook and unlike the card sheet:
  `resources/views/print/tale-log.blade.php`, inline CSS, rendered to PDF through `PdfRenderer`.
  Layout:

  ```
  THE TALE OF THE DROWNED COAST — campaign log
  Mode: [ ] Story  [ ] Standard  [ ] Hard          Players: ____________

  Party                      | 1 Kraken | 2 Pale Fever | 3 ... |
  Ship damage (number)       |          |              |       |
  The lighthouse fell (check)|   [ ]    |     [ ]      |  [ ]  |
  Rewards earned             |          |              |       |
  Notes                      |          |              |       |

  Character: ______________  (one block per seat, 4 blocks — builder's call)
  Damage kept (number)       |          |              |       |
  ```

  Rows come from the log entries, columns from the steps. A `rewards` entry is a wide blank box. The
  sheet prints the mode names but none of their text — the text is on the rules side (see pocket).
- **The Tale's story** prints through the rulebook sheet: a Tale is rendered as a document (overview,
  then each step's intro and outro under the scenario's name, then each mode and its text) and
  offered alongside the rules documents — *builder's call* on whether it is one document or one per
  Tale; one per Tale is the guess.

### Design folder

- `design/data/tales/<slug>.json` holds the Tale: name, status, overview, set icon, `steps`
  (scenario slug, module slugs, intro, outro, notes), `modes`, `logEntries`, `cards` (Tale cards) and
  `rewards` (reward cards, written like a domain's `cards`/`upgrades`).
- A Tale's modules stay in `design/data/modules/<slug>.json` with one extra key, `"tale": "<slug>"`,
  **written only when set**, so every existing module file exports byte for byte.
- The `tales/` directory is made on the way past, so a folder with no Tales does not grow one.
- Import reads both and, within a Tale, prunes the steps, modes, entries and cards its file dropped.
  A Tale with no file at all is **kept and reported**, the same as a module the folder does not have
  (changed in review: deleting it would take its modules and every card in them). A step naming a
  scenario that does not exist is reported and skipped, not invented.

### Pocket page

Tales get a section: each Tale's story, its modes, its log entries, and its cards grouped by kind.
Reading only, like the rest of the page.

### Markup

A Tale card has no scenario, so `{dreadRule}`, `{dreadAmount}` and `{omenAmount}` print as `?…`, the
same as a module card. `{this}` works.

## Design — Part 2: the table keeps the log

*Built 2026-11-17 (plan: `docs/superpowers/plans/2026-11-16-tale-part-2.md`). Changed in building it:*
*no `step` column — the next scenario is the first one with no result; a reward is dealt by its own
start zone (deck by default, as below); only the next scenario is offered (playing out of order is
open question 2); a `?run=` opened on another scenario is ignored and named. Also built at the
designer's request: card markup on every piece of Tale text, a step quoting its own scenario's
Dread tokens.*

### Data

| Table | What |
|---|---|
| `tale_runs` | `tale_id`, `name`, `tale_mode_id` (nullable), `step` (the next step to play), timestamps |
| `tale_run_results` | `tale_run_id`, `tale_step_id`, `values` (JSON), `played_at` |

`values` is keyed by log entry id, and for a `character` entry then by character slug:
`{"12": 3, "13": true, "14": {"gunslinger": 4, "runesmith": 1}, "15": {"gunslinger": [88]}}` — a
`rewards` entry holds player card ids. A run is a fact about the designer's playtests, not the game,
so `design:export` knows nothing about it — the same as a saved deck or a print preset.

### Flow

1. **`/tales/{slug}`** gets a **Playthroughs** list: start one (a name, a mode), see each with its
   log so far as a read-only grid shaped like the printed sheet.
2. **Play the next step** opens `/scenarios/{scenario}/play?run={id}`. The table's setup screen, in a
   run:
   - fixes the modules to the step's (they are the Tale's choice) — *builder's call* whether players
     may still add free-slot modules; they may, because the Tale's modules are added on top and the
     slot count is reported as it is today;
   - shows the step's intro and notes, the mode's text, and **the log so far** for the party and for
     each character whose saved deck is seated;
   - **adds each seated character's earned reward cards to their deck** — the one thing applied
     automatically, because "add this card to the deck" means the same in every box.
   Everything else (starting damage, which dials to change) the players set themselves.
3. **End of scenario** — a *Record results* button in the table's drawer opens a form built from the
   log entries: one field per party entry, one per character entry per seated character, and a
   reward picker over the Tale's reward cards. Saving writes a `tale_run_results` row and advances
   `step`. Recording a step again overwrites it.
4. The run's live game is keyed in `localStorage` by run and step, so it never collides with an
   ordinary game of the same scenario.

### Who a reward belongs to

A reward is recorded against a **character**, not a saved deck, because the log is about the
characters. At setup it joins the deck of whichever seat plays that character. If one character is
seated twice, it joins the first seat — *builder's call*, reported on the setup screen.

- **It is shuffled into the deck** — *Claude's call, for the designer to correct.* A reward that
  should start in play or in hand is moved there with the table's existing free moves. It sits
  outside the deck's 20 and the deck builder never counts it, because it is not part of a built deck.
- **A `rewards` entry with `party` scope is shown, not dealt** — *Claude's call.* A party reward has
  no one deck to join, so the setup screen lists it and the players put it where it goes.
- **The mode is picked when a playthrough starts and can be changed on the playthrough** — *builder's
  call*; the log records results, not which mode they were played in.

## What is left to the builder

- Routes, controller split, component names, migration names.
- The order and grouping of tabs and fields on the Tale page.
- Tests, following the existing pattern: round trip (a folder with no Tales exports unchanged; a Tale
  round-trips byte for byte), a Tale module hidden from the ordinary picker, deleting a Tale leaves
  scenarios and shared modules, the log sheet carries no `<link>` and no `@font-face`, a run records
  and reads back results, rewards join the right seat.
- `CLAUDE.md` rules for the new two-halves pairs (the Tale card face) and the third player-card owner.

## Questions for the designer (open, not answered here)

These are the game's, not the tool's; the tool builds the mechanism and stays out of the answers.

- Do a Tale's modules fill a scenario's module slots, or come on top of them? (The tool reports the
  count either way.)
- Can a Tale's scenario be played out of order, or skipped?
- Does a mode ever change something other than the between-scenario text — for example which Tale
  cards go in?
