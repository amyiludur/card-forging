// The browser half of App\Support\Markup. Same token forms, the same line
// breaks and the same lists and emphasis, so what the editor shows while
// typing matches what the print sheet renders.
//
// Icons are drawn from the same path data the server uses (App\Support\Icons,
// shared through Inertia's props), so the two cannot show different icons.

import { mentionsPerPlayer, scaledMarkup } from './playerScaled.js';

const escapeHtml = (value) =>
    String(value ?? '')
        .replace(/&/g, '&amp;')
        .replace(/</g, '&lt;')
        .replace(/>/g, '&gt;')
        .replace(/"/g, '&quot;');

export const autoIconise = (text) =>
    String(text ?? '').replace(/\b(\d+)\s+(omen|gold|damage)\b/gi, (_, n, word) => `${n} {${word.toLowerCase()}}`);

const formatConfigValue = (value) => {
    if (typeof value === 'boolean') return value ? 'yes' : 'no';
    // An equation prints as typed, with its player count as the {perPlayer}
    // token so the pass below can draw it as the icon.
    if (typeof value === 'string' && mentionsPerPlayer(value)) return scaledMarkup(null, value);
    if (Array.isArray(value)) return value.join(' to ');
    if (value === null || value === undefined) return '—';
    // A map prints its parts: {signature: 20, domain: 20} → "20 signature, 20 domain".
    if (typeof value === 'object') return Object.entries(value).map(([k, v]) => `${v} ${k}`).join(', ');
    return String(value);
};

/** One icon as inline SVG, matching Icons::svg() on the server. */
export const iconSvg = (icon, className = 'icon') =>
    icon
        ? `<svg class="${className}" viewBox="0 0 ${icon.w} ${icon.h}" xmlns="http://www.w3.org/2000/svg" fill="currentColor" aria-hidden="true" focusable="false"><path d="${icon.d}"/></svg>`
        : '';

/**
 * A token is lowercase letters, digits and hyphens: {unique}, {bottom-draw}.
 * A keyword may carry a whole number after a colon — {fastplay:2}, "Fast Play 2"
 * — and only a keyword: an icon or unknown token with one is left as typed.
 * Matches Markup::TOKEN.
 */
const TOKEN = /\{([a-z][a-z0-9-]*)(?::(\d+))?\}/g;

/**
 * The scenario's Dread rule. camelCase, so it is not a token in the sense above
 * and can never collide with a keyword the designer names.
 */
const DREAD_RULE = /\{dreadRule\}/g;

/**
 * The Dread that scenario starts on. camelCase for the same reason, and written
 * into the text the same way: what goes in is the designer's number or their
 * equation, so an equation's player count draws as the {perPlayer} icon below.
 */
const DREAD_AMOUNT = /\{dreadAmount\}/g;

/**
 * The omen that scenario's pool starts on — its own number, or the shared
 * tunable one when it sets none. Matches Markup::OMEN_AMOUNT.
 */
const OMEN_AMOUNT = /\{omenAmount\}/g;

/**
 * {perPlayer}, the icon an equation's player count draws as. camelCase, so like
 * {dreadRule} it needs its own pattern: the token regex above is lowercase only,
 * which is what stops a keyword the designer names from colliding with it.
 */
const PER_PLAYER = /\{perPlayer\}/g;

/**
 * The name of the card the text is on. Lowercase, so it shares the keyword
 * namespace; the keywords editor refuses a keyword called "this", which is what
 * keeps it from colliding. Matches Markup::THIS_PATTERN.
 */
const THIS = /\{this\}/g;

/** A list item: a bullet or a number, a space, and the item's text. Matches Markup::LIST_ITEM. */
const LIST_ITEM = /^\s*([-*•]|\d+[.)])\s+(.*)$/u;

/**
 * **bold** and *italic* inside one line, matching Markup::emphasis(). The
 * asterisks have to hug the words and sit outside them, so "2 * 3" and "2*3*4"
 * are left alone.
 */
const emphasis = (line) =>
    line
        .replace(/(?<![\w*])\*\*(?=[^\s*])(.+?)(?<=[^\s*])\*\*(?![\w*])/gu, '<strong>$1</strong>')
        .replace(/(?<![\w*])\*(?=[^\s*])(.+?)(?<=[^\s*])\*(?![\w*])/gu, '<em>$1</em>');

/**
 * The little markdown card text takes, on text already escaped, matching
 * Markup::formatting(): "- ", "* ", "• " bullets and "1. " / "1) " numbers make
 * lists, every other line break is a <br>, and each line takes emphasis.
 */
