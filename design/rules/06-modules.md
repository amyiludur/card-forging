# Modules

*Status: draft. Numbers and module contents are placeholders. Data: `data/modules/`.*

Modules work like the modular encounter sets in Marvel Champions. A scenario has a core (its base entity deck, board and story beats), and modules are added on top so that not every play of the same scenario feels the same.

## What a module is
A module is a themed set of entity cards. It can also include board cards (the minions and effects its cards create) and a set icon printed on every card, so the deck can be separated after play.

A module contains:
- **Entity cards:** about 8 (placeholder). Every card has an omen cost, type, traits, an arrow and its effects, like any other entity card. A module card can be marked as **added by a story beat**, in which case it stays set aside until that beat advances.
- **Board cards:** minions or effects that the module's cards create (for example *Drowned*).
- **Traits:** the module's theme traits (for example Deep, Sky), so character cards can target them.
- **Compatibility:** the scenarios (or kinds of entity) the module works with.
- **Setup effects** *(optional, none in the current examples)*: things that happen when the module is included, such as an extra board card.

## How scenarios use modules
- Each scenario states how many modules it **requires**, usually 1 to 3.
- A scenario can list **recommended** modules. These are the intended experience. The remaining slots are free.
- Players can swap a recommended module for another compatible one, or fill the free slots as they like, for a different game.
- At setup, shuffle the cards of every chosen module into the entity deck with the base deck. Cards added by story beats stay set aside.

**Example (The Kraken):** requires 2 modules. *What Lurks Below* is recommended and the other slot is free. Players who want something different can choose *That Which Comes From the Sky* for the free slot.

## Example modules (placeholders)

### What Lurks Below
Theme: things in the dark water. Traits: Deep, Drowned. Board card: **Drowned** (3 health, deals 1 damage to the first player each round).

| Card | Qty | Omen | Arrow | Type | Effect |
|---|---|---|---|---|---|
| Drowned Sailors | 2 | 2 | Top | Summon | Create a Drowned. |
| Pressure of the Deep | 2 | 1 | Bottom | Curse | Add 1 omen for each Tentacle in play (max 3). |
| Cold Currents | 2 | 2 | Top | Split | Top (Hazard): each player exhausts a card in play. Bottom (Curse): each player loses 1 gold. |
| Sunken Wreck | 1 | 3 | Bottom | Hazard | Discard one of the first player's items in play. Each player loses 1 gold. |
| The Deep Watches | 1 | 3 | Top | Attack | The first player takes 2 damage for each Drowned in play (max 4). |

### That Which Comes From the Sky
Theme: storms, lightning and things that fall or fly. Traits: Sky, Storm. Board card: **Stormcrow** (2 health, adds 1 omen when created, deals 1 damage to the first player each round).

| Card | Qty | Omen | Arrow | Type | Effect |
|---|---|---|---|---|---|
| Stormcrows | 2 | 2 | Bottom | Summon | Create a Stormcrow. |
| Lightning Strike | 2 | 2 | Top | Attack | The first player takes 3 damage. |
| Thunderhead | 2 | 2 | Bottom | Split | Top (Curse): add 2 omen to the pool. Bottom (Hazard): each player discards a card. |
| Falling Star | 1 | 3 | Top | Attack | Each player takes 2 damage. |
| Eclipse | 1 | 2 | Bottom | Curse | Each player loses 2 gold. Add 1 omen. |

## Design notes
- **Deck size and pacing.** Every module adds cards, so a longer deck means the entity deck runs out later. Keeping modules about the same size (8 cards) keeps them comparable.
- **Story beats reference cards by name.** A beat that shuffles in "3 Howling Gale" refers to a scenario card. Modules should not be required by beats. If a beat should react to a module, the module's own cards can be marked as added by that beat.
- **Traits do the cross-module work.** Character cards that target traits (Tentacle, Deep, Sky) get a different meaning depending on which modules are in the deck.
