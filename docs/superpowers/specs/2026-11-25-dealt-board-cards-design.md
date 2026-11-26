# Board cards dealt to the players

## Why

Not every entity board card starts in play. Meddler in the Dark has four townsfolk — Valter
Butcher, Isabella Obscura, Crystina Smith, Rayner Howard — and at setup each player is given one
at random; the rest do not come into play. Today a board card either starts on the board or comes
out with a beat, so the playtest table puts all four out at Start.

## What the designer decided

- A dealt card goes **to the player's seat**, beside their in-play cards — it is theirs to
  exhaust and to put counters on (Astraphath's "their townsfolk").
- The ones nobody is dealt are **kept aside**: a face-down pile on the entity side of the table,
  which can be looked through and pulled from by hand. Nothing draws from it on its own.
- Approach **A / A** below.

## Approaches

**How a card says it is dealt**

- **A. A `start` on the board card (picked).** One column, `board` (the default) or `dealt`. In
  the design file a dealt card stays in `boardSetup` and carries `"start": "dealt"`, written only
  when it is dealt — so the Kraken, the Legion and every module export byte for byte, and the file
  reads the townsfolk as part of setup, which they are.
- B. A third list, `boardDealtToPlayers`. Easier to spot in a diff, but a copy of the setup
  list's shape and a third list for import and export to keep in step.

**Where a dealt card sits in a seat**

- **A. Its own short row on the seat, at the head of the in-play row (picked).** Click exhausts,
  right-click has counters, *Set aside* and *Put on the entity board*. Every existing move — play,
  buy, discard, reshuffle — assumes a seat holds player cards, so keeping these apart means none
  of them can shuffle a townsfolk into someone's deck.
- B. Mixed into in-play. Looks most natural; every pile move would then have to ask what kind of
  card it is holding, which is where the bugs would come from.

## The design

**Data.** `board_cards.start`, string, not null, default `board`; the other value is `dealt`.
`start` only means anything for a setup card: a card added by a beat comes out with its beat, so
`added_by_beat_id` set wins and the editor does not offer both. A module's board cards are
unchanged — they never come out at setup.

**Design file.** `boardSetup` entries gain `"start": "dealt"` only when dealt. Import reads it
(absent = `board`); an unknown value is imported as `board` and warned about — report, don't
correct. `boardAddedByBeats` is untouched.

**Editor** (`BoardCardRow.vue`, the scenario's board tab). The *Added by beat* select becomes one
**Starts** select: *On the board* / *Dealt to the players* / *Added at beat N…*. The row's summary
says *dealt to the players* the way it now says *added at beat 2*.

**Playtest table.** At Start, in `freshSession`:

- setup cards with `start = board` go out exactly as now;
- the `dealt` cards — each copy of its `qty` — are shuffled, and one is dealt to each seat in
  lineup order; whatever is left is the **aside** pile (`session.boardAside`);
- more seats than cards: deal what there is, and the log names the seats that got none. No card
  is invented, none reused.

A seat gets `seat.entities`, a list of `{ uid, id, exhausted, counter }` board-card instances,
drawn as its own short row at the head of the seat's in-play row with the same `TableCard` face as
a board card. Click toggles exhausted; *Ready and draw* readies it with the rest of the seat. Its
right-click menu: counter +/−, *Set aside* (to `boardAside`), *Put on the entity board* (to
`session.board`, damage 0).

The aside pile sits on the entity rail as a face-down count. Clicking it opens the pile viewer,
face up, and each card there offers *Give to <seat>* and *Put on the entity board*. Every one of
these moves goes through `act()`, so undo and the log work as for any move. A save from before
this existed loads with an empty aside pile and no seat entities; nothing is re-dealt.

**Print and pocket.** A dealt card prints exactly as any board card does — how it enters is the
setup card's sentence, which is the designer's to write. The pocket page lists it with the board
cards, marked *dealt to the players* where the setup ones are unmarked.

## Non-goals

- Dealing any number other than one per player, or dealing to the board rather than the seats.
  Nothing in the game asks for it yet; `start` leaves room for another value if it does.
- Reading card text. Corruption is the seat card's ordinary counter; the table does not count it
  against Astraphath's limit — the players do, as with every other card rule.
- Writing the Meddler's setup step. That sentence is the designer's.

## Left to the builder

- The exact look of the seat's entity row and the aside pile's control on the rail, within the
  table's no-scroll, flex-share rules (`useCardFit`; a region's height must not depend on its
  cards).
- Hotkeys: none required.
- Tests: import/export round trip (dealt key written only when dealt; Kraken and Legion unchanged),
  the editor saving `start`, and the playtest page receiving `start` on each board card.

## Note on the data

The Meddler's four townsfolk are in the dev database but not yet in
`design/data/meddler-in-the-dark.json`, which still holds only Astraphath. Marking them dealt
happens in the editor, then an export writes them out — never an import first.

## Addendum (2026-11-25): deal groups

**Why.** A scenario may one day deal more than one kind of thing — a Townsfolk *and* a Curse to
each player — so a dealt card carries a **group name**, and each group is dealt on its own.

**Decided with the designer.**

- **Data.** `board_cards.deal_group`, nullable string. Only meaningful when `start = dealt`; saving
  a card as `board` or as a beat's clears it. Design file: `"dealGroup": "Townsfolk"` beside
  `"start": "dealt"`, written only when both are set — Kraken and Legion still export byte for byte.
- **Editor.** Choosing *Dealt to the players* shows a **Group** field beside the Starts select, with
  the scenario's existing group names offered as suggestions (a `datalist`), so one group is not
  typed two ways. A dealt card with no group is flagged *no group* in the row summary.
- **Table.** At Start each group is shuffled and dealt separately: one card per player per group, in
  lineup order, so a seat can hold one of each. Leftovers of every group go onto the one *Set aside*
  pile; in its viewer each card is captioned with its group. The log says, per group, who got what
  and who went without (*No Townsfolk left for …*).
- **No group.** A dealt card with no name deals as its own unnamed group (*dealt card* in the log)
  and is reported in the editor — never renamed on the designer's behalf.
- **Pocket page.** One heading per group: *Board cards — Townsfolk, dealt to the players*; an
  unnamed group keeps the plain *Board cards — dealt to the players*.

**Approaches considered.** Dealing by an existing **trait** (the townsfolk already carry *TOWNS
FOLK*) needs no new field, but a trait describes what a card *is* and card text may name it; making
it also drive setup would let a trait edit silently change the deal. A **separate set-aside pile per
group** keeps them apart on the rail, but the rail is narrow and a second or third pile crowds it;
captions in the one viewer carry the same information.
