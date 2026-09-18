# Scenario 1: The Kraken

*Status: full draft. Every number is a placeholder for playtesting. Structured version: `data/kraken.json`.*

**Type:** creature
**Overview:** Storms have kept the fleet in harbour for weeks, and now something is dragging ships under. The sea itself hides the Kraken. The players have to survive the storm, break the Ocean's hold, and bring the Kraken to the surface.

**Starting Dread (X):** 2

## Setup
- **The Ocean:** the Kraken is immune to damage. Removed when beat 3 advances (which begins beat 4), or by specific effects (for example a rare shop card).
- **The Kraken:** health 12 per player. Can't be damaged while The Ocean is in play.
- **Tentacles (x3):** minions, 3 health each. At the start of the entity phase, each Tentacle deals 1 damage to the first player. A maximum of 6 Tentacles can be in play. Any beyond that deal 1 damage to the first player instead.
- **Story beats:** beats 1 to 4, in order.
- **Entity deck:** the base deck below plus the chosen modules' cards, shuffled.

## Modules

- **Required:** 2 modules (placeholder).
- **Recommended:** *What Lurks Below*. The second slot is free. For variety, players can choose *That Which Comes From the Sky* or any other compatible module.
- Module cards are shuffled into the entity deck at setup, on top of the 24-card base deck below. See `../rules/06-modules.md` and `../data/modules/`.

## Entity Deck (24 cards)

Traits: Tentacle, Storm, Deep. **Arrow** is the arrow on the card's right edge (it tells the next split card which half to resolve). All arrows are placeholders.

### Single-effect (14)

| Card | Qty | Omen | Arrow | Type | Trait | Effect |
|---|---|---|---|---|---|---|
| Tentacle Lash | 3 | 1 | Top | Attack | Tentacle | The first player takes 2 damage. |
| Barnacled Grasp | 2 | 1 | Bottom | Hazard | Tentacle | Exhaust one of the first player's cards in play. |
| Salt Wind | 2 | 1 | Top | Curse | Storm | Add 2 omen to the pool. |
| Choking Ink | 2 | 2 | Bottom | Curse | Deep | Each player loses 2 gold. |
| Tentacle Rises | 2 | 2 | Top | Summon | Tentacle | Create a Tentacle. |
| Crushing Coil | 2 | 3 | Bottom | Attack | Tentacle | The first player takes 4 damage. |
| The Kraken Stirs | 1 | 2 | Top | Story | Deep | Create a Tentacle. Advances beat 1 if it is the current beat. |

### Split (10)

| Card | Qty | Omen | Arrow | Trait | Top (type) | Bottom (type) |
|---|---|---|---|---|---|---|
| Whispers Below | 2 | 1 | Bottom | Deep | The first player discards a random card. (Hazard) | Each player takes 1 damage. (Attack) |
| Rising Tide | 2 | 2 | Top | Storm | Each player discards a card. (Hazard) | Add 2 omen to the pool. (Curse) |
| Storm Surge | 2 | 2 | Bottom | Storm | Each player takes 1 damage. (Attack) | Heal 2 damage from each Tentacle. (Hazard) |
| Dragged Under | 2 | 3 | Top | Deep | Exhaust a player until the next round. (Hazard) | The first player takes 3 damage. (Attack) |
| Foundering Boat | 2 | 3 | Bottom | Storm | Discard one of the first player's items in play. (Hazard) | Create 2 Tentacles. (Summon) |

### Added by story beats

| Card | Qty | Omen | Arrow | Layout | Type | Trait | Effect |
|---|---|---|---|---|---|---|---|
| Howling Gale | 3 | 2 | Top | Split | Top: Attack. Bottom: Curse | Storm | Top: Each player takes 1 damage and discards a card. Bottom: Add 3 omen. |
| Grasping Depths | 3 | X | Bottom | X-cost | Summon | Tentacle | Create X Tentacles (max 3). Dread isn't checked on this reveal (placeholder). |
| The Abyss | 1 | 4 | Top | Single | Story | Deep | Each player takes 2 damage. Create 2 Tentacles. Advances beat 3 if it is the current beat. |
| Death Throes | 3 | 2 | Bottom | Single | Attack | Tentacle | The first player takes 3 damage. Each other player takes 1 damage. |

## Dread Effect
Create a Tentacle and add 5 omen to the pool. Because the pool was just emptied, this becomes the head start on the next reveal.

## Story Beats

### Beat 1: Troubled Waters
*The harbour bells ring. Something is testing the moorings.*
- **Advance:** destroy 2 Tentacles during this beat, or resolve *The Kraken Stirs*.
- **On advance:** shuffle 3 *Howling Gale* into the entity deck.

### Beat 2: Storm Front
*The sky closes in, and the water begins to turn.*
- **On reaching:** put the **Whirlpool** into play (board card, 5 health). At the end of the entity phase, it adds 1 omen.
- **Advance:** destroy the Whirlpool.
- **On advance:** Dread X +1. Shuffle 3 *Grasping Depths* and *The Abyss* into the entity deck.

### Beat 3: Into the Deep
*The Ocean holds its breath.*
- **Advance:** destroy every Tentacle at the same time (at least one must have been destroyed this beat), or resolve *The Abyss*.
- **On advance:** remove **The Ocean**. Dread X +1. Shuffle 3 *Death Throes* into the entity deck.

### Beat 4: The Surface
*The Kraken is no longer hidden.*
- **Advance:** defeat the Kraken. **The players win.**

## Town
The harbour town has three districts (all placeholders):
- **Chapel:** heal 3 (costs 2 gold).
- **Apothecary:** buy a potion (costs 2 gold).
- **Smithy:** upgrade a card (costs 3 gold).

Each action adds 1 omen. While the Whirlpool is in play, the Chapel adds 1 additional omen (flooded streets).

## Win and Lose
- **Win:** complete beat 4.
- **Lose:** all players are defeated (placeholder).

## Designer notes
- **Aggression:** Tentacles are killable from turn one, and killing them advances beat 1 and bleeds omen. Beats 2 and 3 have specific targets, and beat 4 opens the Kraken itself.
- **Beat 3 is the most likely to be wrong.** Destroying every Tentacle at once may be too hard while the deck keeps making more. *The Abyss* is the safety valve. Check this one first in playtesting.
- **Beat 2 has only one way forward** (destroy the Whirlpool). That is why it adds omen, so ignoring it costs players something.
- **Dread reaches 4 by beat 4**, meaning players need roughly 8 or more omen per round to avoid it. This may be too harsh.
- **Type balance:** Attack and Hazard dominate and Summon is thin.
