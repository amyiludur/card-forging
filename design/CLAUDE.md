# Brief for Claude Code

## Status
The app has been built from version 1 of this folder, and updated for version 2 (`CHANGES-v2.md`). **The current work is the player side (version 3): read `players/README.md` first.** Ask the designer before making design decisions there, as with everything else.

## Project
This folder contains the design for a cooperative card game (see `README.md`). The goal is a **platform** that lets the designer:

1. **Create and edit cards** (entity deck cards, board cards, story beats, and later player and character cards).
2. **Create and edit the rules** (the rulebook text and the tunable numbers).
3. **Print the cards** (and ideally the rules) to a file that can be printed.

These three goals come from the designer. Everything below that is marked *(proposal)* is a suggestion to confirm, not a requirement.

## Source of truth
- Read `rules/` first, in order, then `scenarios/kraken.md`.
- `rules/05-decisions-and-open-questions.md` separates decided rules from placeholders and open questions. Do not treat placeholders as final, and do not silently resolve open questions. Ask the designer.
- `data/kraken.json` is a first pass at the card data shape. It is a starting point and can change if the platform needs it to.

## Stack
Not decided. The designer already has a scaffold called **CardForge** (Laravel 11, Vue 3, Inertia.js, Tailwind CSS, Docker, with card export and automated backups). **Ask whether this project should be built inside CardForge or start fresh** before choosing anything.

## Data model *(proposal)*
- **RulesConfig:** the tunable numbers in `data/rules-config.json`, editable in the UI. Rules text should reference these by name so changing a number updates the rulebook.
- **CardType:** shared across scenarios (Attack, Hazard, Summon, Curse, Story).
- **Scenario:** name, entity type (creature or concept), overview, starting Dread, Dread effect, traits, town actions, win and lose text, and module rules (how many modules it requires and which are recommended).
- **Module:** a themed set of entity cards and board cards with a set icon, added to a scenario's deck at setup. See `rules/06-modules.md` and `data/modules/`.
- **EntityCard:** belongs to a scenario or a module. Fields: name, quantity, layout (`single`, `split`, `x-cost`), omen cost (integer or `X`), traits, **arrow (`top` or `bottom`, required on every card)**, faces, and `addedByBeat` (null if in the base deck). A single card has one face. A split card has two faces (top and bottom), each with its own type and effect text.
- **BoardCard:** health (optional), traits, text, setup quantity, and which beat adds it (if any).
- **StoryBeat:** order, name, flavour text, on-reach effect, advance trigger, on-advance effect, Dread change.
- **Rules documents:** the markdown files in `rules/`, editable in the UI with version history.
- Later: **Character** and **PlayerCard** (not designed yet, so leave room for them).

Card and rules text will need simple markup for icons (omen, gold, damage) and for references to config values. Keep the format simple and plain text.

## Printing *(proposal, confirm details with the designer)*
- Print-ready PDF export for a deck, a scenario, or the whole game.
- Default card size of standard poker cards (about 63.5 x 88.9 mm), configurable.
- A4 and Letter sheets with several cards per page, crop marks and optional bleed.
- **Split cards** need a layout with two halves. Every entity card has an arrow on its right edge, at the top or bottom, that points at the half of the next card to use.
- Module cards print with their module's set icon.
- Preview in the browser before export, and a card back.
- Story beats and board cards are printed as separate decks from the entity deck.

## Resolved question
The earlier question about how Redirect works on a printed arrow is resolved: every entity card carries an arrow on its right edge (see `CHANGES-v2.md`). New open questions are in `rules/05-decisions-and-open-questions.md`. Ask the designer rather than deciding them.

## How to work
- Ask the designer before making design decisions. This is their game, and you are building the tool.
- The designer is actively changing the rules, so rules text, numbers and card data should be easy to edit, not hardcoded.
- Keep placeholders visibly flagged in the UI.
- The current work and the suggested order are in `CHANGES-v2.md`.
