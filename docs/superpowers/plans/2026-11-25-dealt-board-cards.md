# Board Cards Dealt to the Players — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A scenario's board card can be marked *dealt to the players*: at Start the playtest table shuffles those cards, deals one to each seat, and keeps the rest aside.

**Architecture:** One column, `board_cards.start` (`board` | `dealt`), carried through the editor, the design file (`"start": "dealt"` only when dealt), the presenter and the pocket page. On the table, a pure helper deals the cards; a seat gains `entities` (board-card instances, kept apart from its player-card zones) and the session gains `boardAside`.

**Tech Stack:** Laravel 11 / PHPUnit, Vue 3 + Inertia 3, SQLite.

**Spec:** `docs/superpowers/specs/2026-11-25-dealt-board-cards-design.md`

## Global Constraints

- `start` values are exactly `board` and `dealt`; default `board`; not null.
- Design file: `"start": "dealt"` is written **only** on a dealt card in `boardSetup`; Kraken, Legion and every module must export byte for byte.
- An unknown `start` in a design file imports as `board` with a warning naming the card — report, don't correct.
- A card with `added_by_beat_id` set is always `start = board` (the beat wins); a module's board card is always `board`.
- One card per seat, in lineup order. More seats than cards: deal what there is, log the seats that got none. Never invent or reuse a card.
- Every table move goes through `act()`. Seat entities never enter `seat.zones`.
- Run PHP tests with `--filter` only: the full suite commits to the real repo (memory note). The frontend must be built (`npm run build`) before feature tests.
- Do not commit unless the user asks; the working tree already holds uncommitted common-district work.

## Review Focus

1. **A saved game from before this change** loads with `boardAside: []` and every seat `entities: []`; nothing re-dealt, nothing thrown. (Task 5 browser check.)
2. **A seat added mid-game from the drawer** gets no dealt card automatically (no re-dealing, same rule as the dials); one can be given from the aside pile. (Task 5 browser check.)
3. **A dealt card deleted in the editor during a game** drops out of `boardAside` and every `seat.entities` on load, as `board` already does. (Task 4 load code, Task 5 browser check.)
4. **A dealt card with qty 2** puts two copies in the shuffle; two seats can hold the same card. (Task 4 node check.)
5. **Switching a dealt card to *Added at beat N*** saves `start = board` server side even if the form sent `dealt`. (Task 2 test.)

---

### Task 1: `start` column, presenter and design-file round trip

**Files:**
- Create: `database/migrations/2026_11_25_000002_add_start_to_board_cards.php`
- Modify: `app/Models/BoardCard.php` (fillable, constants, `isDealt()`)
- Modify: `app/Support/CardPresenter.php:~193` (board card array gains `'start' => $card->start`)
- Modify: `app/Console/Commands/ExportDesign.php:448` (`boardSetup` entry gains `'start' => $c->isDealt() ? 'dealt' : null` after `qty`; the existing `array_filter` drops the null)
- Modify: `app/Console/Commands/ImportDesign.php:~895` (read `start`)
- Test: `tests/Feature/DealtBoardCardTest.php` (new)

**Interfaces:**
- Produces: `BoardCard::START_BOARD = 'board'`, `BoardCard::START_DEALT = 'dealt'`, `BoardCard::STARTS = [self::START_BOARD, self::START_DEALT]`, `BoardCard::isDealt(): bool`; presented board card key `start` (string).

