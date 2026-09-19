// The browser half of App\Support\Markup. Same two token forms and the same
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

export function renderMarkup(text, { icons = {}, paths = {}, config = {}, autoIcons = false } = {}) {
    // Mirrors Markup::toHtml(): a typed line break is a line break on the card,
    // applied to the escaped text before any token becomes real HTML.
    let out = escapeHtml(autoIcons ? autoIconise(text) : text).replace(/\r\n|\r|\n/g, '<br>');

    out = out.replace(/\{config:([A-Za-z0-9_]+)\}/g, (match, key) => {
        const entry = config[key];
        if (!entry) return `<span class="text-red-600 font-bold">?${escapeHtml(key)}</span>`;

        const classes = entry.is_placeholder
            ? 'font-bold text-amber-700 underline decoration-dotted decoration-amber-400 underline-offset-2'
            : 'font-bold text-stone-900';

        return `<span class="${classes}" title="${escapeHtml(entry.label)}${entry.is_placeholder ? ' (placeholder)' : ''}">${escapeHtml(formatConfigValue(entry.value))}</span>`;
    });

    return out.replace(/\{([a-z]+)\}/g, (match, name) => {
        if (!(name in icons)) return match;

        // The character is the fallback when an icon has no path.
        const body = paths[name] ? iconSvg(paths[name]) : escapeHtml(icons[name]);

        return `<span class="markup-icon markup-icon-${escapeHtml(name)}" title="${escapeHtml(name)}">${body}</span>`;
    });
}

/** Config keys a piece of text depends on. */
export const markupReferences = (text) =>
    [...String(text ?? '').matchAll(/\{config:([A-Za-z0-9_]+)\}/g)].map((m) => m[1]);
