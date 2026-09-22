@php
    /** @var \App\Support\RulebookOptions $options */
    $sheet = $options->sheet();

    // Each edge stands on its own, the same way a card sheet's do: a rulebook
    // that will be bound wants a wider inside margin than outside one.
    $marginTop = $options->topMargin();
    $marginRight = $options->rightMargin();
    $marginBottom = $options->bottomMargin();
    $marginLeft = $options->leftMargin();

    $break = $options->newPagePerDocument;
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $title }}</title>
<style>
    /*
     * A rulebook is a column of text, not a grid of cards: the page breaks fall
     * wherever the words run out of paper, so the margins live on @page and the
     * content simply flows. That is why there are no .page elements here and
     * why resources/views/print/sheet.blade.php cannot be reused.
     *
     * Everything is inline for the same reason it is on the card sheet: the PDF
     * is rendered from file:// by headless Chromium, where a stylesheet
     * reference or a webfont URL does not load, so a webfont would print empty
     * boxes. Only families a machine already has. There is a test asserting
     * this page carries neither, which is also why this note spells out
     * neither of the two tag names.
     */
    @page {
        size: {{ $sheet['css'] }};
        margin: {{ $marginTop }}mm {{ $marginRight }}mm {{ $marginBottom }}mm {{ $marginLeft }}mm;
    }

    * { box-sizing: border-box; }

    html, body {
        margin: 0;
        padding: 0;
        background: #52525b;
        color: #1c1917;
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    /*
     * On screen this is the paper, so the designer can see the measure they
     * have chosen at its real width. In print the paper is the paper and
     * @page owns the margins, so it gives them all back.
     */
    .paper {
        width: {{ $sheet['w'] }}mm;
        min-height: {{ $sheet['h'] }}mm;
        padding: {{ $marginTop }}mm {{ $marginRight }}mm {{ $marginBottom }}mm {{ $marginLeft }}mm;
        margin: 0 auto 8mm;
        background: #fff;
    }

    .rules-prose {
        font-size: {{ $options->fontSize }}pt;
        line-height: 1.5;
        orphans: 2;
        widows: 2;
    }

    /*
     * A document is its own measure, so a two-column rulebook is two columns
     * per document rather than one long thread running through the book.
     */
    .document {
        column-count: {{ $options->columns }};
        column-gap: {{ $options->columnGap }}mm;
    }

    @if ($break)
    /* Only break-before, never also break-after on what came before it: two
       forced breaks at one break point are one break, but only one of them
       can be undone for whatever happens to come first in the book. */
    .document, .appendix { break-before: page; }
    .paper > :first-child { break-before: auto; }
    @endif

    .rules-prose h1,
    .rules-prose h2,
    .rules-prose h3,
    .rules-prose h4,
    .rules-prose h5,
    .rules-prose h6 {
        font-family: Georgia, "Times New Roman", serif;
        font-weight: 600;
        line-height: 1.2;
        margin: 1.4em 0 0.5em;
        /* A heading at the foot of a column is a heading in the wrong place. */
        break-after: avoid;
        break-inside: avoid;
    }

    .rules-prose > :first-child { margin-top: 0; }

    /* A document's own title spans the measure rather than sitting in column one. */
    .rules-prose h1 {
        font-size: 1.7em;
        column-span: all;
        margin-top: 0;
        padding-bottom: 0.25em;
        border-bottom: 0.4mm solid #1c1917;
    }

    .rules-prose h2 { font-size: 1.28em; }
    .rules-prose h3 { font-size: 1.1em; }
    .rules-prose h4,
    .rules-prose h5,
    .rules-prose h6 { font-size: 1em; }

    .rules-prose p { margin: 0 0 0.6em; }

    .rules-prose ul, .rules-prose ol { margin: 0 0 0.7em; padding-left: 1.4em; }
    .rules-prose li { margin-bottom: 0.25em; break-inside: avoid; }

    /* A nested list is inside its item, so it keeps the gap off the line above. */
    .rules-prose li > ul, .rules-prose li > ol { margin: 0.25em 0 0; }

    .rules-prose table {
        width: 100%;
        border-collapse: collapse;
        margin: 0 0 0.8em;
        font-size: 0.92em;
        break-inside: avoid;
    }

    .rules-prose th {
        text-align: left;
        vertical-align: top;
        font-weight: 700;
        padding: 0.25em 0.6em 0.25em 0;
        border-bottom: 0.3mm solid #57534e;
    }

    .rules-prose td {
        vertical-align: top;
        padding: 0.25em 0.6em 0.25em 0;
        border-bottom: 0.2mm solid #d6d3d1;
    }

    .rules-prose code {
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        font-size: 0.9em;
        background: #f5f5f4;
        border-radius: 0.6mm;
        padding: 0 0.4mm;
    }

    /* The contents: the designer's own headings, listed. */
    .contents h1 {
        font-family: Georgia, "Times New Roman", serif;
        font-size: 1.7em;
        font-weight: 600;
        margin: 0 0 0.6em;
        padding-bottom: 0.25em;
        border-bottom: 0.4mm solid #1c1917;
    }

    .contents ul { list-style: none; margin: 0; padding: 0; }
    .contents li { margin-bottom: 0.25em; }
    .contents a { color: #1c1917; text-decoration: none; }
    .contents .level-1 { font-weight: 700; margin-top: 0.7em; }
    .contents .level-2 { padding-left: 4mm; }
    .contents .level-3 { padding-left: 8mm; }
    .contents .level-4, .contents .level-5, .contents .level-6 { padding-left: 12mm; }

    /*
     * An appendix is the tool listing the designer's own data — the tunable
     * numbers and the keyword library — not the tool writing rules. Every
     * placeholder is flagged, because a placeholder is not a decision.
     */
    .appendix-note {
        font-size: 0.9em;
        font-style: italic;
        color: #57534e;
        margin: 0 0 0.8em;
    }

    .flag {
        display: inline-block;
        font-size: 0.75em;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        background: #fde68a;
        color: #78350f;
        border-radius: 0.8mm;
        padding: 0 1mm;
        white-space: nowrap;
    }

    .appendix-key {
        font-family: "SFMono-Regular", Consolas, "Liberation Mono", monospace;
        font-size: 0.85em;
        color: #57534e;
    }

    .appendix-meaning { color: #57534e; }

    /*
     * Icons are inline SVG so they survive being rendered from file:// for the
     * PDF. Mirrors .icon in resources/css/app.css and in the card sheet.
     */
    .icon {
        display: inline-block;
        height: 1em;
        width: auto;
        max-width: 1.25em;
        vertical-align: -0.125em;
    }

    .markup-icon { font-weight: 700; }
    .markup-config { font-weight: 700; }
    .markup-missing { color: #b91c1c; font-weight: 700; }
    /* A designer-defined keyword. Mirrored by .markup-keyword in resources/css/app.css. */
    .markup-keyword { font-variant-caps: small-caps; font-weight: 600; letter-spacing: .01em; }
    .markup-keyword-placeholder { color: #b45309; }

    .sheet-note {
        max-width: {{ $sheet['w'] }}mm;
        margin: 6mm auto 2mm;
        color: #fafaf9;
        font-size: 10pt;
        line-height: 1.5;
    }

    .sheet-note .warn { color: #fca5a5; font-weight: 600; }

    @media print {
        body { background: #fff; }
        .paper { width: auto; min-height: 0; margin: 0; padding: 0; }
        .sheet-note { display: none; }
    }
</style>
</head>
<body>

<div class="sheet-note">
    {{ $title }} ·
    {{ count($documents) }} {{ \Illuminate\Support\Str::plural('document', count($documents)) }} ·
    {{ $sheet['label'] }} · {{ $options->columns }}{{ $options->columns === 1 ? ' column' : ' columns' }}
    @if ($omitted > 0)
        · {{ $omitted }} {{ \Illuminate\Support\Str::plural('document', $omitted) }} left out of this run
    @endif
    @if (count($placeholderNumbers) > 0)
        · <span class="warn">quotes {{ count($placeholderNumbers) }} placeholder
        {{ \Illuminate\Support\Str::plural('number', count($placeholderNumbers)) }}:
        {{ implode(', ', $placeholderNumbers) }}</span>
    @endif
    @if ($options->overflows())
        · <span class="warn">these margins leave no room to print in, widen the page or narrow the margins</span>
    @endif
</div>

<div class="paper rules-prose">
    @if ($documents === [] && ! $options->tunableNumbers && ! $options->keywordGlossary)
        <p style="color: #78716c;">
            @if ($omitted > 0)
                Nothing to print. Every document was left out of this run.
            @else
                Nothing to print. There are no rules documents yet.
            @endif
        </p>
    @endif

    @if ($options->contents && $contents !== [])
        <section class="contents">
            <h1>Contents</h1>
            <ul>
                @foreach ($contents as $entry)
                    <li class="level-{{ $entry['level'] }}"><a href="#{{ $entry['id'] }}">{{ $entry['text'] }}</a></li>
                @endforeach
            </ul>
        </section>
    @endif

    @foreach ($documents as $document)
        <section class="document">{!! $document['html'] !!}</section>
    @endforeach

    @if ($options->tunableNumbers)
        <section class="appendix">
            <h1 id="appendix-tunable-numbers">Tunable numbers</h1>
            <p class="appendix-note">
                The numbers the rules above read through <code>{config:…}</code>, listed as they stand today. A number
                flagged as a placeholder has not been decided.
            </p>
            <table>
                <thead><tr><th>Number</th><th>Value</th><th>Key</th></tr></thead>
                <tbody>
                @foreach ($configRows as $row)
                    <tr>
                        <td>
                            {{ $row['label'] }}
                            @if ($row['is_placeholder']) <span class="flag">placeholder</span> @endif
                            @if ($row['description'])
                                <div class="appendix-meaning">{!! $row['description'] !!}</div>
                            @endif
                        </td>
                        <td>{!! $row['value'] !!}</td>
                        <td class="appendix-key">{{ $row['key'] }}</td>
                    </tr>
                @endforeach
                </tbody>
            </table>
        </section>
    @endif

    @if ($options->keywordGlossary)
        <section class="appendix">
            <h1 id="appendix-keywords">Keywords</h1>
            <p class="appendix-note">
                The designer's own keywords, as the cards print them. A keyword flagged as a placeholder is still
                being decided.
            </p>
            @if ($keywordRows === [])
                <p>No keywords have been written yet.</p>
            @else
                <table>
                    <thead><tr><th>Keyword</th><th>What it means</th><th>Typed</th></tr></thead>
                    <tbody>
                    @foreach ($keywordRows as $row)
                        <tr>
                            <td>
                                {!! $row['rendered'] !!}
                                @if ($row['is_placeholder']) <span class="flag">placeholder</span> @endif
                            </td>
                            <td>{!! $row['description'] !!}</td>
                            <td class="appendix-key">&#123;{{ $row['token'] }}&#125;</td>
                        </tr>
                    @endforeach
                    </tbody>
                </table>
            @endif
        </section>
    @endif
</div>

</body>
</html>
