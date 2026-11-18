/*
 * A card's picture, in the browser. The other half of app/Support/CardArt.php:
 * the editor's preview draws the window from whatever the form holds right now,
 * so it cannot wait for the server to resolve the defaults. Change one and
 * change the other.
 */

export const DEFAULT_SIZE = 40;

export const FITS = ['cover', 'contain'];

/**
 * How much of the card the window takes, in percent. Every card has one at
 * 40% by default, blank until there is a picture. A size that is set wins,
 * and 0 means no window at all. `landscape` no longer changes the default; it
 * stays so both halves read the same.
 */
// eslint-disable-next-line no-unused-vars
export const windowSize = (card, landscape) => {
    const size = card?.art_size;

    return size !== null && size !== undefined && size !== '' ? Number(size) : DEFAULT_SIZE;
};

/** The four art fields every card editor's form starts from, off the card as presented. */
export const artFields = (card) => ({
    art: card?.art ?? null,
    art_size: card?.art_size ?? null,
    art_fit: card?.art_fit ?? null,
    art_focus: card?.art_focus ?? null,
});

/** Where the app serves a stored picture from: design/art/<…> is /art/<…>. */
export const artUrl = (art) => (art ? '/' + String(art).replace(/^\/+/, '') : null);

const focusOf = (focus) =>
    Array.isArray(focus) && focus.length === 2
        ? focus.map((v) => Math.max(0, Math.min(100, Math.round(Number(v) || 0))))
        : [50, 50];

/** The window's background: the picture, cropped or whole, around its focus point. */
export const artStyle = (card) => {
    if (!card?.art) {
        return {};
    }

    const [x, y] = focusOf(card.art_focus);

    return {
        backgroundImage: `url("${artUrl(card.art)}")`,
        backgroundSize: FITS.includes(card.art_fit) ? card.art_fit : 'cover',
        backgroundPosition: `${x}% ${y}%`,
    };
};