const formatting = (escaped) => {
    let html = '';
    // The open list's tag, or null between lists.
    let list = null;
    // Whether the last thing written was a line of text, so the next one needs
    // a <br> before it. A list is a block and needs none.
    let afterText = false;

    for (const line of escaped.split(/\r\n|\r|\n/)) {
        const item = line.match(LIST_ITEM);

        if (item) {
            const tag = /^\d/.test(item[1]) ? 'ol' : 'ul';

            if (list !== tag) {
                html += `${list !== null ? `</${list}>` : ''}<${tag} class="markup-list">`;
                list = tag;
            }

            html += `<li>${emphasis(item[2])}</li>`;
            afterText = false;
            continue;
        }

        if (list !== null) {
            html += `</${list}>`;
            list = null;
        } else if (afterText) {
            html += '<br>';
        }

        html += emphasis(line);
        afterText = true;
    }

    return list !== null ? `${html}</${list}>` : html;
};

/**
 * Write the scenario's Dread rule into the text, matching
 * Markup::expandDreadRule(). Substituted, not rendered and spliced in, and the
 * replacement is never rescanned: a rule that itself says {dreadRule} is
 * reported rather than expanded, so there is no loop to guard against.
 */
const expandDreadRule = (text, rule) => {
    const written = String(rule ?? '').trim();

    // A function replacement, so a rule containing $ is written out as typed.
    return written === '' ? text : text.replace(DREAD_RULE, () => written);
};

/**
 * Write the scenario's starting Dread into the text, matching
 * Markup::expandDreadAmount(). The same substitution the rule gets, and never
 * rescanned for the same reason.
 */
const expandDreadAmount = (text, amount) => {
    const written = String(amount ?? '').trim();

    return written === '' ? text : text.replace(DREAD_AMOUNT, () => written);
};

/** Write the scenario's starting omen into the text, matching Markup::expandOmenAmount(). */
const expandOmenAmount = (text, amount) => {
    const written = String(amount ?? '').trim();

    return written === '' ? text : text.replace(OMEN_AMOUNT, () => written);
};

/**
 * {card:tentacle-lash} or {card:kraken/tentacle-lash}: another card's name.
 * Matches CardNames::PATTERN; App\Support\CardNames has the rules.
 */
export const CARD = /\{card:(?:([a-z0-9-]+)\/)?([a-z0-9-]+)\}/g;

/**
 * The name a reference prints, or null when no card answers to it, matching
 * CardNames::resolve(). `cards` is the shared markup.cards prop: key → cards.
 */
export const resolveCard = (cards, key, owner) =>
    (cards?.[key] ?? []).find((card) => !owner || card.owner === owner)?.name ?? null;

/**
 * Where a referenced card's name starts and ends until it becomes the
 * .markup-card span — private-use characters that escaping and formatting pass
 * straight through. Matches Markup::CARD_OPEN and CARD_CLOSE.
 */
const CARD_OPEN = '';
const CARD_CLOSE = '';

/** Write each {card:…} the card's name, marked for the span, matching Markup::expandCards(). */
const expandCards = (text, cards) =>
    text.includes('{card:')
        ? text.replace(CARD, (match, owner, key) => {
              const name = resolveCard(cards, key, owner);

              return name === null ? match : `${CARD_OPEN}${name}${CARD_CLOSE}`;
          })
        : text;

/**
 * Write the card's name into the text, matching Markup::expandName(). The same
 * substitution the Dread pair gets, and never rescanned for {this}.
 */
const expandName = (text, name) => {
    const written = String(name ?? '').trim();

    return written === '' ? text : text.replace(THIS, () => written);
};

/**
 * One icon token, matching Markup::iconHtml() on the server. The character (or,
 * for {perPlayer}, the words) is the fallback when an icon has no path.
 */
const iconHtml = (name, icons, paths) => {
    const body = paths[name] ? iconSvg(paths[name]) : escapeHtml(icons[name]);

    return `<span class="markup-icon markup-icon-${escapeHtml(name)}" title="${escapeHtml(name)}">${body}</span>`;
};

/** One keyword, matching Markup::keywordHtml() on the server character for character. */
const keywordHtml = (token, keyword, paths, number = '') => {
    const svg = keyword.icon && paths[keyword.icon] ? iconSvg(paths[keyword.icon]) : '';
    // The name is the fallback as well as the usual case: a keyword set to print
    // its icon alone still has to show something when it has none.
    const name = keyword.show_name !== false || svg === '' ? escapeHtml(keyword.name) : '';
    // The card's own X, after whatever the keyword draws: "Fast Play 2".
    const body = (svg !== '' && name !== '' ? `${svg}&nbsp;${name}` : `${svg}${name}`) + (number ? `&nbsp;${number}` : '');
    const title = keyword.description ? `${keyword.name} — ${keyword.description}` : keyword.name;
    const classes = `markup-keyword markup-keyword-${escapeHtml(token)}${keyword.is_placeholder ? ' markup-keyword-placeholder' : ''}`;

    return `<span class="${classes}" title="${escapeHtml(title)}">${body}</span>`;
};

