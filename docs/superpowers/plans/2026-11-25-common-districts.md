# Common districts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A district written once, in a shared library, that each scenario can pick and add a line of its own to.

**Architecture:** A common district is a `town_actions` row with no scenario and no module. A
scenario's picks are `scenario_town_picks` rows (district, extra line, sort). `Scenario::town()`
merges own districts and picks by `sort`; a picked district is a clone of the common row carrying
the picking scenario and the pick as relations, so every reader that took `townActions` takes
`town()` instead and needs nothing else.

**Tech Stack:** Laravel 11, Vue 3, Inertia 3, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-25-common-districts-design.md`

## Global Constraints

- Report, don't correct: a pick naming a missing district is skipped with a warning, never guessed.
- A design folder with no common districts and no picks exports byte for byte.
- Run tests with `--filter`; the full suite touches the real git repo.
- The extra line prints after the district's own note. It is folded into the presented `note`
  by `CardPresenter::townAction()`, so both card faces (which already render `note`) carry it
  with no change of their own.

## Review Focus

- A scenario picking a district then the district being deleted: the pick cascades, the town still renders.
- Re-importing twice: no duplicate picks and no stale own districts (`test_import_is_idempotent_with_picks`).
- A scenario file whose town was converted from own districts to picks: the old own rows are pruned on import.
- The same district picked by two scenarios in one print pool: each prints with its own extra (`pick:<id>` pool key).
- `{dreadRule}` on a common district: the picking scenario's rule in its town, `?dreadRule` on `/town`.

---

### Task 1: Data and `Scenario::town()`

**Files:** migration `2026_11_25_000001_create_scenario_town_picks.php`; create `app/Models/ScenarioTownPick.php`; modify `app/Models/Scenario.php`, `app/Models/TownAction.php`, `app/Support/CardPresenter.php`. Test: `tests/Feature/CommonDistrictTest.php`.

**Produces:** `TownAction::scopeCommon()`, `TownAction::isCommon(): bool`, `TownAction::picks(): HasMany`;
`Scenario::townPicks(): HasMany`, `Scenario::town(): Collection<TownAction>`;
`ScenarioTownPick` (`scenario_id`, `town_action_id`, `extra`, `sort`), `->district(): TownAction`;
presenter adds `own_note`, `extra`, `pick_id`, `is_common` and folds `extra` into `note`/`note_html`/`reminders`.

- [ ] Tests: merged order by sort; picked district resolves `{dreadRule}` against the picker; extra appended to `note`; deleting the district drops the pick.
- [ ] Implement; run `php artisan test --filter=CommonDistrictTest`.

### Task 2: Editor

**Files:** `TownActionController` (`storeCommon`, `index`), new `TownPickController` (store/update/destroy), `routes/web.php`, `ScenarioController::show` (`town`, `commonDistricts`), `Pages/Town/Index.vue`, `Components/TownPickRow.vue`, `Pages/Scenarios/Show.vue`, `Layouts/AppLayout.vue`.

Routes: `GET town`, `POST town`, `POST scenarios/{scenario}/town-picks`, `PUT town-picks/{pick}`, `DELETE town-picks/{pick}`.

- [ ] Tests: create a common district; pick it (sort = after the scenario's highest); a second pick of the same district is refused; update and remove the extra line; `/town` lists who picks each.
- [ ] Implement, `npm run build`, run the tests.

### Task 3: Design folder

**Files:** `ImportDesign` (`importCommonTown` before scenarios; picks and a prune of own districts in `importScenario`), `ExportDesign` (`town.json`, picks in the scenario's list).

- [ ] Tests: round trip with `town.json` and a pick with and without `extra`; no file grown when there are none; a missing pick warned and skipped; import twice gives the same rows.
- [ ] Implement, run the tests and `DesignExportTest`, `DesignImportTest`.

### Task 4: Print, pocket, table

**Files:** `PrintController::scenarioItems`, `PrintCatalogue` (`pick` group; common source "Common district"), `Pocket` (scenario town = `town()`, a *Common districts* section), `DeckController::play`.

- [ ] Tests: scenario print run includes the pick with its extra; a pool holds `pick:<id>`; pocket has the section; the table's `town` carries the pick.
- [ ] Implement, run the tests plus `PrintTest`, `PrintPoolTest`, `PocketTest`, `PlaytestTableTest`, `ModuleTownTest`.

### Task 5: Convert the Smithy and Chapel, and CLAUDE.md

- [ ] Move Smithy and Chapel into `design/data/town.json`, picked by both scenarios with each one's difference as its `extra` (no rule added that is not in the files today). Import, export, diff is only the move.
- [ ] CLAUDE.md "Things that will bite" entry and the "Where things live" rows.
