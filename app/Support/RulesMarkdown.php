<?php

namespace App\Support;

/**
 * The markdown the rulebook is written in: headings, lists, tables and
 * paragraphs, with the card markup ({omen}, {unique}, {config:startingOmen})
 * running inside every one of them.
 *
 * This is deliberately not a markdown implementation. It is the subset the
 * design folder is actually written in, kept small enough that the designer
 * can read the whole of it, and it is mirrored by renderRules() in
 * resources/js/rulesMarkdown.js — the rules editor renders a preview while the
 * designer is still typing, and the print sheet renders the saved text server
 * side, so there is no way for one to render the other's text. Change one half
 * and change the other, or the printed rulebook stops being what the preview
 * promised.
 *
 * It emits plain semantic HTML with no classes of its own. Both stylesheets
 * style it under `.rules-prose` — resources/css/app.css for the editor and the
 * inline CSS of resources/views/print/rulebook.blade.php for the printed page
 * — the same reason a keyword renders through a class rather than utilities.
 */
class RulesMarkdown
{
    /** A table's separator row: |---|---| , alignment colons and all. */
    private const SEPARATOR = '/^[-:\s]+$/';

    public function __construct(private Markup $markup)
    {
    }

    public static function make(): self
    {
        return new self(Markup::make());
    }

