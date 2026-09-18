# Scenario 2: The Pale Fever

*Status: early draft. Structure and beats exist, the full entity deck, Remedy cards and town have not been designed. Card types below are placeholders.*

**Type:** concept (no health)
**Overview:** A plague is spreading through the players' own town. It has no health, so it can only be beaten by containing it and finding the cure.

**Starting Dread (X):** 2 (placeholder)

## Setup
- **Outbreaks (x3):** health 3 (placeholder). At the start of the entity phase, each Outbreak adds 1 to the **Infection track**.
- **Infection track:** starts at 0, maximum 10 (placeholder).

**Infected town:** each Outbreak in play makes one town action worse (for example, Healing costs +1 omen while the Hospital District is infected). Clearing Outbreaks makes the town safer, so aggressive play is rewarded there too.

## Modules
Not designed yet. Required module count: 1 (placeholder). Modules for this scenario will need to fit a concept entity, so the sea and sky modules do not apply. See `rules/06-modules.md`.

## Entity deck (not yet built, around 30 cards)
Every card needs an arrow (top or bottom) when the deck is built.
- Single-effect: infection spikes and spawning Carriers (minions with 2 health).
- Split: fevers and panic. Top effects hit players, bottom effects strengthen Outbreaks.
- X-cost: *Contagion*, which drains the remaining omen and places X infection across Outbreaks.

## Story Beats

| Beat | Name | Advance trigger | On advance |
|---|---|---|---|
| 1 | First Cases | Destroy 2 Outbreaks, or Infection reaches 4 | Shuffle Carrier cards into the deck |
| 2 | The Spread | Destroy 3 Carriers in total, or Infection reaches 7 | Dread X +1. Outbreaks gain +1 health |
| 3 | Patient Zero | Destroy Patient Zero (weak point, 6 health, only damageable while no Carriers are in play) | Dread X +1. Shuffle in *Final Stage* cards |
| 4 | The Cure | Complete the Cure track (3 progress). Progress comes from playing *Remedy* cards bought from the shop | Players win |

Beats can advance by player action (killing things) or by entity action (Infection climbing), so the story moves either way.

## Example cards (placeholders)

| Card | Layout | Omen | Type | Effect |
|---|---|---|---|---|
| Cough in the Crowd | Single | 1 | Curse | Add 1 Infection. |
| Fever Dream | Split | 2 | Top: Hazard. Bottom: Curse | Top: a player discards a random card. Bottom: each Outbreak gains +1 health. |
| Quarantine Breaks | Single | 3 | Summon | Create a Carrier. Empowered gives it +1 health per excess. |
| Contagion | X-cost | X | Curse | Drain all remaining omen. Place X Infection across Outbreaks. |

## Win and Lose
- **Win:** complete beat 4 (the Cure track).
- **Lose:** Infection reaches its maximum, or all players are defeated (placeholder).

## Designer notes
- **Aggression:** Outbreaks always have health. Kills reduce Infection pressure, bleed omen, and advance beats 1 and 2. Patient Zero is the weak point in beat 3, which works like a boss fight for an entity with no health.
- **Ties into the shop and town:** Remedy cards make the shop matter to the win condition, and infected districts make town actions costlier.
- **Beat 4 tension:** players need gold for Remedies while omen keeps building. Remedy cost needs tuning so it isn't a pure gold race.
