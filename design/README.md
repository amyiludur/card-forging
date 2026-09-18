# Card Game (working title)

A cooperative card game in which players face an **entity** (a creature, a concept, or a group) through a story of set-aside beats. The core mechanic is **omen**: players build it by playing cards and using the town, and the entity spends it by revealing cards. Too little omen and **Dread** triggers. Too much and the entity overwhelms the players.

This folder is the design source of truth, and the starting point for a platform to edit cards, edit rules, and print them. Start with `CLAUDE.md`.

## Contents

| Path | What it is |
|---|---|
| `CLAUDE.md` | Brief for Claude Code: what to build and how to work |
| `CHANGES-v2.md` | What changed in v2 (arrows on every card, modules) and what the app needs to change |
| `rules/01-core-rules.md` | Entity, shop, gold, town, omen, cards and the storyline |
| `rules/02-turn-structure.md` | Setup, round structure, timing rules |
| `rules/03-card-types.md` | Card types and traits |
| `rules/04-keywords-and-terminology.md` | Keyword and term glossary |
| `rules/05-decisions-and-open-questions.md` | What is decided, what is a placeholder, what is open |
| `rules/06-modules.md` | Modules: what they are, how scenarios use them, two example modules |
| `scenarios/kraken.md` | Scenario 1, full draft |
| `scenarios/pale-fever.md` | Scenario 2, early draft |
| `data/rules-config.json` | Tunable numbers |
| `data/card-types.json` | The shared card types |
| `data/kraken.json` | The Kraken scenario as structured data (deck, beats, board, town, module rules) |
| `data/modules/` | The example modules as structured data |

## Conventions
- In markdown, *(placeholder)* marks an invented number that is expected to change.
- `rules/05-decisions-and-open-questions.md` lists what the designer has decided and what has not been decided yet.