    /**
     * The document as HTML.
     *
     * $anchor prefixes every heading id, because a printed rulebook is several
     * documents in one page and two of them may well both have a "Rules"
     * heading. The contents list reads the same ids from {@see headings()}, so
     * a link in the PDF lands on the heading it names.
     */
    public function toHtml(string $text, string $anchor = ''): string
    {
        $html = [];
        // Open <ul>/<ol> tags, innermost last, each with the indent that opened it.
        $lists = [];
        // Table rows are buffered: which row is the header is only known once
        // the separator row under it has been seen.
        $table = [];
        $seen = [];

        // An item stays open until the next one at its level, because a
        // nested list belongs inside the item above it and not beside it.
        $closeLists = function () use (&$lists, &$html): void {
            while ($lists !== []) {
                $html[] = '</li>';
                $html[] = '</'.array_pop($lists)['tag'].'>';
            }
        };

        $closeTable = function () use (&$table, &$html): void {
            if ($table !== []) {
                $html[] = $this->table($table);
                $table = [];
            }
        };

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            if (preg_match('/^\s*\|(.+)\|\s*$/', $line, $m) === 1) {
                $closeLists();
                $table[] = array_map('trim', explode('|', $m[1]));

                continue;
            }

            $closeTable();

            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m) === 1) {
                $closeLists();
                $level = strlen($m[1]);
                $id = $this->anchor($m[2], $anchor, $seen);
                $html[] = '<h'.$level.' id="'.e($id).'">'.$this->inline($m[2]).'</h'.$level.'>';

                continue;
            }

            if (preg_match('/^(\s*)([-*]|\d+[.)])\s+(.*)$/', $line, $m) === 1) {
                $indent = strlen(str_replace("\t", '    ', $m[1]));
                $tag = in_array($m[2], ['-', '*'], true) ? 'ul' : 'ol';

                // A shallower bullet has come back out of however many lists
                // it is now outside; each one closes the item it was written
                // inside, which is still open under it.
                while ($lists !== [] && $indent < end($lists)['indent']) {
                    $html[] = '</li>';
                    $html[] = '</'.array_pop($lists)['tag'].'>';
                }

                if ($lists === [] || $indent > end($lists)['indent']) {
                    // A deeper bullet opens a list inside the open item above
                    // it, so that item is deliberately not closed first.
                    $lists[] = ['tag' => $tag, 'indent' => $indent];
                    $html[] = '<'.$tag.'>';
                } else {
                    $html[] = '</li>';

                    if (end($lists)['tag'] !== $tag) {
                        // Same level, other kind: one list ends, another starts.
                        $html[] = '</'.array_pop($lists)['tag'].'>';
                        $lists[] = ['tag' => $tag, 'indent' => $indent];
                        $html[] = '<'.$tag.'>';
                    }
                }

                $html[] = '<li>'.$this->inline($m[3]);

                continue;
            }

            $closeLists();

            if (trim($line) !== '') {
                $html[] = '<p>'.$this->inline($line).'</p>';
            }
        }

        $closeLists();
        $closeTable();

        return implode('', $html);
    }

    /**
     * The document's headings, for a contents list. Same ids toHtml() writes,
     * generated the same way and in the same order, so the two agree by
     * construction rather than by both being careful.
     *
     * @return list<array{level: int, text: string, id: string}>
     */
    public function headings(string $text, string $anchor = ''): array
    {
        $headings = [];
        $seen = [];

        foreach (preg_split('/\r\n|\r|\n/', $text) ?: [] as $line) {
            if (preg_match('/^(#{1,6})\s+(.*)$/', $line, $m) === 1) {
                $headings[] = [
                    'level' => strlen($m[1]),
                    // The contents is a list of the designer's own words, so it
                    // reads the plain text: a heading with an icon token in it
                    // shows the icon's character rather than an inline SVG.
                    'text' => $this->plain($m[2]),
                    'id' => $this->anchor($m[2], $anchor, $seen),
                ];
            }
        }

        return $headings;
    }

    /**
     * One line of text: the card markup first, then the three inline forms the
     * rulebook uses. In that order because the markup escapes the text — bold
     * applied first would be escaped along with it.
     */
    private function inline(string $text): string
    {
        $html = $this->markup->toHtml($text);

        $html = preg_replace('/\*\*(.+?)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*(.+?)\*/', '<em>$1</em>', $html);

        return preg_replace('/`(.+?)`/', '<code>$1</code>', $html);
    }

    /** A heading as the contents lists it: no markup, no emphasis marks. */
    private function plain(string $text): string
    {
        return trim(preg_replace('/[*`]/', '', $this->markup->toPlain($text)));
    }

    /**
     * A heading with nothing but its own words left: the emphasis marks and
     * every {token} gone. What an id is built from, rather than the rendered
     * text, so the browser half can build the same id without carrying a
     * second copy of Markup::toPlain() over to render a preview that shows no
     * contents list anyway.
     */
    private function bare(string $text): string
    {
        return preg_replace('/\{[^}]*\}|[*`]/', '', $text);
    }

    /**
     * One table. The header is the row above the separator — that is what a
     * separator row means — and a table written without one is all body, which
     * is how a two-column list of terms is usually typed.
     *
     * @param  list<list<string>>  $rows
     */
    private function table(array $rows): string
    {
        $head = [];

        if (isset($rows[1]) && $this->isSeparator($rows[1])) {
            $head = array_shift($rows);
        }

        // Any further separator rows are the writer lining the table up, not
        // content: they are dropped rather than printed as a row of dashes.
        $body = array_values(array_filter($rows, fn (array $row): bool => ! $this->isSeparator($row)));

        $html = '<table>';

        if ($head !== []) {
            $html .= '<thead><tr>';
            foreach ($head as $cell) {
                $html .= '<th>'.$this->inline($cell).'</th>';
            }
            $html .= '</tr></thead>';
        }

        $html .= '<tbody>';
        foreach ($body as $row) {
            $html .= '<tr>';
            foreach ($row as $cell) {
                $html .= '<td>'.$this->inline($cell).'</td>';
            }
            $html .= '</tr>';
        }

        return $html.'</tbody></table>';
    }

    /** @param  list<string>  $row */
    private function isSeparator(array $row): bool
    {
        return $row !== [] && ! array_filter(
            $row,
            fn (string $cell): bool => $cell === '' || preg_match(self::SEPARATOR, $cell) !== 1
        );
    }

    /**
     * A heading's id. Prefixed per document, and numbered when a document says
     * the same thing twice, so every id in a printed rulebook is its own.
     *
     * @param  array<string, int>  $seen
     */
    private function anchor(string $text, string $prefix, array &$seen): string
    {
        $slug = trim(preg_replace('/-+/', '-', preg_replace('/[^a-z0-9]+/', '-', strtolower($this->bare($text)))), '-');
        $base = trim(($prefix !== '' ? $prefix.'-' : '').($slug !== '' ? $slug : 'section'), '-');

        $seen[$base] = ($seen[$base] ?? 0) + 1;

        return $seen[$base] > 1 ? $base.'-'.$seen[$base] : $base;
    }
}
