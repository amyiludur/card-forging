# Decisions and Open Questions

Use this to tell what is a real design decision from what is a stand-in value. The platform should treat placeholders as easy-to-edit parameters.

## Decided by the designer
- Cooperative, players vs. an **entity** (not a single villain). An entity can be a creature, a concept or a group.
- The entity has an **entity deck**, set-aside **story beats** that are added to and removed from the deck over time, and an **entity board** set up at the start.
- Not all entity cards are split. Some are single-effect.
- Split cards have an arrow pointing at the top or bottom half, which decides the effect. Players can manipulate it, but only some characters can (Redirect).
- Storyline cards resolve and are discarded.
- **Shop:** some of a player's cards start in a shop, not the deck.
- **Town:** each player can take each town action once per round, at the end of the entity phase. Town actions add omen. Omen builds fast.
- **Omen:** players build the pool by playing cards. The reveal takes cards until their omen cost meets or exceeds the pool, then the pool empties. X-cost cards drain the remainder.
- **Empowered:** overshoot makes the last revealed card stronger.
- **Dread:** the name, and the rule that too few cards revealed (fewer than X) triggers a strongly negative effect. X rises during the game.
- **Gold:** does not carry over. Some decks have a gold pouch (about 2 gold). Cards can generate gold.
- Some entities, like a plague, have no health. Aggressive decks must still feel like they are achieving something.
- Entity cards have general **types** so character cards can affect them.
- "Omen" stays as the name. The pool is the "omen pool", the card value is the "omen cost".
- **Arrows are printed on split cards**, and **Redirect places a token over the printed arrow** to override it in play. The arrow is therefore a field on the card (`arrow`: top or bottom), not a game-state value. (Was open question 1.)

## Placeholders (invented to make things playable, tune in playtesting)
- Starting omen 4. Base gold 2 per round. Cards add 0 to 2 omen when played. Each town action adds 1 omen. End of round adds 1 omen. Each board kill removes 1 omen.
- Starting Dread X = 2, and Dread effect for the Kraken.
- All Kraken numbers: health, card counts, damage, omen costs, town costs.
- Gold is lost at end of round (the designer decided it doesn't carry over, the exact timing is mine).
- Empty deck: reshuffle and raise Dread by 1.
- Story cards can't be cancelled.
- Lose condition.

## Open questions

*Arrows on split cards are settled (see above). With printed arrows, every split card now needs its arrow chosen: the platform flags the ones that have not been.*

1. **Type per half or per card?** Currently each half of a split card has its own type, so Redirect can change a card's type.
2. **Empty entity deck.** Currently reshuffle plus Dread +1. The designer is not sold on "empty deck advances a story beat", so that is only an idea.
3. **Dread only rises on beats.** If players stall on a beat, pressure drops. Consider a second source, such as +1 every few rounds.
4. **X-cost cards vs. Dread.** An X-cost card drains all omen, so revealing it early can be the only card and trigger Dread by accident. Currently it skips the Dread check.
5. **Empowered cap.** A big overshoot could make one card very strong.
6. **Kills bleeding omen** could let aggressive decks trigger Dread on themselves. Keep the amount small.
7. **Curse balance.** Most omen and gold effects are Curses, so a character that cancels Curses is very strong.
8. **Story cards can't be cancelled.** Consider allowing cancellation of side effects only.
9. **Naming overlap:** "story beat" vs. "storyline". Candidates for the revealed line: chapter, scene, act.
10. **Characters are not designed yet:** health, hand size, deck size, starting hand, gold generation, and how characters use omen. Response cards are only defined in passing.
11. **Shop details:** currency (gold assumed), whether it is a face-up row, whether it refreshes or can be cycled, and what "other means" of acquiring shop cards are.
12. **Town details:** how potions and upgrades work.
13. **Win and lose:** whether every scenario ends on the final beat or some end on damage. Lose conditions are placeholders.
