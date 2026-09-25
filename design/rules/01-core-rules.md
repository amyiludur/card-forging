# Core Rules

*Status: draft. Values marked (placeholder) are tunable and live in `data/rules-config.json`.*

A cooperative card game in which players face an **entity** rather than a single villain, similar in spirit to the villains in Marvel Champions. The central theme is **omen**: players and the entity both generate and spend it, and the players can only stay ahead of it for so long. The game should feel overwhelming.

## The Entity

Players don't face one villain. They face an **entity**, which could be a creature (the Kraken), a concept (a plague, a curse) or a group of enemies. Each scenario is a story the players play through and need to win.

An entity has three parts:

- **Entity deck:** the shuffled deck revealed by the omen system. It is the scenario's base deck plus the cards of the chosen modules.
- **Story beats:** key story cards set aside at the start of the game, in order. Over time they are added to the entity deck, taken out of it, or put onto the board, so the story changes as the game goes on.
- **Entity board:** cards the entity sets up at the start of the game, like the villain setup in Marvel Champions. These are persistent effects, minions and other things in play that the players deal with.

### Advancing the story
The current story beat advances when its trigger is met. Triggers are defined per beat. Typical examples:
- Players complete an objective, such as destroying certain board cards.
- A specific entity card resolves.
- Under consideration: the entity deck runs out (not decided, see `05-decisions-and-open-questions.md`).

When a beat advances, its effects resolve. These can include shuffling cards into the entity deck, removing cards from it, changing the board, and raising Dread.

### Winning
Each scenario defines its own win condition, usually completing the final story beat. Some entities (a plague) have no health and are beaten purely through the story.

### Making aggression matter
Aggressive decks must always have something meaningful to hit, even when the entity itself has no health or is immune:
- **Board pieces have health.** Outbreaks, carriers, tentacles and minions can be damaged and destroyed.
- **Kills advance the story.** Destroying certain board cards counts towards advancing a story beat.
- **Kills bleed off omen.** Destroying a board card removes omen from the pool (1, placeholder). This is a pressure valve, and it interacts with Dread: bleeding too much omen risks revealing too few cards.
- **Weak points** *(idea)*: a beat can expose a weak point that must be damaged to complete it.

## Shop

During deck building, not all of a player's cards start in their deck. Some go into a **shop**. As the game goes on, players can purchase these cards or acquire them through other means.
When purchasing cards from the shop, those cards go on top of the players deck

## Gold

- Gold is the main resource. Players spend it to play cards, buy from the shop, play Response cards and use town actions.
- **Gold does not carry over.** Unspent gold is lost at the end of the round (see turn structure).
- Some decks have a **gold pouch** that stores up to 2 gold between rounds.
- Some cards generate extra gold.

## Town

Players have access to a **town**. Town actions include:
- Heal X
- Buy a potion
- Upgrade a card

**Timing:** players can use the town at any point during the player phase. Each player can take each town action **once** per round. Every town action adds omen to the pool, so recovery has a real cost. Each action also costs an additional gold for each other town action used.

Each scenario defines its own town (districts, costs, and how the scenario's board state affects them).

## Omen

Omen is the escalation engine.

- The **omen pool** holds omen counters. They build up as players play cards and use town actions.
- Entity cards have an **omen cost**.

**Start of the player phase:**
1. Reveal cards from the top of the entity deck until their total omen cost meets or exceeds the omen pool.
2. Empty the omen pool.

**Empowered (overshoot):** if the total omen cost exceeds the pool, the last revealed card gains +1 effect strength for each point of excess.

**Dread:** if fewer than X cards are revealed, a strongly negative effect triggers (for example, add 5 omen). This stops players keeping omen artificially low or taking tiny actions to slow the game. X starts at a scenario-defined value and increases as the game goes on, for example when story beats or storyline cards trigger.

**X-cost cards:** these drain all remaining omen, and their effect scales with X (for example, create X tentacles).

Some player characters can generate and spend omen too.

## Entity cards, arrows and the storyline

Entity cards come in two layouts:

- **Single-effect cards:** one effect. Predictable.
- **Split cards:** two halves, each with its own effect. Volatile.

**Every** entity card, single or split, has an **arrow** on its right edge, pointing at the top or bottom. When revealed cards are laid out in a row (the **storyline**), a card's arrow points at the top or bottom half of the card next to it. A split card resolves the half indicated by the arrow of the card immediately before it. The arrow on a single-effect card has no effect on itself, but still tells the next card which half to use.

Because the arrows are visible as soon as the storyline is revealed, players can see which half of every split card will resolve, and plan around it.

Every entity card also has a type and may have traits (see `03-card-types.md`).

The storyline is the line of revealed entity cards (working name). Storyline cards resolve and are then discarded.

**Redirect:** some player characters can change an arrow in the storyline, which changes which half of a split card resolves. This is character-specific (a cleric or seer, for example), not something every player can do. The exact forms of Redirect are not final (see `04-keywords-and-terminology.md`).

## Modules

Scenarios use **modules**, as in Marvel Champions, so no two plays feel the same. A module is a themed set of entity cards (and sometimes board cards) that is shuffled into the entity deck at setup. Each scenario requires a set number of modules (usually 1 to 3) and recommends some of them. Players can pick different modules for variety. See `06-modules.md`.

## Player interaction

Players interact with the storyline and the entity board to mitigate what is happening, for example by:
- Defending against damage
- Dealing damage
- Preventing other effects

## Damage
Most damage goes towards the first player, however there are cards in players decks that can prevent this or players can help try and defend for each other