export function renderMarkup(text, { icons = {}, paths = {}, config = {}, keywords = {}, cards = {}, dreadRule = null, dreadAmount = null, omenAmount = null, cardName = null, autoIcons = false } = {}) {
    // Mirrors Markup::toHtml(). The Dread rule goes in as the designer wrote it,
    // before anything else runs, so its own icons, keywords and numbers render
    // here exactly as they do on the scenario page. The two numbers follow it,
    // so a Dread effect that quotes either gets it. The card's name goes in
    // after them, so a Dread effect saying {this} names each card it is quoted on.
    // Other cards' names last, so a Dread rule can name a card too.
    const written = expandCards(
        expandName(
            expandOmenAmount(expandDreadAmount(expandDreadRule(String(text ?? ''), dreadRule), dreadAmount), omenAmount),
            cardName,
        ),
        cards,
    );

    // Line breaks, lists and emphasis, applied to the escaped text before any
    // token becomes real HTML.
    let out = formatting(escapeHtml(autoIcons ? autoIconise(written) : written));

    // A card another card names, underlined and highlighted so it reads as a
    // card rather than as words.
    out = out.replaceAll(CARD_OPEN, '<span class="markup-card">').replaceAll(CARD_CLOSE, '</span>');

    // Whatever {dreadRule} is left is one nothing filled: a card with no
    // scenario, or a Dread rule that named itself. Marked here, while the only
    // markup in the string is those <br>s, for the same reason.
    out = out.replace(DREAD_RULE, '<span class="markup-missing">?dreadRule</span>');

    // Same for the number: a module card has no scenario to read one off.
    out = out.replace(DREAD_AMOUNT, '<span class="markup-missing">?dreadAmount</span>');
    out = out.replace(OMEN_AMOUNT, '<span class="markup-missing">?omenAmount</span>');

    // And for the name: text that is on no card, or a card with no name yet.
    // Before the token pass, so it never reads as an unknown token.
    out = out.replace(THIS, '<span class="markup-missing">?this</span>');

    // A card reference nothing answers to: deleted, or renamed somewhere the
    // tool could not follow it. As typed, so it says which.
    out = out.replace(CARD, (match, owner, key) => `<span class="markup-missing">?card:${owner ? `${owner}/` : ''}${key}</span>`);

    // The sixth icon, drawn here rather than in the token pass below because
    // its name is camelCase and that pass is lowercase only.
    out = out.replace(PER_PLAYER, () => iconHtml('perPlayer', icons, paths));

    out = out.replace(/\{config:([A-Za-z0-9_]+)\}/g, (match, key) => {
        const entry = config[key];
        if (!entry) return `<span class="text-red-600 font-bold">?${escapeHtml(key)}</span>`;

        const classes = entry.is_placeholder
            ? 'font-bold text-amber-700 underline decoration-dotted decoration-amber-400 underline-offset-2'
            : 'font-bold text-stone-900';

        // A tunable number written as an equation prints its player count as
        // the icon, the same as one typed into card text. The token has no
        // HTML-special characters, so substituting it after escaping cannot
        // disturb the escaping. Mirrors Markup::scaledText().
        const body = escapeHtml(formatConfigValue(entry.value))
            .replace(PER_PLAYER, () => iconHtml('perPlayer', icons, paths));

        return `<span class="${classes}" title="${escapeHtml(entry.label)}${entry.is_placeholder ? ' (placeholder)' : ''}">${body}</span>`;
    });

    return out.replace(TOKEN, (match, name, number) => {
        if (name in icons) return number ? match : iconHtml(name, icons, paths);

        if (name in keywords) return keywordHtml(name, keywords[name], paths, number ?? '');

        // An unknown token is left as typed.
        return match;
    });
}

/** Keyword tokens a piece of text uses. */
export const keywordReferences = (text, keywords = {}) =>
    [...new Set([...String(text ?? '').matchAll(TOKEN)].map((m) => m[1]))].filter((token) => token in keywords);

/** Config keys a piece of text depends on. */
export const markupReferences = (text) =>
    [...String(text ?? '').matchAll(/\{config:([A-Za-z0-9_]+)\}/g)].map((m) => m[1]);
