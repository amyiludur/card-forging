# Gunslinger

*Status: draft; every number is a placeholder for playtesting. Structured version: `gunslinger.json`.*

**Identity:** Fast, cheap and loud. Pays in omen instead of gold. Ammo returns to the shop after firing.

## Character card

- **Health:** 10
- **Hand size:** 5 (drawn up to at the start of the player phase)
- **Ability, Deadeye:** Once per round, draw the bottom card of your deck.
- **Name and story:** not written yet (`title` and `story` are empty in the data)

## Kit (starts in play, not part of the 20)

| Card | Qty | Type | Gold | Omen | Shop cost | Start | Traits | Effect |
|---|---|---|---|---|---|---|---|---|
| Revolver | 1 | Item | 0 | 0 |  | Play | Weapon | Starts in play. Once per round, when you would return a Bullet to your shop, tuck it under this card instead (max 2 tucked). You may play a tucked Bullet as if it were in your hand. When a tucked Bullet is played, it returns to your shop. |

## Signature cards (20)

20 cards. 12 start in the deck and 8 start in the shop. Types: Action 14, Item 2, Response 4. Omen icons: 0 omen x7, 1 omen x12, 2 omen x1.

| Card | Qty | Type | Gold | Omen | Shop cost | Start | Traits | Effect |
|---|---|---|---|---|---|---|---|---|
| Standard Round | 3 | Action | 0 | 0 | 1 | Deck | Bullet | Deal 2 damage to a board card. |
| Hollow Point | 3 | Action | 0 | 1 | 1 | Shop | Bullet | Deal 3 damage to a board card. |
| Scattershot | 2 | Action | 0 | 1 | 2 | Shop | Bullet | Deal 1 damage to each of up to 3 board cards. |
| Silver Round | 1 | Action | 0 | 2 | 2 | Shop | Bullet | Deal 5 damage to a board card. |
| Warning Shot | 2 | Response | 0 | 1 | 2 | Shop | Bullet | Cancel a Hazard as it would resolve. |
| Bandolier | 1 | Item | 1 | 0 |  | Deck | Gear | Once per round, when you draw from the bottom of your deck, look at the bottom 3 cards and draw one of them instead. |
| Quick Draw | 2 | Action | 1 | 1 |  | Deck |  | Draw 2 cards from the bottom of your deck. |
| Fan the Hammer | 2 | Action | 1 | 1 |  | Deck |  | Play up to 2 Bullets from your hand. Each one that deals damage deals 1 extra damage. |
| Duck and Cover | 2 | Response | 1 | 0 |  | Deck |  | Prevent up to 2 damage from an Attack. |
| Lucky Coin | 1 | Item | 1 | 0 |  | Deck | Gear | Once per round, gain 1 gold. |
| Wanted Poster | 1 | Action | 1 | 1 |  | Deck |  | Choose a board card. Bullets deal 1 extra damage to it until the end of the round. |

## Upgrades (set aside, outside the 40)

| Upgrade | Replaces | Type | Gold | Omen | Effect |
|---|---|---|---|---|---|
| Peacemaker | Revolver | Item | 0 | 0 | Starts in play when upgraded. Once per round, when you would return a Bullet to your shop, tuck it under this card instead (max 3 tucked). You may play a tucked Bullet as if it were in your hand. When a tucked Bullet is played, it returns to your shop. |
| Ammo Belt | Bandolier | Item | 1 | 0 | Once per round, when you draw from the bottom of your deck, look at the bottom 5 cards and draw one of them instead. |
| Explosive Round | Hollow Point | Action | 0 | 1 | Deal 3 damage to a board card and 1 damage to another board card. |
| Lightning Draw | Quick Draw | Action | 1 | 1 | Draw 3 cards from the bottom of your deck. |
| Dodge Roll | Duck and Cover | Response | 0 | 0 | Prevent up to 3 damage from an Attack. |

## Design notes

- The Revolver is a kit card and is not part of the 20. Entity effects that discard items (for example Foundering Boat) can still hit it. Needs a rule.
- Deadeye, Bandolier and Quick Draw exist because shop purchases go to the bottom of the deck. They are how the Gunslinger reaches the bullets they just bought.
- Fired Bullets return to the shop, not the discard pile, so the Gunslinger's deck empties faster. That means more deck-out omen.
- Upgrades sit outside the 40. Open: can a Bullet that is in the shop be upgraded, or only cards in deck, hand or discard?
