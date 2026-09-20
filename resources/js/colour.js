/*
 * The browser's half of app/Support/Colour.php.
 *
 * The print sheet is rendered server side and the preview here, so the colour
 * a card type or a character carries is turned into a printable head band
 * twice — the same way the markup is. Change one of these and change the other.
 *
 * The browser needs its own copy rather than a value passed down with the card,
 * because the editor previews a colour the moment it is picked, before anything
 * has been saved.
 */

/** The card body: what a type label has to stay legible against. */
export const PAPER = '#fdfcf9';
export const LIGHT_INK = '#fdfcf9';
export const DARK_INK = '#1c1917';

/** WCAG AA for the small, bold, wide-tracked type label. */
const READABLE_RATIO = 4.5;

/** A picked colour as #rrggbb, or null for anything that is not one. */
export const normalise = (value) => {
    const text = String(value ?? '').trim().toLowerCase();
    const short = text.match(/^#?([0-9a-f]{3})$/);

    if (short) {
        const [r, g, b] = short[1].split('');

        return `#${r}${r}${g}${g}${b}${b}`;
    }

    const long = text.match(/^#?([0-9a-f]{6})$/);

    return long ? `#${long[1]}` : null;
};

const rgb = (colour) => {
    const hex = (normalise(colour) ?? DARK_INK).slice(1);

    return [0, 2, 4].map((i) => parseInt(hex.slice(i, i + 2), 16));
};

/** WCAG relative luminance, 0 (black) to 1 (white). */
export const luminance = (colour) => {
    const [r, g, b] = rgb(colour).map((channel) => {
        const c = channel / 255;

        return c <= 0.03928 ? c / 12.92 : ((c + 0.055) / 1.055) ** 2.4;
    });

    return 0.2126 * r + 0.7152 * g + 0.0722 * b;
};

/** The WCAG contrast ratio between two luminances, 1 to 21. */
export const contrast = (a, b) => (Math.max(a, b) + 0.05) / (Math.min(a, b) + 0.05);

/** Mixed toward black by amount, 0 to 1. */
export const darken = (colour, amount) => {
    const keep = Math.max(0, 1 - amount);

    return `#${rgb(colour)
        .map((channel) => Math.round(channel * keep).toString(16).padStart(2, '0'))
        .join('')}`;
};

/** The ink that reads over these colours: whichever contrasts further. */
export const ink = (...colours) => {
    const picked = colours.map(normalise).filter(Boolean);

    if (picked.length === 0) return LIGHT_INK;

    // A gradient is only as readable as its worst stop, so the ink is chosen
    // against the mean of them rather than against the first.
    const mean = picked.reduce((total, colour) => total + luminance(colour), 0) / picked.length;

    return contrast(mean, luminance(LIGHT_INK)) >= contrast(mean, luminance(DARK_INK)) ? LIGHT_INK : DARK_INK;
};

/**
 * The ink the other way round, for a halo behind text on a band whose two
 * stops disagree about which ink reads.
 */
export const halo = (...colours) => (ink(...colours) === LIGHT_INK ? DARK_INK : LIGHT_INK);

/**
 * The same colour, darkened until it reads on the card's cream body. A pale
 * yellow type label is the designer's choice and stays theirs on the head band;
 * in six-point tracked capitals it has to be legible.
 */
export const onPaper = (colour) => {
    const picked = normalise(colour);

    if (picked === null) return DARK_INK;

    const paper = luminance(PAPER);

    // 16 steps of 6% is enough to take any colour to black; stopping at the
    // first readable one keeps as much of the designer's hue as it can.
    for (let step = 0; step <= 16; step++) {
        const tried = darken(picked, step * 0.06);

        if (contrast(paper, luminance(tried)) >= READABLE_RATIO) return tried;
    }

    return DARK_INK;
};

/**
 * The head band's background. One colour is flat; two are a slight gradient,
 * which is what a character card asks for and what a split card gets when its
 * halves are different types — top colour at the top, the way the halves sit.
 */
export const band = (from, to = null, angle = 'to bottom') => {
    let a = normalise(from);
    let b = normalise(to);

    if (a === null && b === null) return null;

    a = a ?? b;
    b = b ?? a;

    return a === b ? a : `linear-gradient(${angle}, ${a}, ${b})`;
};
