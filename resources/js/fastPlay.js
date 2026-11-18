// Fast Play, the one keyword the playtest table reads off a card's text.
//
// The table automates what every card does and never what one card says — but
// Fast Play is a move out of the shop, the same for every card that has it, so
// the table offers it: buy the card, then play it for X omen instead of its gold
// cost and instead of the omen printed on it. X is the card's own number,
// written {fastplay:2}; a bare {fastplay} is 1. Both are the designer's calls.
//
// It is read by its token, so renaming the keyword's token in the library
// leaves the table not seeing it — the card still prints, it just plays like
// any other shop card.

const FAST_PLAY = /\{fastplay(?::(\d+))?\}/;

/** A bare {fastplay} costs this much omen. */
export const FAST_PLAY_DEFAULT = 1;

/** The omen a card's Fast Play costs, or null when it has none. */
export const fastPlayOmen = (text) => {
    const match = FAST_PLAY.exec(String(text ?? ''));

    return match ? Number(match[1] ?? FAST_PLAY_DEFAULT) : null;
};
