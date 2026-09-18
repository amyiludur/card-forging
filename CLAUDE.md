# Card Forge — notes for Claude Code

This repository is the platform. `design/` is the game, and `design/CLAUDE.md` is the designer's
original brief — read it and the `design/rules/` files before changing anything about the game.

## The one rule

**This is the designer's game. You are building the tool.** Do not resolve an open question from
`design/rules/05-decisions-and-open-questions.md` on your own, and do not quietly turn a
*(placeholder)* number into a real one. Ask.

## Stack

Laravel 11, Vue 3, Inertia, Tailwind, SQLite in development. No auth: it is a single-designer local
tool. Chosen to match the designer's existing **CardForge** scaffold so the two can merge later.

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
| `resources/views/print/` | the print sheet, inline CSS so it renders from `file://` for the PDF |
| `resources/js/Components/CardPreview.vue` | the on-screen card — mirrors the print partial |

## Things that will bite

- **The browser preview and the print sheet are two implementations of one card design.** Change
  one and change the other, or what the designer sees stops being what they get.
- **`design:export` writes the design folder verbatim.** Match the existing key names (split faces
  use `position`, not `half`) and the two-space indentation, or every export becomes a huge diff.
- **Markdown bodies are exempt from `TrimStrings`** in `bootstrap/app.php`. Without that, every
  save strips the trailing newline and creates a spurious version.
- **Placeholders are load-bearing.** `is_placeholder` on cards and configs drives the flags in the
  UI. Keep new data defaulting to placeholder, not to final.
- **Board card health is free text** so it can hold "12 per player". The exporter turns it back
  into a number only when it is one.

## Not built yet

Characters, player cards, the shop and Response cards are not designed, so they have no tables.
Printing the rulebook to PDF is not built either — only the cards are.
