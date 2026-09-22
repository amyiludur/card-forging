# Berserker

*Status: draft; every number is a placeholder for playtesting. Structured version: `berserker.json`.*

**Identity:** Stands where the entity is already aiming. Counts as the first player when damage lands and takes one less of it. Highest health so far; no cards written yet.

## Character card

- **Health:** 14
- **Hand size:** 5 *(placeholder, not chosen: the tool's default)*
- **Gold per round:** 4 *(placeholder, not chosen: what both other characters generate)*
- **Ability, Bear the Brunt** *(working name)*: During the entity phase, when damage would be dealt to the first player, you count as the first player. Damage dealt to you is reduced by 1, to a minimum of 1.
- **Name and story:** not written yet (`title` and `story` are empty in the data)

## Signature cards (0 of 20)

None written. A deck is 20 signature cards plus 20 domain cards, so the character page and the deck
builder both report this one as 20 short until they are. Nothing has been written on the designer's
behalf.

## Upgrades

None written.

## Design notes

- Health 14 and the ability are the designer's. Hand size and gold per round are not — see the two
  placeholders above.
- The ability makes the Berserker the target of entity **damage** aimed at the first player. It does
  not take the first player token, which still passes left at the end of the round
  (`rules/02-turn-structure.md`), so an effect that discards the first player's item or takes their
  gold still hits whoever holds it.
- Open: is the reduction only for damage taken through the first half of the ability, or for all
  damage dealt to the Berserker? Written as all damage, as dictated.
- Open: 14 health and a flat −1 per hit is two kinds of durability at once. Watch whether the
  reduction makes the health total redundant, or the other way round.
