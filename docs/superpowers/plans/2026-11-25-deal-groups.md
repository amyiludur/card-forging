# Deal Groups — Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A dealt board card carries a group name; at Start each group is dealt separately, one card per player per group.

**Architecture:** One nullable column, `board_cards.deal_group`, beside `start`. The server clears it whenever a card is not dealt. The table groups the dealt cards by name and deals each group with the existing `dealToSeats`.

**Tech Stack:** Laravel 11 / PHPUnit, Vue 3 + Inertia 3.

**Spec:** `docs/superpowers/specs/2026-11-25-dealt-board-cards-design.md` — the *Addendum: deal groups* section.

## Global Constraints

- `"dealGroup"` is written only when `start = dealt` and the group is non-empty; Kraken and Legion export with no `dealGroup` key.
- `deal_group` is null whenever `start` is not `dealt` (controller and import both enforce this). An empty or whitespace-only name is stored as null.
- A dealt card with no group is never renamed: it deals as its own unnamed group and is flagged *no group*.
- Run PHP tests with `--filter` only. Build the frontend before feature tests. No commits unless asked.

## Review Focus

1. **Two names that differ only in case or spacing** ("Townsfolk" / "townsfolk ") — they deal as two groups, as typed; surrounding spaces are trimmed, nothing else. The datalist is what prevents this. (Task 1 import test trims.)
2. **The four Meddler townsfolk already in the dev DB as dealt with no group** — they keep dealing as one unnamed group, exactly as before. (Task 4 browser check.)
3. **A saved game from before groups existed** — loads unchanged; seat entities and the aside pile hold ids, and the group is read off the card. (Task 4 browser check.)
4. **A card switched from dealt back to On the board** — its group is cleared, not left as a stale value that reappears later. (Task 1 test.)
5. **One group with more cards than players, another with fewer** — each is reported on its own. (Task 4 node check.)

---

### Task 1: `deal_group` column, round trip and controller

**Files:**
- Create: `database/migrations/2026_11_25_000003_add_deal_group_to_board_cards.php`
- Modify: `app/Models/BoardCard.php` (fillable `deal_group`)
- Modify: `app/Support/CardPresenter.php` (`'deal_group' => $card->deal_group` beside `start`)
- Modify: `app/Console/Commands/ExportDesign.php` (`'dealGroup' => $c->isDealt() ? ($c->deal_group ?: null) : null` right after `start`)
- Modify: `app/Console/Commands/ImportDesign.php` (scenario board loop: `deal_group` = trimmed `dealGroup` when `$start` is dealt, else null; module loop: `'deal_group' => null`)
- Modify: `app/Http/Controllers/BoardCardController.php` (validate `'deal_group' => ['nullable', 'string', 'max:60']`; trim; null unless `start === dealt`, after the existing forcing)
- Test: `tests/Feature/DealtBoardCardTest.php`

- [ ] **Step 1: Failing tests:**
  - `test_export_writes_the_deal_group_beside_start`: a Kraken setup card dealt with `deal_group = 'Townsfolk'`; export; its entry has `start => dealt`, `dealGroup => Townsfolk`. `test_an_undealt_folder_grows_no_start_key` also asserts no `"dealGroup"`.
  - `test_import_reads_the_deal_group`: temp design copy; `boardSetup[0]` gets `"start": "dealt", "dealGroup": "  Townsfolk "`, `boardSetup[1]` gets `"dealGroup": "Curses"` and no start → first `deal_group === 'Townsfolk'`, second `null`.
  - `test_the_editor_saves_a_deal_group`: PUT `start: dealt, deal_group: 'Townsfolk'` → stored.
  - `test_a_card_put_back_on_the_board_loses_its_group`: card dealt with a group; PUT `start: board, deal_group: 'Townsfolk'` → null.
  - `test_the_playtest_page_receives_the_deal_group`: the `boardCards` prop carries `deal_group`.
- [ ] **Step 2: Run** `php artisan test --filter=DealtBoardCardTest` — new ones FAIL.
- [ ] **Step 3: Implement**; migration `$table->string('deal_group')->nullable()->after('start')`.
- [ ] **Step 4: Run** — PASS. `php artisan migrate` on the dev DB.

### Task 2: The editor's Group field

