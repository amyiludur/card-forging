# Module Town Districts Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** A module can own town districts (the Revolt of the Peasants' Farm), which join the town when the module is chosen.

**Architecture:** `town_actions` gets a nullable `module_id` beside a now-nullable `scenario_id`, the pattern `entity_cards` and `board_cards` already use. Every reader of districts (module page, print, pocket, playtest table, design folder) learns the module owner.

**Tech Stack:** Laravel 11, Inertia 3, Vue 3, SQLite.

**Spec:** `docs/superpowers/specs/2026-11-16-module-town-districts-design.md`

## Global Constraints

- A district belongs to a scenario or a module, never both.
- A module file writes `"town"` **only when the module has a district** — existing module files export byte for byte.
- The print partial and `CardPreview.vue` stay two halves of one rule.
- Run tests with `--filter` (the full suite touches real git).

## Review Focus

- A module district has no scenario: its markup must render `?dreadRule` rather than crash (`dreadOf(null)`).
- Deleting a scenario must not delete module districts; deleting a module must.
- The table must not show a module's district when the module is not chosen.
- Re-importing must not duplicate a module district (upsert by `module_id` + `name`).
- `PrintCatalogue::describe('town')` for a module district names the module, not "No scenario".

---

### Task 1: Data and module page

**Files:** migration `database/migrations/2026_11_16_000001_add_module_to_town_actions.php`; `app/Models/TownAction.php`, `app/Models/Module.php`, `app/Http/Controllers/TownActionController.php`, `routes/web.php`, `app/Http/Controllers/ModuleController.php`, `resources/js/Pages/Modules/Show.vue`, `resources/js/Components/TownActionRow.vue` (comment only); test `tests/Feature/ModuleTownTest.php`.

**Produces:** `Module::townActions(): HasMany` (ordered by sort); `TownAction::module(): BelongsTo`; route `POST modules/{module}/town-actions` → `TownActionController@storeForModule`, name `modules.town-actions.store`; `Modules/Show` prop `townActions` (presenter shape).

- [ ] Test `test_a_module_can_add_a_district`: post `{name: 'Farm', effect: 'Gain 2 gold', gold_cost: 1, omen: 1}` to `/modules/what-lurks-below/town-actions` → district has `module_id` set, `scenario_id` null; `/modules/what-lurks-below` has `townActions` 1 with `name` Farm and `set_icon` WLB.
- [ ] Test `test_deleting_a_module_takes_its_districts_and_a_scenario_does_not`.
- [ ] Implement; run `php artisan test --filter=ModuleTownTest`; commit.

### Task 2: Card face, print, pocket

**Files:** `app/Support/CardPresenter.php` (`townAction()` adds `set_icon` => `$action->module?->set_icon`), `resources/views/print/partials/card.blade.php` and `resources/js/Components/CardPreview.vue` (town foot with set icon), `app/Http/Controllers/PrintController.php` (`moduleItems` + `town` deck option), `app/Support/PrintCatalogue.php` (`describe` + `query` with module), `app/Support/Pocket.php` (module section's Town cards).

- [ ] Test `test_a_module_prints_its_districts`: `/print/module/{slug}/sheet?deck=town` contains `Farm` and the set icon.
- [ ] Implement; run filter; commit.

### Task 3: Playtest table and design folder

**Files:** `app/Http/Controllers/DeckController.php` (`town` = scenario's then chosen modules'), `app/Support/DeckAssembly.php` (eager-load `townActions`), `app/Console/Commands/ExportDesign.php`, `app/Console/Commands/ImportDesign.php`.

- [ ] Test `test_the_table_gets_a_module_district_only_when_chosen`.
- [ ] Test `test_a_module_district_round_trips_through_the_design_folder` (export writes `town`, re-import keeps one Farm) and `test_a_module_without_districts_writes_no_town_key`.
- [ ] Implement; run filter plus `ModuleTest`, `PlaytestTableTest`; commit.
