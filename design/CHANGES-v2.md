# Changes in v2 (for the existing app)

The app has already been built from v1 of this folder. This file lists what changed in the design and what needs to change in the app. The files in `rules/`, `scenarios/` and `data/` are already updated to match.

## 1. Arrows now live on every entity card

**Old:** only split cards had an arrow, printed on the card, pointing at its own top or bottom half. `arrow` was `null` in the data.

**New:** **every** entity card (single, split and X-cost) has an arrow on its **right edge**, at the top or bottom. When cards are laid out in a row (the storyline), a card's arrow points at the top or bottom half of the card to its right. A split card resolves the half indicated by the arrow of the card immediately before it. See `rules/01-core-rules.md` and `rules/02-turn-structure.md`.

### Data changes
- `EntityCard.arrow` is **required** for every layout and is `"top"` or `"bottom"`.
- A split card's own arrow no longer says which of its own halves is active. It only tells the next card.
- `data/kraken.json` already has an arrow for every card (all placeholders, roughly 12 top and 12 bottom in the base deck).
- Migrate existing cards: import the arrows from `data/kraken.json` by card name. For any card created in the app that has no arrow, default to `"top"` and flag it for the designer to choose.
- New keys in `data/rules-config.json`: `defaultArrow`, `firstCardArrowSource`, `cancelledCardsKeepArrow`, `moduleCardCountGuideline`, `moduleSlotsPerScenarioRange`.

### UI and print changes
- Draw the arrow on the **right edge** of every entity card face, positioned at the top half or bottom half of the card height, so it lines up with the halves of a split card next to it.
- The card editor needs a required top/bottom arrow control on every entity card.
- Card preview and print: show the arrow on single-effect cards too.
- Validation: arrow required on every entity card, split cards need two faces, single cards need one.
- *(Proposal)* Add a **storyline preview** tool: pick or randomly draw cards, lay them out in a row, and show the active half of every split card. This is the fastest way to test the arrow rule and Redirect. Rule: use the arrow of the card immediately before the split card. For the first card, use the arrow of the last resolved card (the top of the discard pile), or the default `top` if there is none.

## 2. Modules

Scenarios now use modules, like Marvel Champions. Full rules are in `rules/06-modules.md`. Two example modules are in `data/modules/`.

### Data model
- **Module:** `id`, `name`, `theme`, `setIcon` (short text or image for the card corner), `compatibleScenarios` (list of scenario ids, or all), `traits`, `boardCards`, `entityCards`, optional `setup`, `status`.
- Module `entityCards` use the same shape as scenario entity cards (including `arrow` and `addedByBeat`).
- **Scenario** gets `moduleRules`: `required` (count, 1 to 3) and `recommended` (list of module ids). See `data/kraken.json`.
- Module cards belong to a module, not a scenario. A card's origin (scenario base deck or a module) should be shown in the UI and printed as the set icon.

### UI features
- Module list, create and edit. The card editor and card list should be reused for module cards.
- Scenario editor: set the required module count (1 to 3) and choose recommended modules.
- **Deck assembly view** for a scenario: pick modules, check the count against `required`, and show the resulting entity deck (card total, omen cost distribution, top and bottom arrow counts, type counts). Warn if the selected modules are not compatible with the scenario.
- Modules that a story beat adds later (`addedByBeat`) should appear separately from the starting deck.

### Print
- Print a module as its own deck, and print a scenario's base deck separately from its modules.
- Print the module's set icon on every card in the module, so the decks can be separated after play.
- Board cards created by a module (Drowned, Stormcrow) are printed with the module.

## 3. Rules editor
- Update the rules documents to the new files (`01` to `06`). `06-modules.md` is new.
- `05-decisions-and-open-questions.md` was rewritten. The old open question about how Redirect works on a printed arrow is now resolved, and several new questions are listed. Do not make decisions on those. Ask the designer.

## 4. Suggested order
1. Migrate the data (arrow required, import arrows, config keys).
2. Update the card editor, card preview and print layout for the arrow.
3. Add the Module model, the module editor and the scenario module settings.
4. Add the deck assembly view and module print.
5. Add the storyline preview tool.
6. Update the rules documents in the app.
