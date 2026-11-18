// Dormant, the second keyword the playtest table reads off a card's text.
//
// Fast Play is the first, and for the same reason: it is something every card
// carrying it does the same way, so the table can show it without reading what
// one card says. A card with {dormant} lies on the table greyed while it is in
// play, until someone wakes it from its right-click menu.
//
// What wakes it is Carve, and what Carve costs is the card's text and the
// designer's call — so waking is a free move here, and charges nothing.
//
// A card is dormant by default: the instance only remembers `awake`, and
// leaving play forgets it (`moveCard`), so a Rune that comes back into play
// comes back dormant. Read by its token, like Fast Play: renaming the keyword's
// token in the library leaves the table not seeing it.

const DORMANT = /\{dormant\}/;

/** Whether a card's text says {dormant}. */
export const hasDormant = (text) => DORMANT.test(String(text ?? ''));

/** Whether this card, as it lies in play, is still dormant. */
export const isDormant = (card, instance) => hasDormant(card?.text) && !instance?.awake;
