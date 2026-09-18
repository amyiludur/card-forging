# Card Forge — notes for Claude Code

This repository is the platform. `design/` is the game, and `design/CLAUDE.md` is the designer's
original brief — read it and the `design/rules/` files before changing anything about the game.

## The one rule

**This is the designer's game. You are building the tool.** Do not resolve an open question from
`design/rules/05-decisions-and-open-questions.md` on your own, and do not quietly turn a
*(placeholder)* number into a real one. Ask.

## Stack

Laravel 11, Vue 3, Inertia 3, Tailwind, SQLite in development. No auth: it is a single-designer
local tool. Chosen to match the designer's existing **CardForge** scaffold so the two can merge
later. The designer runs PHP 8.5, so the suite is run on 8.2 and 8.5 before anything ships.

## Running it

`./setup` then `composer dev`. Both are tested. There is also a `Dockerfile` and
`docker-compose.yml`, which have **never been built** — they were written in a sandbox with no
access to Debian's package repositories. Treat them as unverified until someone runs
`docker compose up` for real.

## Where things live

| Path | What |
|---|---|
| `design/` | the rules markdown and scenario JSON — the source of truth, tracked by git |
| `app/Console/Commands/ImportDesign.php` | `design:import`, design/ → database |
| `app/Console/Commands/ExportDesign.php` | `design:export`, database → design/ |
| `app/Support/Markup.php` | the `{omen}` / `{config:key}` markup, server side |
| `resources/js/markup.js` | the same markup in the browser — **keep these two in step** |
| `app/Support/CardPresenter.php` | the one card shape used by the editor, preview and print |
| `app/Support/PrintOptions.php` | sheet and card geometry, all in millimetres |
| `app/Support/Storyline.php` | the arrow rule: which half of each split card resolves |
| `app/Support/DeckAssembly.php` | a scenario's base deck plus the modules chosen for a play |
| `resources/views/print/` | the print sheet, inline CSS so it renders from `file://` for the PDF |
| `resources/js/Components/CardPreview.vue` | the on-screen card — mirrors the print partial |

## Things that will bite

- **The browser preview and the print sheet are two implementations of one card design.** Change
  one and change the other, or what the designer sees stops being what they get.
- **The arrow is on the right edge and points at the NEXT card.** Every entity card has one, whatever
  its layout, and it decides which half of the split card *after* it resolves — never its own halves.
  That was the v1 rule and it is gone. `Storyline::resolve()` is the only implementation; the
  storyline preview renders server-side on purpose so there is no second copy to drift.
- **A card belongs to a scenario or to a module, never both.** `scenario_id` and `module_id` are both
  nullable and exactly one is set. Anything counting cards has to say which it means: deleting a
  scenario must not take module cards with it.
- **`design:export` writes the design folder verbatim.** Match the existing key names (split faces
  use `position`, not `half`) and the two-space indentation, or every export becomes a huge diff.
- **Markdown bodies are exempt from `TrimStrings`** in `bootstrap/app.php`. Without that, every
  save strips the trailing newline and creates a spurious version.
- **Placeholders are load-bearing.** `is_placeholder` on cards and configs drives the flags in the
  UI. Keep new data defaulting to placeholder, not to final.
- **Board card health is free text** so it can hold "12 per player". The exporter turns it back
  into a number only when it is one.
- **Inertia's server and client versions must match.** The v3 client reads the initial page from
  `<script data-page="app" type="application/json">`; v1 wrote it to `<div id="app" data-page>`.
  Upgrading one side alone gives a blank page and a null-deref in the console, not an error.
- **Blade caches the compiled root view.** After anything that changes the `@inertia` directive's
  output, `php artisan view:clear`, or the old markup keeps being served.
- **Page components live in `resources/js/Pages`, capital P.** Inertia 3 defaults to lowercase
  `pages`; `config/inertia.php` points at ours. Renaming the directory would be a case-only rename
  that macOS and Windows checkouts handle badly, so don't.
- **Don't let a dependency cap the PHP version.** Check `composer.lock` for `~8.x.0`-style
  constraints before committing a lock change; one of those is what broke the first install.

## Open questions this code deliberately does not answer

`design/rules/05-decisions-and-open-questions.md` has 21 of them. Three are wired to config rather
than settled in code, and must stay that way until the designer decides:

- **Which arrow a split card uses** (question 1) and **what the first card in a row uses**
  (question 2) come from `defaultArrow` and `firstCardArrowSource`.
- **Redirect's form** (question 3) — only "flip" is modelled, and the storyline preview says so.
- **Arrow balance** (question 6) — the scenario and deck pages report the top/bottom mix and pass no
  judgement on it.

## Not built yet

Characters, player cards, the shop and Response cards are not designed, so they have no tables.
Printing the rulebook to PDF is not built either — only the cards are.