- [ ] **Step 1: Write failing tests** in `DealtBoardCardTest` (`RefreshDatabase`; temp `--path` pattern from `DesignExportTest`):
  - `test_a_board_card_starts_on_the_board_by_default`: after `design:import`, create a Kraken board card; `assertSame('board', $card->fresh()->start)`.
  - `test_export_writes_start_only_on_a_dealt_card`: import, set one Kraken setup card `start = dealt`, export; that entry has `'start' => 'dealt'`; no other `boardSetup` or `boardAddedByBeats` entry has a `start` key.
  - `test_kraken_and_legion_export_byte_for_byte`: import, export to the temp path; `assertFileEquals` against `design/data/kraken.json` and `design/data/march-of-dead-legion.json`. (If either differs before this change, record that as baseline and assert only that no `"start"` appears.)
  - `test_import_reads_dealt_and_warns_on_nonsense`: a temp design folder (through `cardforge.design_path`) whose scenario has one setup card `"start": "dealt"` and one `"start": "sideways"`; after import the first is `dealt`, the second `board`, and the command output names the second card and `sideways`.
  - `test_the_playtest_page_receives_start`: import, mark a Kraken card dealt; `GET /scenarios/kraken/play` → its entry in the `boardCards` prop has `start === 'dealt'`.
- [ ] **Step 2: Run** `php artisan test --filter=DealtBoardCardTest` — FAIL (no column).
- [ ] **Step 3: Implement.** Migration adds `$table->string('start')->default('board')->after('qty')`. Import: a card from `boardAddedByBeats` is `board`; a setup card takes its `start` when it is in `STARTS`, otherwise `board` plus `$this->warn("  {$scenario->name}: board card \"{$name}\" starts \"{$value}\", which is not a start; imported as on the board.")`. Module board card import is left writing no `start` (defaults to `board`).
- [ ] **Step 4: Run** the filter — PASS. Then `--filter=DesignExportTest` and `--filter=DesignImportTest`: no failures beyond the baseline from the last session.
- [ ] **Step 5:** `php artisan migrate` on the dev database.

### Task 2: The editor's *Starts* select

**Files:**
- Modify: `app/Http/Controllers/BoardCardController.php` (validate `start` as `['sometimes', Rule::in(BoardCard::STARTS)]`; force `board` when `added_by_beat_id` is set, and always in `storeForModule`)
- Modify: `resources/js/Components/BoardCardRow.vue:26,78,94-111`
- Modify: `resources/js/Pages/Scenarios/Show.vue:79` (`newBoardCard` gains `start: 'board'`)
- Test: `tests/Feature/DealtBoardCardTest.php`

**Interfaces:**
- Consumes: `BoardCard::STARTS`, presented `start` (Task 1).

- [ ] **Step 1: Write failing tests:**
  - `test_the_editor_saves_dealt`: `PUT /board-cards/{id}` with the card's fields plus `start: 'dealt'` → `start === 'dealt'`.
  - `test_a_beat_card_is_never_dealt`: `PUT` with `start: 'dealt'` and a Kraken beat id → `start === 'board'`, `added_by_beat_id` kept.
  - `test_a_module_board_card_is_never_dealt`: `POST /modules/{slug}/board-cards` with `start: 'dealt'` → `board`.
  - `test_nonsense_start_is_rejected`: `PUT` with `start: 'sideways'` → `assertSessionHasErrors('start')`.
- [ ] **Step 2: Run** — FAIL.
- [ ] **Step 3: Implement.** In `BoardCardRow.vue` the *Added by beat* select becomes one **Starts** select over a computed get/set of (`form.start`, `form.added_by_beat_id`): `On the board` → (`board`, null); `Dealt to the players` → (`dealt`, null); `Added at beat {order}. {name}` → (`board`, beat id). Shown whenever the row is a scenario's (it has `beats` or is not a module card); a module row shows no select, as today. The summary line beside *added at beat N* (line 78) gains `<span v-if="card.start === 'dealt'" class="text-xs text-amber-800">dealt to the players</span>`.
- [ ] **Step 4: Run** — PASS. `npm run build`; on `/scenarios/meddler-in-the-dark` set a townsfolk to *Dealt to the players*, save, reload: it still says so. Set it back to *On the board* afterwards — marking the real cards is the user's call (Task 6).

### Task 3: The pocket page names dealt cards

**Files:**
- Modify: `app/Support/Pocket.php:120`
- Test: `tests/Feature/PocketTest.php`

