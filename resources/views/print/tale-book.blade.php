@php
    /** @var \App\Support\RulebookOptions $options */
    /** @var \App\Models\Tale $tale */
    $sheet = $options->sheet();

    $marginTop = $options->topMargin();
    $marginRight = $options->rightMargin();
    $marginBottom = $options->bottomMargin();
    $marginLeft = $options->leftMargin();

    // The log grid's columns: one per scenario. A Tale with none still prints
    // one blank column, so the sheet can be filled in by hand.
    $columns = $log ? $log['steps'] : [];

    // What goes in a cell: a box to tick for a check, space to write for the rest.
    $cell = fn (string $kind) => $kind === 'check' ? '<span class="box">☐</span>' : '';

    // A rewards or a note entry is written in words, so its row is tall.
    $tall = fn (string $kind) => in_array($kind, ['rewards', 'note'], true);
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $tale->name }} — {{ $story && $log ? 'story and campaign log' : ($log ? 'campaign log' : 'story') }}</title>
<style>
    /*
     * A Tale's book is a page of text and a log sheet, not a grid of cards, so
     * it takes the rulebook's paper (margins on @page, content flowing) rather
     * than the card sheet's. Everything is inline for the rulebook's reason:
     * the PDF is rendered from file:// by headless Chromium, where a stylesheet
     * reference or a webfont URL does not load. A test asserts this page
     * carries neither, which is why this note names neither tag.
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

    .paper {
        width: {{ $sheet['w'] }}mm;
        min-height: {{ $sheet['h'] }}mm;
        padding: {{ $marginTop }}mm {{ $marginRight }}mm {{ $marginBottom }}mm {{ $marginLeft }}mm;
        margin: 0 auto 8mm;
        background: #fff;
    }

    h1, h2, h3 { font-family: Georgia, "Times New Roman", serif; font-weight: 600; line-height: 1.2; break-after: avoid; }
    h1 { font-size: 1.9em; margin: 0 0 0.4em; padding-bottom: 0.2em; border-bottom: 0.4mm solid #1c1917; }
    h2 { font-size: 1.35em; margin: 1.4em 0 0.4em; }
    h3 { font-size: 1.1em; margin: 1em 0 0.3em; }

    /* Mirrors .rules-prose in rulebook.blade.php and resources/css/app.css. */
    .rules-prose { font-size: {{ $options->fontSize }}pt; line-height: 1.5; orphans: 2; widows: 2; }
    .rules-prose p { margin: 0 0 0.6em; }
    .rules-prose ul, .rules-prose ol { margin: 0 0 0.7em; padding-left: 1.4em; }
    .rules-prose li { margin-bottom: 0.25em; break-inside: avoid; }
    .rules-prose li > ul, .rules-prose li > ol { margin: 0.25em 0 0; }
    .rules-prose table { width: 100%; border-collapse: collapse; margin: 0 0 0.8em; font-size: 0.92em; }
    .rules-prose th { text-align: left; padding: 0.25em 0.6em 0.25em 0; border-bottom: 0.3mm solid #57534e; }
    .rules-prose td { padding: 0.25em 0.6em 0.25em 0; border-bottom: 0.2mm solid #d6d3d1; }
    .rules-prose h1, .rules-prose h2, .rules-prose h3 { border: 0; font-size: 1.1em; margin: 1em 0 0.3em; }

    .step { break-inside: avoid-page; }
    .step-after { border-left: 0.6mm solid #d6d3d1; padding-left: 3mm; color: #44403c; }
    .step-notes { font-style: italic; color: #57534e; font-size: {{ $options->fontSize }}pt; }
    .placeholder { color: #b45309; font-size: 0.75em; text-transform: uppercase; letter-spacing: 0.08em; font-weight: 700; }

    /* The log starts on a page of its own when the story comes before it. */
    .log { break-before: page; }
    .paper > .log:first-child { break-before: auto; }
    .log-meta { display: flex; gap: 8mm; flex-wrap: wrap; font-size: 10pt; margin: 0 0 4mm; }
    .log-meta .line { display: inline-block; min-width: 60mm; border-bottom: 0.3mm solid #1c1917; }
    .grid { width: 100%; border-collapse: collapse; margin: 0 0 6mm; font-size: 9pt; break-inside: avoid; }
    .grid th, .grid td { border: 0.3mm solid #57534e; padding: 1.2mm 1.5mm; vertical-align: top; }
    .grid thead th { background: #f5f5f4; text-align: center; font-weight: 600; }
    .grid tbody th { text-align: left; width: 38%; font-weight: 600; }
    .grid td { height: 8mm; text-align: center; }
    .grid tr.tall td { height: 18mm; }
    .box { font-size: 12pt; }
    .character { margin-top: 5mm; break-inside: avoid; }
    .character .who { font-size: 10pt; margin-bottom: 1.5mm; }
    .character .who .line { display: inline-block; min-width: 70mm; border-bottom: 0.3mm solid #1c1917; }
    .empty { color: #57534e; font-style: italic; font-size: 10pt; }

    /* Inline SVG icons and markup spans. Mirrors the rulebook sheet. */
    .icon { display: inline-block; height: 1em; width: auto; max-width: 1.25em; vertical-align: -0.125em; }
    .markup-icon { font-weight: 700; }
    .markup-config { font-weight: 700; }
    .markup-missing { color: #b91c1c; font-weight: 700; }
    .markup-keyword { font-variant-caps: small-caps; font-weight: 600; letter-spacing: .01em; }
    .markup-keyword-placeholder { color: #b45309; }

    @media print {
        body { background: #fff; }
        .paper { width: auto; min-height: 0; margin: 0; padding: 0; }
    }
</style>
</head>
<body>
<div class="paper">

@if ($story)
    <section class="story">
        <h1>{{ $tale->name }}</h1>

        @if ($story['overview_html'] !== '')
            <div class="rules-prose">{!! $story['overview_html'] !!}</div>
        @endif

        @foreach ($story['steps'] as $step)
            <div class="step">
                <h2>{{ $step['number'] }}. {{ $step['scenario'] }}</h2>
                @if ($step['intro_html'] !== '')
                    <div class="rules-prose">{!! $step['intro_html'] !!}</div>
                @endif
                @if ($step['notes_html'] !== '')
                    <p class="step-notes">Setup: {!! $step['notes_html'] !!}</p>
                @endif
                @if ($step['outro_html'] !== '')
                    <h3>When it ends</h3>
                    <div class="rules-prose step-after">{!! $step['outro_html'] !!}</div>
                @endif
            </div>
        @endforeach

        @if (count($story['modes']))
            <h2>Modes</h2>
            @foreach ($story['modes'] as $mode)
                <h3>{{ $mode['name'] }} @if ($mode['is_placeholder'])<span class="placeholder">placeholder</span>@endif</h3>
                @if ($mode['text_html'] !== '')
                    <div class="rules-prose">{!! $mode['text_html'] !!}</div>
                @endif
            @endforeach
        @endif
    </section>
@endif

@if ($log)
    <section class="log">
        <h1>{{ $tale->name }} — campaign log</h1>

        <div class="log-meta">
            <span>Mode:
                @forelse ($log['modes'] as $mode)
                    <span class="box">☐</span> {{ $mode }}&nbsp;&nbsp;
                @empty
                    <span class="line"></span>
                @endforelse
            </span>
            <span>Players: <span class="line"></span></span>
        </div>

        @if (! count($log['steps']))
            <p class="empty">No scenarios in this Tale yet.</p>
        @endif
        @if (! count($log['party']) && ! count($log['character']))
            <p class="empty">No log entries yet.</p>
        @endif

        @if (count($log['party']))
            <h2>Party</h2>
            @include('print.partials.tale-log-grid', ['entries' => $log['party'], 'columns' => $columns, 'cell' => $cell, 'tall' => $tall])
        @endif

        @if (count($log['character']))
            @for ($seat = 1; $seat <= $log['character_blocks']; $seat++)
                <div class="character">
                    <div class="who">Character: <span class="line"></span></div>
                    @include('print.partials.tale-log-grid', ['entries' => $log['character'], 'columns' => $columns, 'cell' => $cell, 'tall' => $tall])
                </div>
            @endfor
        @endif
    </section>
@endif

</div>
</body>
</html>
