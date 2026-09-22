// The browser half of App\Support\RulesMarkdown: the markdown the rulebook is
// written in — headings, lists, tables and paragraphs, with the card markup
// running inside every one of them.
//
// The rules editor renders its preview while the designer is still typing, so
// it cannot use the server's HTML; the print sheet renders the saved text
// server side, so it cannot use this. That makes them two halves of one rule,
// like the card preview and the print partial. Change one and change the
// other, or the printed rulebook stops being what the preview promised.
//
// The HTML is plain and unclassed on purpose: `.rules-prose` in
// resources/css/app.css and in the print sheet's inline CSS are what style it.

import { renderMarkup } from './markup';

/** A table's separator row: |---|---| , alignment colons and all. */
const SEPARATOR = /^[-:\s]+$/;

const isSeparator = (row) => row.length > 0 && row.every((cell) => cell !== '' && SEPARATOR.test(cell));

/**
 * A heading with nothing but its own words left: the emphasis marks and every
 * {token} gone. What an id is built from, so both halves build the same one.
 */
const bare = (text) => String(text ?? '').replace(/\{[^}]*\}|[*`]/g, '');

/**
 * A heading's id. Prefixed per document, and numbered when a document says the
 * same thing twice, so every id in a printed rulebook is its own.
 */
const anchorFor = (text, prefix, seen) => {
    const slug = bare(text).toLowerCase().replace(/[^a-z0-9]+/g, '-').replace(/-+/g, '-').replace(/^-|-$/g, '');
    const base = `${prefix ? `${prefix}-` : ''}${slug || 'section'}`.replace(/^-|-$/g, '');

    seen[base] = (seen[base] ?? 0) + 1;

    return seen[base] > 1 ? `${base}-${seen[base]}` : base;
};

/**
 * The document as HTML.
 *
 * `options` are renderMarkup's: the icons, their paths, the keyword library
 * and the tunable numbers. `anchor` prefixes every heading id, matching
 * RulesMarkdown::toHtml().
 */
export function renderRules(text, options = {}, anchor = '') {
    const html = [];
    // Open <ul>/<ol> tags, innermost last, each with the indent that opened it.
    const lists = [];
    // Table rows are buffered: which row is the header is only known once the
    // separator row under it has been seen.
    let table = [];
    const seen = {};

    // One line of text: the card markup first, then the three inline forms the
    // rulebook uses. In that order because the markup escapes the text — bold
    // applied first would be escaped along with it.
    const inline = (line) =>
        renderMarkup(line, options)
            .replace(/\*\*(.+?)\*\*/g, '<strong>$1</strong>')
            .replace(/\*(.+?)\*/g, '<em>$1</em>')
            .replace(/`(.+?)`/g, '<code>$1</code>');

    // An item stays open until the next one at its level, because a nested
    // list belongs inside the item above it and not beside it.
    const closeLists = () => {
        while (lists.length) {
            html.push('</li>');
            html.push(`</${lists.pop().tag}>`);
        }
    };

    /**
     * One table. The header is the row above the separator — that is what a
     * separator row means — and a table written without one is all body, which
     * is how a two-column list of terms is usually typed.
     */
    const closeTable = () => {
        if (!table.length) return;

        const rows = [...table];
        table = [];

        const head = rows.length > 1 && isSeparator(rows[1]) ? rows.shift() : null;
        // Any further separator rows are the writer lining the table up, not
        // content: they are dropped rather than printed as a row of dashes.
        const body = rows.filter((row) => !isSeparator(row));

        html.push('<table>');

        if (head) {
            html.push('<thead><tr>');
            head.forEach((cell) => html.push(`<th>${inline(cell)}</th>`));
            html.push('</tr></thead>');
        }

        html.push('<tbody>');
        body.forEach((row) => {
            html.push('<tr>');
            row.forEach((cell) => html.push(`<td>${inline(cell)}</td>`));
            html.push('</tr>');
        });
        html.push('</tbody></table>');
    };

    for (const line of String(text ?? '').split(/\r\n|\r|\n/)) {
        const row = line.match(/^\s*\|(.+)\|\s*$/);

        if (row) {
            closeLists();
            table.push(row[1].split('|').map((cell) => cell.trim()));
            continue;
        }

        closeTable();

        const heading = line.match(/^(#{1,6})\s+(.*)$/);
        const bullet = line.match(/^(\s*)([-*]|\d+[.)])\s+(.*)$/);

        if (heading) {
            closeLists();
            const level = heading[1].length;
            const id = anchorFor(heading[2], anchor, seen);
            html.push(`<h${level} id="${id}">${inline(heading[2])}</h${level}>`);
        } else if (bullet) {
            const indent = bullet[1].replace(/\t/g, '    ').length;
            const tag = ['-', '*'].includes(bullet[2]) ? 'ul' : 'ol';

            // A shallower bullet has come back out of however many lists it
            // is now outside; each one closes the item it was written inside,
            // which is still open under it.
            while (lists.length && indent < lists[lists.length - 1].indent) {
                html.push('</li>');
                html.push(`</${lists.pop().tag}>`);
            }

            if (!lists.length || indent > lists[lists.length - 1].indent) {
                // A deeper bullet opens a list inside the open item above it,
                // so that item is deliberately not closed first.
                lists.push({ tag, indent });
                html.push(`<${tag}>`);
            } else {
                html.push('</li>');

                if (lists[lists.length - 1].tag !== tag) {
                    // Same level, other kind: one list ends, another starts.
                    html.push(`</${lists.pop().tag}>`);
                    lists.push({ tag, indent });
                    html.push(`<${tag}>`);
                }
            }

            html.push(`<li>${inline(bullet[3])}`);
        } else {
            closeLists();
            if (line.trim() !== '') html.push(`<p>${inline(line)}</p>`);
        }
    }

    closeLists();
    closeTable();

    return html.join('');
}
