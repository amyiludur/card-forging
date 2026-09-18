// The browser half of App\Support\Markup. Same two token forms, so what the
// editor shows while typing matches what the print sheet renders.

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
    return String(value);
};

export function renderMarkup(text, { icons = {}, config = {}, autoIcons = false } = {}) {
    let out = escapeHtml(autoIcons ? autoIconise(text) : text);

    out = out.replace(/\{config:([A-Za-z0-9_]+)\}/g, (match, key) => {
        const entry = config[key];
        if (!entry) return `<span class="text-red-600 font-bold">?${escapeHtml(key)}</span>`;

        const classes = entry.is_placeholder
            ? 'font-bold text-amber-700 underline decoration-dotted decoration-amber-400 underline-offset-2'
            : 'font-bold text-stone-900';

        return `<span class="${classes}" title="${escapeHtml(entry.label)}${entry.is_placeholder ? ' (placeholder)' : ''}">${escapeHtml(formatConfigValue(entry.value))}</span>`;
    });

    return out.replace(/\{([a-z]+)\}/g, (match, name) => {
        const icon = icons[name];
        return icon ? `<span class="font-bold" title="${escapeHtml(name)}">${icon}</span>` : match;
    });
}

/** Config keys a piece of text depends on. */
export const markupReferences = (text) =>
    [...String(text ?? '').matchAll(/\{config:([A-Za-z0-9_]+)\}/g)].map((m) => m[1]);
