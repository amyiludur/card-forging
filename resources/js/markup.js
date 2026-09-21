// The browser half of App\Support\Markup. Same four token forms and the same
// line breaks, so what the editor shows while typing matches what the print
// sheet renders.
//
// Icons are drawn from the same path data the server uses (App\Support\Icons,
// shared through Inertia's props), so the two cannot show different icons.

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

/** A token is lowercase letters, digits and hyphens: {unique}, {bottom-draw}. */
const TOKEN = /\{([a-z][a-z0-9-]*)\}/g;

/**
 * The scenario's Dread rule. camelCase, so it is not a token in the sense above
 * and can never collide with a keyword the designer names.
 */
const DREAD_RULE = /\{dreadRule\}/g;

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

/** One keyword, matching Markup::keywordHtml() on the server character for character. */
const keywordHtml = (token, keyword, paths) => {
    const svg = keyword.icon && paths[keyword.icon] ? iconSvg(paths[keyword.icon]) : '';
    // The name is the fallback as well as the usual case: a keyword set to print
    // its icon alone still has to show something when it has none.
    const name = keyword.show_name !== false || svg === '' ? escapeHtml(keyword.name) : '';
    const body = svg !== '' && name !== '' ? `${svg}&nbsp;${name}` : `${svg}${name}`;
    const title = keyword.description ? `${keyword.name} — ${keyword.description}` : keyword.name;
    const classes = `markup-keyword markup-keyword-${escapeHtml(token)}${keyword.is_placeholder ? ' markup-keyword-placeholder' : ''}`;

    return `<span class="${classes}" title="${escapeHtml(title)}">${body}</span>`;
};

export function renderMarkup(text, { icons = {}, paths = {}, config = {}, keywords = {}, dreadRule = null, autoIcons = false } = {}) {
    // Mirrors Markup::toHtml(). The Dread rule goes in as the designer wrote it,
    // before anything else runs, so its own icons, keywords and numbers render
    // here exactly as they do on the scenario page.
    const written = expandDreadRule(String(text ?? ''), dreadRule);

    // A typed line break is a line break on the card, applied to the escaped
    // text before any token becomes real HTML.
    let out = escapeHtml(autoIcons ? autoIconise(written) : written).replace(/\r\n|\r|\n/g, '<br>');

    // Whatever {dreadRule} is left is one nothing filled: a card with no
    // scenario, or a Dread rule that named itself. Marked here, while the only
    // markup in the string is those <br>s, for the same reason.
    out = out.replace(DREAD_RULE, '<span class="markup-missing">?dreadRule</span>');

    out = out.replace(/\{config:([A-Za-z0-9_]+)\}/g, (match, key) => {
        const entry = config[key];
        if (!entry) return `<span class="text-red-600 font-bold">?${escapeHtml(key)}</span>`;

        const classes = entry.is_placeholder
            ? 'font-bold text-amber-700 underline decoration-dotted decoration-amber-400 underline-offset-2'
            : 'font-bold text-stone-900';

        return `<span class="${classes}" title="${escapeHtml(entry.label)}${entry.is_placeholder ? ' (placeholder)' : ''}">${escapeHtml(formatConfigValue(entry.value))}</span>`;
    });

    return out.replace(TOKEN, (match, name) => {
        if (name in icons) {
            // The character is the fallback when an icon has no path.
            const body = paths[name] ? iconSvg(paths[name]) : escapeHtml(icons[name]);

            return `<span class="markup-icon markup-icon-${escapeHtml(name)}" title="${escapeHtml(name)}">${body}</span>`;
        }

        if (name in keywords) return keywordHtml(name, keywords[name], paths);

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