**Files:**
- Modify: `resources/js/Components/BoardCardRow.vue` (form `deal_group`; a Group input shown when the card starts dealt, with a `datalist` of `groups`; summary *dealt to the players · Townsfolk*, or *· no group* in red)
- Modify: `resources/js/Pages/Scenarios/Show.vue` (pass `:groups` — distinct non-empty `deal_group`s of this scenario's board cards — to each `BoardCardRow`; `newBoardCard` gains `deal_group: null`)

**Interfaces:**
- Produces: `BoardCardRow` prop `groups: string[]` (default `[]`).

- [ ] **Step 1:** Implement. The `startsAs` setter clears `form.deal_group` when anything but `dealt` is picked.
- [ ] **Step 2:** `npm run build`; on the Meddler's Entity board tab, give Valter the group *Townsfolk*, save, reload: the summary reads *dealt to the players · Townsfolk*, and Isabella's Group field offers *Townsfolk*. Then clear Valter's group again — naming groups is the designer's (Task 5).

### Task 3: The pocket page heads each group

**Files:**
- Modify: `app/Support/Pocket.php:120`
- Test: `tests/Feature/PocketTest.php`

- [ ] **Step 1: Failing test** `test_each_deal_group_has_its_own_heading`: two Kraken setup cards dealt, groups `Townsfolk` and `Curses`; the page has `Board cards — Townsfolk, dealt to the players` and `Board cards — Curses, dealt to the players`, each followed by its card's name (`strpos` from the heading). `test_dealt_board_cards_are_their_own_group` (no group) still passes unchanged.
- [ ] **Step 2: Run** `--filter=PocketTest` — FAIL.
- [ ] **Step 3: Implement:** dealt cards `groupBy(fn ($c) => $c->deal_group ?? '')` in first-appearance order, one entry each, titled `Board cards — {name}, dealt to the players`, or `Board cards — dealt to the players` for `''`.
- [ ] **Step 4: Run** — PASS.

### Task 4: Dealing by group on the table

**Files:**
- Modify: `resources/js/boardDeal.js` (add `dealByGroup`)
- Modify: `resources/js/Pages/Scenarios/Play.vue` (`freshSession` deal, `startGame` log lines, aside viewer captions)
- Modify: `resources/js/Components/Play/PileViewer.vue` (optional `captionFor` prop: a line under each card)

**Interfaces:**
- Produces: `dealByGroup(cards: Array<{id, qty, deal_group}>, seatCount, shuffleFn = shuffle) => Array<{ name: string, seats: number[][], aside: number[] }>` — one entry per group in first-appearance order, `name` = `deal_group ?? ''`, each dealt by `dealToSeats`.

- [ ] **Step 1: Extend the node check** (`<scratchpad>/boardDeal.check.mjs`, identity shuffle):
  - Townsfolk ids 1–4 + Curses ids 5–6, 3 seats → `[{ name: 'Townsfolk', seats: [[1],[2],[3]], aside: [4] }, { name: 'Curses', seats: [[5],[6],[]], aside: [] }]`.
  - Cards with no `deal_group` → one entry named `''`.
- [ ] **Step 2: Run** — FAIL (no export).
- [ ] **Step 3: Implement** `dealByGroup`. `freshSession` deals by group, pushing each seat's ids into its `entities` and every group's leftovers onto `boardAside`. `startGame` logs per group: `Dealt {name || 'dealt cards'}: {card} to {seat}, …; N kept aside.` and `No {name || 'dealt card'} left for {seats}.` The aside `PileViewer` gets `:caption-for="(item) => boardCardOf(item).deal_group || 'no group'"`.
- [ ] **Step 4: Run** the node check — PASS. `npm run build`.
- [ ] **Step 5: Browser check** on the Meddler play page (temporarily: Valter + Isabella *Townsfolk*, Crystina + Rayner *Curses*; 2 seats): each seat gets one of each, aside empty, a log line per group. 3 seats: the third gets neither and both lines name them; the viewer captions read *Townsfolk* / *Curses*. Review Focus 2 and 3: clear the groups — one unnamed group deals as before, and a save from before loads. Then restore every group to empty.
- [ ] **Step 6: Run** `--filter='DealtBoardCardTest|PocketTest|PlaytestTableTest'` — PASS.

### Task 5: Write it down and hand over

- [ ] **Step 1:** Extend the CLAUDE.md dealt-card entry: `deal_group`, null unless dealt; `"dealGroup"` only beside `"start": "dealt"`; each group dealt separately by `dealByGroup`; no group is its own unnamed group and is flagged; one set-aside pile, captioned by group.
- [ ] **Step 2:** Tell the user the four townsfolk are dealt with no group yet: naming them *Townsfolk* is theirs in the editor, or on their word; then export.