- [ ] **Step 1: Failing test** `test_dealt_board_cards_are_their_own_group`: import, mark one Kraken setup card dealt, render the pocket page; `assertTrue(str_contains($html, 'Board cards — dealt to the players'), 'no dealt group')` and `strpos` of the card's name is after that heading. Never `assertStringContainsString` on this page — a failure hangs the runner.
- [ ] **Step 2: Run** `--filter=PocketTest` — FAIL.
- [ ] **Step 3: Implement:** `['Board cards', 'board', $scenario->boardCards->reject->isDealt()->values()]` and `['Board cards — dealt to the players', 'board', $scenario->boardCards->filter->isDealt()->values()]`. Confirm `section()` already prints nothing for an empty group.
- [ ] **Step 4: Run** — PASS.

### Task 4: Dealing at Start, and saves that know about it

**Files:**
- Create: `resources/js/boardDeal.js`
- Modify: `resources/js/goldfish.js:160` (`normaliseSeat`: `seat.entities ??= []`)
- Modify: `resources/js/Pages/Scenarios/Play.vue` — `boardFor`/`freshSession` (`:230-255`), `freshTable` (`:262-286`, `boardAside: []`), `loadSession` (`:312-350`), `runStep` draw (`:735-740`), `table` (`:873-881`)

**Interfaces:**
- Produces: `dealToSeats(cards: Array<{id: number, qty: number}>, seatCount: number, shuffleFn = shuffle) => { seats: number[][], aside: number[] }` — `seats[i]` holds zero or one id; each copy of `qty` is one entry in the shuffle.
- Seat entity instance: `{ uid, id, exhausted: false, counter: 0 }`, uids from `session.boardUid`. `session.boardAside: Array<{ uid, id, counter }>`.
- On `table` (for Task 5): `takeAside(uid) => instance | null` (removes it), `setAside(instance)` (pushes `{ uid, id, counter }`), `nextBoardUid() => number`. Callers wrap them in `act()`.

- [ ] **Step 1: Write a node check** `<scratchpad>/boardDeal.check.mjs`, importing `boardDeal.js` by absolute `file://` URL, `shuffleFn = (l) => [...l]`, asserting with `node:assert/strict`:
  - ids 1–4 qty 1, 2 seats → `seats` `[[1],[2]]`, `aside` `[3,4]`.
  - ids 1–4, 5 seats → `[[1],[2],[3],[4],[]]`, `aside` `[]`.
  - id 7 qty 2, 2 seats → `[[7],[7]]`, `aside` `[]`.
  - no cards, 3 seats → `[[],[],[]]`, `aside` `[]`.
- [ ] **Step 2: Run** `node <scratchpad>/boardDeal.check.mjs` — FAIL (no module).
- [ ] **Step 3: Implement** `boardDeal.js` (leaf, no Vue, `shuffle` from `./goldfish`). In `Play.vue`:
  - `boardFor(beatId)` also excludes `start === 'dealt'`; `dealtCards` (computed) = the scenario's own setup cards (`!module_id`, no beat) with `start === 'dealt'`.
  - `freshSession(party)`: after `freshTable`, deal; push each seat's instance into `fresh.party[i].entities`, the rest into `fresh.boardAside`. When there were dealt cards and some seats got none, add one log line: `no dealt card left for <names joined by ", ">`.
  - `loadSession`: `boardAside: (saved.boardAside ?? []).filter((i) => knownBoard.has(i.id))`; each seat's `entities` filtered the same way (default `[]`).
  - `runStep` *draw*: `seat.entities.forEach((c) => (c.exhausted = false))` beside the in-play ready.
  - `table` gains `takeAside`, `setAside`, `nextBoardUid`.
- [ ] **Step 4: Run** the node check — PASS.

### Task 5: The seat's entity row and the aside pile

**Files:**
- Modify: `resources/js/Components/Play/useSeatActions.js`
- Modify: `resources/js/Components/Play/SeatDock.vue:~425` (entity row at the head of the in-play row)
- Modify: `resources/js/Components/Play/EntityRail.vue` (aside `PileStack`, emits `show-aside`)
- Modify: `resources/js/Pages/Scenarios/Play.vue` (pass `boardCards` to `SeatDock`; a `PileViewer` of `session.boardAside` on `show-aside`, `kind="board"`)

