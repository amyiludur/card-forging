# Core Rules

*Status: draft. Values marked (placeholder) are tunable and live in `data/rules-config.json`.*

A cooperative card game in which players face an **entity** rather than a single villain, similar in spirit to the villains in Marvel Champions. The central theme is **omen**: players and the entity both generate and spend it, and the players can only stay ahead of it for so long. The game should feel overwhelming.

## The Entity

Players don't face one villain. They face an **entity**, which could be a creature (the Kraken), a concept (a plague, a curse) or a group of enemies. Each scenario is a story the players play through and need to win.

An entity has three parts:

- **Entity deck:** the shuffled deck revealed by the omen system.
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

**Timing:** the town phase is at the end of the entity phase. Each player can take each town action **once** per round. Every town action adds omen to the pool (1 per action, placeholder), so recovery has a real cost. Omen builds fast.

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

## Entity cards and the storyline

Entity cards come in two layouts:

- **Single-effect cards:** one effect, no arrow. Predictable.
- **Split cards:** two halves, each with its own effect. An **arrow** points at the top or bottom half, and the half it points at is the effect that resolves. Volatile.

Every entity card also has a type and may have traits (see `03-card-types.md`).

The line of revealed cards is the **storyline** (working name). Storyline cards resolve and are then discarded.

**Redirect:** some player characters can manipulate the arrow on split cards. This is character-specific (a cleric or seer, for example), not something every player can do.

## Player interaction

Players interact with the storyline and the entity board to mitigate what is happening, for example by:
- Defending against damage
- Dealing damage
- Preventing other effects
