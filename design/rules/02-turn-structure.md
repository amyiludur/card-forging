# Turn Structure

*Status: draft. Numbers marked (placeholder) are tunable.*

## Setup
1. Choose a scenario. Set up the entity: board cards, entity deck, and story beats in order (beat 1 face up).
2. Each player builds their deck and shop pile, draws a starting hand, and takes their gold pouch if their deck has one.
3. Set the omen pool to **4** (placeholder, so the first reveal isn't empty) and Dread X to the scenario's starting value.
4. Give the first player token to any player.

## Round
A round has two phases. Players act first, then the entity.

### Player phase
1. **Reveal.** Reveal cards from the top of the entity deck until their total omen cost meets or exceeds the omen pool. Then empty the omen pool.
   - **Empowered:** if the total exceeds the pool, the last revealed card gains +1 to the first number in its effect per point of excess.
   - **Dread:** if fewer than X cards were revealed, the scenario's Dread effect happens now, after the pool is emptied. A pool of 0 reveals no cards, so it always triggers Dread.
   - **X-cost cards:** the card's cost equals the omen still unmatched, so it ends the reveal.
   - Revealed cards form the **storyline**, in reveal order. Split cards keep the arrow position printed on them.
2. **Gold.** Each player generates gold (base 2, placeholder) and adds any pouch gold.
3. **Ready and draw.** Players ready exhausted cards and draw up to hand size (hand size not yet defined).
4. **Actions.** Players act in any order and can pass. They can:
   - Play cards. Each card played adds omen equal to its omen icons (0 to 2, placeholder).
   - Buy from the shop.
   - Attack board cards and mitigate the storyline (defend, prevent, **Redirect**).

### Entity phase
1. **Board upkeep.** Board cards resolve their "start of entity phase" effects.
2. **Storyline resolves.** Resolve each storyline card in reveal order. Single-effect cards resolve their effect. Split cards resolve the half their arrow points at. Then discard them. Players can play **Response** cards (paying gold) while a card would resolve.
3. **Town phase.** Each player can take each town action once. Each action costs gold and adds omen (1 per action, placeholder).
4. **End of round.**
   - Add 1 omen to the pool (placeholder, the passing of time).
   - Players lose unspent gold, except what fits in a gold pouch.
   - Pass the first player token left.

## Timing rules
- **Story beats advance immediately** when their trigger is met, mid-phase if necessary.
- **Redirect** can be used at any time before the affected card resolves.
- **Kills bleed omen:** when a board card is destroyed, remove 1 omen from the pool (placeholder).
- **Empty deck:** shuffle the discard pile into a new entity deck and raise Dread X by 1 (placeholder, not final).
- **"The first player"** is the target of any entity effect that doesn't name one.
