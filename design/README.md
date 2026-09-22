# Card Game (working title)

A cooperative card game in which players face an **entity** (a creature, a concept, or a group) through a story of set-aside beats. The core mechanic is **omen**: players build it by playing cards and using the town, and the entity spends it by revealing cards. Too little omen and **Dread** triggers. Too much and the entity overwhelms the players.

This folder is the design source of truth, and the starting point for a platform to edit cards, edit rules, and print them. Start with `CLAUDE.md`.

## Current work (v3): player characters
The app has been built from v1 and updated for v2 (`CHANGES-v2.md`). The current work is the **player side**: characters, domains, signature cards, the shop and upgrades. Read `players/README.md` first. It covers the deck structure (20 signature cards plus 20 domain cards), the new card fields, the changes to `data/rules-config.json`, the new keywords, and the things to check. Two characters are drafted, the Gunslinger and the Soothsayer, each as markdown and as JSON for import. Domains are not designed yet.

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
| `players/README.md` | Player side overview: deck structure, new card fields, config changes, keywords, things to check |
| `players/gunslinger.md`, `players/gunslinger.json` | The Gunslinger: character card, Revolver kit, 20 signature cards, 5 upgrades |
| `players/soothsayer.md`, `players/soothsayer.json` | The Soothsayer: character card, 20 signature cards, 4 upgrades |
| `players/berserker.md`, `players/berserker.json` | The Berserker: character card only; no cards written yet |

## Conventions
- In markdown, *(placeholder)* marks an invented number that is expected to change.
- `rules/05-decisions-and-open-questions.md` lists what the designer has decided and what has not been decided yet. Open questions 18 to 20 (characters, shop, town) are partly answered by `players/README.md`. Anything marked as unsure there is still a placeholder.
- Character data in `players/` is a draft. Every number is a placeholder for playtesting.
