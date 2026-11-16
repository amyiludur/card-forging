# Runesmith

*Status: draft; every number is a placeholder for playtesting. Structured version: `runesmith.json`.*

**Identity:** not written yet. Carves Runes that stay in play across rounds, then breaks them for a bigger effect when it matters.

## Character card

- **Health:** 10
- **Hand size:** 5
- **Gold per round:** 3
- **Ability, Carve:** Once per round, you may carve a Rune without paying its gold cost.
- **Name and story:** not written yet (`title` and `story` are empty in the data)

## Runes (decided, except where marked)

- **Carve:** put a Rune into play in front of you, paying its gold and adding its omen. A carved Rune stays in play across rounds.
- **Break:** at any time, discard a carved Rune to resolve its Break effect, as long as the effect would actually do something.
- **Break cost escalates, in omen:** the first Break is free, the next adds 1 omen, then 2, and so on. The count resets at the start of the player phase.
- **Forced discard:** a Rune you are made to discard can't be broken in response.
- **Rune limit:** you can only have so many Runes carved, raised by upgrades or specific cards. Here the Runestone sets it at 2 *(placeholder)*.

## Kit (starts in play, not part of the 20)

| Card | Qty | Type | Gold | Omen | Shop cost | Start | Traits | Effect |
|---|---|---|---|---|---|---|---|---|
| Runestone | 1 | Item | 0 | 0 |  | Play | Gear | Starts in play. You may have up to 2 Runes carved. Keep a token here for each time you break a Rune. |

## Signature cards (20)

20 cards. 14 start in the deck and 6 start in the shop. Types: Action 5, Item 13, Response 2. Omen icons: 0 omen x7, 1 omen x12, 2 omen x1.

| Card | Qty | Type | Gold | Omen | Shop cost | Start | Traits | Effect |
|---|---|---|---|---|---|---|---|---|
| Rune of Warding | 1 | Item | 2 | 0 |  | Deck | Rune | While carved, once per round, prevent 1 damage from an Attack. Break: Prevent all damage from one Attack. |
| Rune of Binding | 1 | Item | 2 | 1 |  | Deck | Rune | While carved, Summons enter play with 1 damage on them. Break: Cancel a Summon. |
| Ember Rune | 2 | Item | 1 | 1 |  | Deck | Rune | While carved, the first damage you deal each round deals 1 extra damage. Break: Deal 4 damage to a board card. |
| Rune of Turning | 1 | Item | 1 | 1 |  | Deck | Rune, Redirect | While carved, once per round, look at the top card of the entity deck. Break: Flip an arrow in the storyline. |
| Rune of Thrift | 1 | Item | 1 | 0 |  | Deck | Rune, Pouch | While carved, store up to 1 gold between rounds. Break: Gain 3 gold. |
| Inscribe | 2 | Action | 1 | 1 |  | Deck |  | Carve a Rune from your hand in front of another player. It counts towards your Rune limit, and they may Break it. |
| Lesser Burst | 2 | Response | 1 | 1 |  | Deck |  | Resolve the Break effect of 2 Runes in play, then discard 1 of them. |
| Rune of Mending | 1 | Item | 1 | 0 |  | Deck | Rune | While carved, at the end of the round, heal 1 damage from a player. Break: Heal 4 damage from a player. |
| Rune of Plenty | 1 | Item | 1 | 0 |  | Deck | Rune | While carved, the first time you carve a Rune each round, draw a card. Break: Draw 2 cards. When this is discarded, gain 2 gold. |
| Deep Well Rune | 1 | Item | 2 | 0 | 2 | Shop | Rune | Break: Remove 3 omen from the pool. |
| Rune of Patience | 1 | Item | 1 | 0 | 2 | Shop | Rune | At the end of each round this is carved, put a charge on it. Break: Deal 1 damage to a board card, or prevent 1 damage from an Attack, plus 1 for each charge on this. |
| Echo Sigil | 1 | Item | 2 | 1 | 3 | Shop | Rune | While carved, when you Break another Rune, return it to your hand instead of discarding it. |
| Chisel Set | 1 | Item | 1 | 0 | 2 | Shop | Gear | You may have 1 more Rune carved. |
| Resonance | 2 | Action | 2 | 1 |  | Deck |  | X is the number of Runes you have carved. Remove X omen from the pool and deal X damage to a board card. |
| Runeburst | 1 | Action | 3 | 2 | 3 | Shop |  | Resolve the Break effect of each Rune in play. Don't discard them. |
| Rune of Sundering | 1 | Item | 2 | 1 | 2 | Shop | Rune | While carved, the first board card destroyed each round removes 1 more omen from the pool. Break: Deal 1 damage to each board card. |

## Upgrades (set aside, outside the 40)

| Upgrade | Replaces | Type | Gold | Omen | Effect |
|---|---|---|---|---|---|
| Master's Anvil | Runestone | Item | 0 | 0 | Starts in play when upgraded. You may have up to 3 Runes carved. Keep a token here for each time you break a Rune. |

## Design notes

- The cards are suggestions the designer accepted for playtesting, not cards the designer wrote. Resonance, Runeburst, Lesser Burst's "discard 1" and Rune of Plenty's "when this is discarded" are the designer's ideas; the wording and numbers are not.
- Warding, Binding and Turning were cut from 2 copies to 1 to make room for more kinds of Rune, so a Runeburst has more different effects to fire.
- Rune of Plenty: a normal Break is a discard, so it draws 2 and gains 2 gold. Inside a Runeburst it only draws. Gold from a forced discard in the entity phase may be lost at the end of the round before it can be spent.
- Open: Lesser Burst resolves 2 Break effects. Does that count as 2 Breaks for the escalating omen cost? The Rune it discards is the player's choice, so it is not a forced discard.
- Open: Rune of Plenty's carved effect was not chosen. Written as the smaller version; the alternative was "draw up to 1 more card in the draw step".
- The ability used to end "When a rune card is triggered, discard it." Break replaces that sentence and is now a keyword.
- Two Kraken cards say "Discard one of the first player's items in play", and nothing yet says who chooses the item. With the forced-discard rule, that decides how exposed the Runes are.
- Open: carving with the limit already reached. Either it is not allowed, or an old Rune is discarded first (not broken, so no effect).
- Open: is a "while carved" effect free every round, or does the Rune exhaust to use it?
- Open: when a Rune carved in front of another player with Inscribe is broken, whose Break count goes up and who pays?
- Open: Resonance counts "Runes you have carved". Does a Rune carved in front of another player with Inscribe count?
- Open: Runeburst resolves every Break effect without discarding. Does that count as Breaking for the escalating cost (once, once per Rune, or not at all)? Does "each Rune in play" include other players' Runes, and does the "would actually do something" rule skip the ones that wouldn't?
- Open: Echo Sigil returns broken Runes to hand. Watch whether that, together with the free Carve, makes Breaking too cheap.
