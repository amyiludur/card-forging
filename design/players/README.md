# Player side: characters, domains and signature cards

*Status: draft. Every number is a placeholder for playtesting. Nothing here overrides `rules/`; it extends it.*

## Files
| File | What it is |
|---|---|
| `gunslinger.md` / `gunslinger.json` | Character card, Revolver kit, 20 signature cards, 5 upgrades |
| `soothsayer.md` / `soothsayer.json` | Character card, 20 signature cards, 4 upgrades |

## Deck structure (decided, except where marked)
- A deck is 20 signature cards plus 20 domain cards, 40 total.
- Neutral (colourless) cards fill domain slots. They do not add to the 40.
- Some of the 40 start in the player's own shop pile. Each card has a default `startZone` (deck or shop).
- Upgrades are set aside outside the 40. The Smithy town action swaps a card for its upgrade.
- The gold pouch is a card (signature, domain or neutral), not a character feature.
- Hand size is set by the character. Players draw up to it in step 3 of the player phase.
- A card bought from the shop goes to the bottom of the deck. *(Designer likes this; not 100% sure.)* A future merchant character could buy to hand.
- **Deck runs out (placeholder):** shuffle the discard pile into a new deck and add 1 omen to the pool.

## New card fields
`characterId`, `origin` (signature, domain, neutral), `type` (action, item, response), `goldCost`, `omenIcons` (0 to 2), `traits`, `keywords`, `qty`, `startZone` (deck, shop, play, upgrade), `shopCost`, `upgradesTo`, `upgradeOf`.
Character fields: `health`, `handSize`, `ability` (name and text), `kit`, `title` and `story` (both empty for now).

## Changes to `rules-config.json`
Remove `handSize` (now per character) and add:
```json
"deckSize": { "signature": 20, "domain": 20 },
"neutralFillsDomainSlots": true,
"shopPurchaseDestination": "deck-bottom",
"playerDeckOutReshuffle": true,
"playerDeckOutOmen": 1
```
Flag `shopPurchaseDestination` as unsure in the UI.

## New keywords
- **Bullet** (trait): ammo. Bought from the shop, played from hand.
- **Fired:** after a Bullet resolves, return it to your shop instead of discarding it.
- **Tuck:** put a card under the Revolver. Tucked Bullets can be played as if in hand, then return to the shop.
- **Bottom draw:** draw from the bottom of your deck.
- **Redirect (Flip, Swap, Remove):** the three candidate forms, spread across the Soothsayer's cards for playtesting.
- **Foresight** (trait): looks at or reorders the entity deck or the story beats.

## Things to check
1. The Gunslinger's Revolver is outside the 20. Discard effects such as Foundering Boat can hit it.
2. Bottom-of-deck purchases and the Gunslinger: Deadeye, Bandolier and Quick Draw exist to fix this. Watch it in playtests.
3. Fired Bullets skip the discard pile, so the Gunslinger's deck runs out faster and pays more deck-out omen.
4. Can a card in the shop be upgraded, or only cards in deck, hand or discard?
5. The Soothsayer's Responses cost gold and the Smithy and shop do too. Watch for gold starvation.
6. Cancel and Remove effects skip Story cards (existing placeholder rule).
7. Class names: Gunslinger and Soothsayer are working names. The character `title` and `story` come later.