**Interfaces:**
- Consumes: Task 4's `table.takeAside`, `table.setAside`, `table.placeBoard`, `table.nextBoardUid`; `seat.entities`.
- Produces in `useSeatActions`: `entityCardOf(instance)`, `toggleEntityExhausted(instance)`, `entityCounter(instance, delta)`, `entityToAside(instance)`, `entityToBoard(instance)`; in `Play.vue`, `giveFromAside(uid, seatIndex)` and `asideToBoard(uid)`. Each is one `act()` whose log line names the card and, where there is one, the seat.

- [ ] **Step 1: The seat row.** In `SeatDock.vue`, before the `seat.zones.play` loop, `v-for` over `seat.entities`: `TableCard` with `kind="board"`, `:card="a.entityCardOf(instance)"`, `:width="playWidth"`, `:exhausted`, `:activate="() => a.toggleEntityExhausted(instance)"`, a counter badge in `#overlay` when `counter > 0` (the badge `BoardTable.vue` uses), and a menu: *Exhaust* / *Ready* (click), *Counter +1*, *Counter −1* (disabled at 0), *View full size*, divider, *Set aside*, *Put on the entity board*. No `drag`. `In play · n` still counts player cards only. The row sits inside the in-play row's existing flex share, so sizing is unchanged.
- [ ] **Step 2: The aside pile.** `EntityRail` shows a face-down `PileStack` labelled *Set aside* with `session.boardAside.length`, only when that is non-zero or the scenario has dealt cards; click emits `show-aside`. The viewer's `menuFor(instance)`: one *Give to <seat name>* per seat, and *Put on the entity board* (`placeBoard(id)`, then copy `counter` onto the pushed instance).
- [ ] **Step 3: Check in the browser** (`npm run build`, or `composer dev`; `touch` the file if Vite serves stale JS). Temporarily mark the four Meddler townsfolk dealt, then on `/scenarios/meddler-in-the-dark/play` with 2 seats:
  - Start: board shows Astraphath only; each seat has one townsfolk at the head of its in-play row; rail shows *Set aside* 2.
  - Click exhausts; Space through *Ready and draw* readies it; *Counter +1* shows a badge; undo reverses each.
  - *Set aside* → count 3; open the pile, *Give to* the other seat → it holds two.
  - *Put on the entity board* from a seat and from the pile → on the board, damage 0.
  - 5 seats: the fifth has none and the log reads *no dealt card left for <name>*.
  - Review Focus 1: the Kraken's existing saved game loads, no aside pile shown.
  - Review Focus 2: a seat added from the drawer mid-game gets nothing; give it one from the pile.
  - Review Focus 3: delete a dealt card in the editor (use a throwaway one), reload: gone from seats and pile.
  - Put the townsfolk back to how they were.
- [ ] **Step 4: Run** `php artisan test --filter=PlaytestTableTest` — no failures beyond the baseline.

### Task 6: Write it down and hand over

**Files:**
- Modify: `CLAUDE.md` (*Things that will bite* entry after the playtest table's board rules; *Where things live* row for `resources/js/boardDeal.js`)

- [ ] **Step 1:** The entry: a board card's `start` is `board` or `dealt`; a beat card and a module card are always `board`; the file writes `"start": "dealt"` only when dealt; the table deals one per seat in lineup order and keeps the rest in `session.boardAside`; dealt cards in a seat live in `seat.entities`, never in `zones`, so no player-card move can reach them; a seat added mid-game is dealt nothing. Link the spec.
- [ ] **Step 2:** Tell the user: marking the Meddler's four townsfolk dealt is theirs in the editor (or they say the word), and an **export**, not an import, writes them into `design/data/meddler-in-the-dark.json` — after diffing the database against the design folder.
