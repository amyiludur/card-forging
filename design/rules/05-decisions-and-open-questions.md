# Decisions and Open Questions

Use this to tell what is a real design decision from what is a stand-in value. The platform should treat placeholders as easy-to-edit parameters.

## Decided by the designer
- Cooperative, players vs. an **entity** (not a single villain). An entity can be a creature, a concept or a group.
- The entity has an **entity deck**, set-aside **story beats** that are added to and removed from the deck over time, and an **entity board** set up at the start.
- Not all entity cards are split. Some are single-effect.
- **Arrows:** every entity card has an arrow on its right edge, pointing at the top or bottom. It indicates which effect is active on the next split card. This is how the "arrow moves" between cards. Players can manipulate it, but only some characters can (Redirect).
- Storyline cards resolve and are discarded.
- **Modules:** like Marvel Champions, scenarios use modules to vary each play. Each scenario requires a set number of modules (usually 1 to 3) and recommends some. Players can choose others for fun. Examples: *What Lurks Below* (recommended for the Kraken) and *That Which Comes From the Sky*.
- **Shop:** some of a player's cards start in a shop, not the deck.
- **Town:** each player can take each town action once per round, at the end of the entity phase. Town actions add omen. Omen builds fast.
- **Omen:** players build the pool by playing cards. The reveal takes cards until their omen cost meets or exceeds the pool, then the pool empties. X-cost cards drain the remainder.
- **Empowered:** overshoot makes the last revealed card stronger.
- **Dread:** the name, and the rule that too few cards revealed (fewer than X) triggers a strongly negative effect. X rises during the game.
- **Gold:** does not carry over. Some decks have a gold pouch (about 2 gold). Cards can generate gold.
- Some entities, like a plague, have no health. Aggressive decks must still feel like they are achieving something.
- Entity cards have general **types** so character cards can affect them.
- "Omen" stays as the name. The pool is the "omen pool", the card value is the "omen cost".

## Placeholders (invented to make things playable, tune in playtesting)
- Starting omen 4. Base gold 2 per round. Cards add 0 to 2 omen when played. Each town action adds 1 omen. End of round adds 1 omen. Each board kill removes 1 omen.
- Starting Dread X = 2, and Dread effect for the Kraken.
- All Kraken numbers: health, card counts, damage, omen costs, town costs, and every arrow direction.
- Gold is lost at end of round (the designer decided it doesn't carry over, the exact timing is mine).
- Empty deck: reshuffle and raise Dread by 1.
- Story cards can't be cancelled.
- Lose condition.
- The Kraken requires 2 modules with 1 recommended. Both example modules and all their cards. Module size of about 8 cards.
- First card of a storyline uses the arrow of the top card of the discard pile, or top if it is empty.
- Cancelled cards stay in the storyline, so their arrow still counts.
- Redirect defaults to Flip.

## Open questions
1. **Which arrow does a split card use?** Current reading: the arrow of the card immediately before it in the storyline (every card has an arrow, and cards sit in a row so each arrow points at its right-hand neighbour). If instead a single card's arrow should carry over to the next split card past other single cards, the tie-break when several arrows precede a split card needs a rule.
2. **First card in a storyline.** Currently uses the last card resolved (top of the discard pile), or top if none. Alternatives: always top, or the player with the first player token chooses.
3. **Redirect form.** Flip, Swap or Remove (see `04-keywords-and-terminology.md`), or a mix per character. Also how a flipped arrow is shown at the table (a token on the card).
4. **Cancelled or removed cards and their arrows.** Currently a cancelled card keeps its arrow. A card that leaves the storyline (Remove) does not.
5. **Randomness.** Arrows are visible as soon as the storyline is revealed, so the "random half" is now predictable and only reveal order is random. That is probably intended, but it makes Redirect and card sequencing the main levers.
6. **Arrow design guidance.** Whether the designer wants a rule of thumb for arrow direction (for example roughly half top, half bottom in a deck).
7. **Module details:** how many cards per module, whether a module can change setup, Dread or the story beats, whether modules have a difficulty rating, and whether a recommended module can be dropped.
8. **Base deck vs. modules.** The Kraken base deck already has Deep and Storm cards. It may need trimming so modules add flavour rather than overlap.
9. **Type per half or per card?** Currently each half of a split card has its own type, so Redirect can change a card's type.
10. **Empty entity deck.** Currently reshuffle plus Dread +1. The designer is not sold on "empty deck advances a story beat", so that is only an idea.
11. **Dread only rises on beats.** If players stall on a beat, pressure drops. Consider a second source, such as +1 every few rounds.
12. **X-cost cards vs. Dread.** An X-cost card drains all omen, so revealing it early can be the only card and trigger Dread by accident. Currently it skips the Dread check.
13. **Empowered cap.** A big overshoot could make one card very strong.
14. **Kills bleeding omen** could let aggressive decks trigger Dread on themselves. Keep the amount small.
15. **Curse balance.** Most omen and gold effects are Curses, so a character that cancels Curses is very strong.
16. **Story cards can't be cancelled.** Consider allowing cancellation of side effects only.
17. **Naming overlap:** "story beat" vs. "storyline". Candidates for the revealed line: chapter, scene, act.
18. **Characters are not designed yet:** health, hand size, deck size, starting hand, gold generation, and how characters use omen. Response cards are only defined in passing.
19. **Shop details:** currency (gold assumed), whether it is a face-up row, whether it refreshes or can be cycled, and what "other means" of acquiring shop cards are.
20. **Town details:** how potions and upgrades work.
21. **Win and lose:** whether every scenario ends on the final beat or some end on damage. Lose conditions are placeholders.
