@php
    /** @var \App\Support\PrintOptions $options */
    $sheet = $options->sheet();
    $cols = $options->columns();
    $rows = $options->rows();
    $cellW = $options->cellWidth();
    $cellH = $options->cellHeight();
    $cardW = $options->cardWidth();
    $cardH = $options->cardHeight();
    $bleed = $options->bleed;
    $radius = $options->cornerRadius;

    // Each edge stands on its own: a sheet of labels is rarely centred on its
    // paper, and the four measurements are the only way to say where it starts.
    $marginTop = $options->topMargin();
    $marginRight = $options->rightMargin();
    $marginBottom = $options->bottomMargin();
    $marginLeft = $options->leftMargin();
    $gutterX = $options->columnGutter();
    $gutterY = $options->rowGutter();

    // The printer's own error, nudged out in millimetres. It moves the whole
    // grid, crop marks included, so the marks keep telling the truth.
    $offsetX = $options->offsetX;
    $offsetY = $options->offsetY;

    $skipped = $options->skipped();

    // Trim-line positions, used to put crop marks in the sheet margin where
    // they will not print over a neighbouring card.
    $verticalTrims = [];
    for ($c = 0; $c < $cols; $c++) {
        $left = $marginLeft + $offsetX + $c * ($cellW + $gutterX) + $bleed;
        $verticalTrims[] = $left;
        $verticalTrims[] = $left + $cardW;
    }
    $horizontalTrims = [];
    for ($r = 0; $r < $rows; $r++) {
        $top = $marginTop + $offsetY + $r * ($cellH + $gutterY) + $bleed;
        $horizontalTrims[] = $top;
        $horizontalTrims[] = $top + $cardH;
    }

    // A crop mark lives in the margin it points into, so each edge's marks are
    // only as long as that edge has room for.
    $markTop = max(2, $marginTop - 1);
    $markBottom = max(2, $marginBottom - 1);
    $markLeft = max(2, $marginLeft - 1);
    $markRight = max(2, $marginRight - 1);

    $mirror = function ($page) use ($cols) {
        // Backs are printed row-reversed so they line up after a long-edge flip.
        return $page->chunk($cols)->map(fn ($row) => $row->reverse()->values())->flatten(1);
    };
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<title>{{ $scenario->name }} — {{ \App\Support\PrintOptions::DECKS[$options->deck] ?? $options->deck }}</title>
<style>
    @page {
        size: {{ $sheet['css'] }};
        margin: 0;
    }

    * { box-sizing: border-box; }

    html, body {
        margin: 0;
        padding: 0;
        background: #52525b;
        font-family: "Helvetica Neue", Helvetica, Arial, sans-serif;
        color: #1c1917;
        -webkit-print-color-adjust: exact;
        print-color-adjust: exact;
    }

    .page {
        position: relative;
        width: {{ $sheet['w'] }}mm;
        height: {{ $sheet['h'] }}mm;
        padding: {{ $marginTop }}mm {{ $marginRight }}mm {{ $marginBottom }}mm {{ $marginLeft }}mm;
        background: #fff;
        margin: 0 auto 8mm;
        overflow: hidden;
        page-break-after: always;
        break-after: page;
    }

    .page:last-child { page-break-after: auto; break-after: auto; }

    .grid {
        display: grid;
        grid-template-columns: repeat({{ $cols }}, {{ $cellW }}mm);
        grid-auto-rows: {{ $cellH }}mm;
        row-gap: {{ $gutterY }}mm;
        column-gap: {{ $gutterX }}mm;
        @if ($offsetX || $offsetY) transform: translate({{ $offsetX }}mm, {{ $offsetY }}mm); @endif
    }

    .cell { position: relative; width: {{ $cellW }}mm; height: {{ $cellH }}mm; }

    /* Crop marks sit in the sheet margin, clear of the cards themselves. */
    .crop { position: absolute; background: #000; }
    .crop-v { width: 0.2mm; }
    .crop-h { height: 0.2mm; }

    .card {
        position: absolute;
        inset: 0;
        border-radius: {{ $bleed > 0 ? 0 : $radius }}mm;
        overflow: hidden;
        background: #fdfcf9;
        display: flex;
        flex-direction: column;
    }

    /* The trim area: everything inside this is guaranteed to survive cutting. */
    .card-inner {
        position: absolute;
        top: {{ $bleed }}mm; right: {{ $bleed }}mm; bottom: {{ $bleed }}mm; left: {{ $bleed }}mm;
        border: 0.25mm solid #1c1917;
        border-radius: {{ $radius }}mm;
        display: flex;
        flex-direction: column;
        overflow: hidden;
        background: #fdfcf9;
    }

    .card-head {
        display: flex;
        align-items: stretch;
        border-bottom: 0.25mm solid #1c1917;
        background: #1c1917;
        color: #fdfcf9;
        min-height: 8mm;
    }

    /*
     * The chips in the head carry their own dark backgrounds, so they keep the
     * light ink even when a pale card type flips the head band's. Mirrored by
     * .card-omen, .card-health and .card-uses in CardPreview.vue.
     */
    .omen {
        flex: 0 0 8mm;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 11pt;
        font-weight: 700;
        border-right: 0.25mm solid #fdfcf9;
        background: #3f3f46;
        color: #fdfcf9;
    }

    .omen-x { font-style: italic; }

    .card-name {
        flex: 1;
        display: flex;
        align-items: center;
        padding: 0.6mm 2mm;
        font-family: Georgia, "Times New Roman", serif;
        font-size: 9pt;
        font-weight: 600;
        line-height: 1.15;
        letter-spacing: 0.01em;
    }

    .health {
        flex: 0 0 8mm;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 8.5pt;
        font-weight: 700;
        background: #7f1d1d;
        color: #fdfcf9;
        border-left: 0.25mm solid #fdfcf9;
        text-align: center;
        line-height: 1.05;
        padding: 0 0.5mm;
    }

    /*
     * The card body is the positioning context for the placeholder flag: the
     * head's right corner holds health or a dread change on some card kinds,
     * and the flag was landing on top of it.
     */
    .card-body { position: relative; flex: 1; display: flex; flex-direction: column; min-height: 0; }

    .half {
        flex: 1;
        display: flex;
        flex-direction: column;
        padding: 1.6mm 2mm;
        min-height: 0;
    }

    .half + .half { border-top: 0.25mm dashed #57534e; }

    .type {
        display: flex;
        align-items: center;
        gap: 0.9mm;
        font-size: 6pt;
        font-weight: 700;
        text-transform: uppercase;
        letter-spacing: 0.12em;
        color: #78716c;
        margin-bottom: 0.8mm;
    }

    .effect {
        font-size: 7.5pt;
        line-height: 1.3;
        flex: 1;
    }

    /*
     * v2: every entity card carries an arrow on its right edge. It points at the
     * top or bottom half of the card to its RIGHT in the storyline, so it is
     * positioned at a quarter or three quarters of the card height to line up
     * with the halves of the split card beside it.
     */
    .arrow-edge {
        position: absolute;
        right: {{ $bleed }}mm;
        transform: translate(35%, -50%);
        font-size: 11pt;
        line-height: 1;
        color: #1c1917;
        text-shadow: 0 0 0.6mm #fdfcf9, 0 0 0.6mm #fdfcf9;
    }

    .arrow-top { top: calc({{ $bleed }}mm + (100% - {{ 2 * $bleed }}mm) * 0.25); }
    .arrow-bottom { top: calc({{ $bleed }}mm + (100% - {{ 2 * $bleed }}mm) * 0.75); }

    .set-icon {
        margin-left: auto;
        font-size: 5.5pt;
        font-weight: 700;
        letter-spacing: 0.08em;
        border: 0.2mm solid #1c1917;
        border-radius: 0.8mm;
        padding: 0.3mm 1mm;
    }

    /* A beat tag and a set icon can both be present; only one takes the gap.
       Same for a player card's start zone and its domain badge, or the two
       auto margins would split the gap and drag the start zone inwards. */
    .beat-tag + .set-icon,
    .start-zone + .set-icon { margin-left: 1.2mm; }

    .card-foot {
        border-top: 0.25mm solid #d6d3d1;
        padding: 1mm 2mm;
        display: flex;
        gap: 1.2mm;
        flex-wrap: wrap;
        align-items: center;
        min-height: 5mm;
    }

    .trait {
        font-size: 5.5pt;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 0.08em;
        border: 0.2mm solid #78716c;
        border-radius: 1mm;
        padding: 0.3mm 1mm;
        color: #57534e;
    }

    .beat-tag { font-size: 5.5pt; color: #92400e; margin-left: auto; font-weight: 600; }

    .placeholder-flag {
        position: absolute;
        top: 1mm;
        right: 1mm;
        font-size: 5pt;
        letter-spacing: 0.1em;
        background: #fde68a;
        color: #78350f;
        padding: 0.3mm 1mm;
        border-radius: 0.8mm;
        font-weight: 700;
    }

    .beat-card .card-head { background: #451a03; }
    .beat-card .omen { background: #78350f; }
    .beat-flavour {
        font-family: Georgia, "Times New Roman", serif;
        font-style: italic;
        font-size: 7pt;
        line-height: 1.3;
        color: #57534e;
        padding: 1.6mm 2mm 0;
    }
    .beat-block { padding: 1.2mm 2mm 0; font-size: 7pt; line-height: 1.25; }
    .beat-label {
        font-size: 5.5pt;
        text-transform: uppercase;
        letter-spacing: 0.1em;
        color: #92400e;
        font-weight: 700;
    }

    .board-card .card-head { background: #14532d; }

    /* A district of the town. Mirrors the .town-card rules in
       resources/js/Components/CardPreview.vue. */
    .town-card .card-head { background: #854d0e; }
    .town-card .omen { background: #a16207; }

    /* The head's right corner, where a board card carries health: what a
       district carries instead is the omen taking the action adds. */
    .omen-add {
        flex: 0 0 8mm;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 8.5pt;
        font-weight: 700;
        background: #78350f;
        border-left: 0.25mm solid #fdfcf9;
        text-align: center;
        line-height: 1.05;
        padding: 0 0.5mm;
    }

    /* The setup card: how the other four piles are laid out before the first
       round. Mirrors the .setup-card rules in
       resources/js/Components/CardPreview.vue. */
    .setup-card .card-head { background: #134e4a; }
    .setup-card .omen { background: #0f766e; }

    .setup-steps {
        margin: 0;
        padding-left: 4mm;
        list-style: decimal;
    }
    .setup-steps li { margin-bottom: 0.8mm; }

    .town-note {
        margin-top: 1mm;
        font-size: 6.5pt;
        font-style: italic;
        line-height: 1.25;
        color: #57534e;
    }

    /* Player side (v3). Mirrors the player and character branches of CardPreview.vue. */
    .player-card .card-head { background: #1e3a5f; }
    .player-card .omen { background: #334e68; }
    .character-card .card-head { background: #3f2b56; }

    .omen-pips {
        display: flex;
        align-items: center;
        gap: 0.4mm;
        padding-left: 1.5mm;
        font-size: 8pt;
    }

    .pip-mark { font-size: 0.8em; margin-left: 0.4mm; }

    .shop-cost {
        font-size: 5.5pt;
        font-weight: 600;
        background: #fef3c7;
        color: #78350f;
        border-radius: 1mm;
        padding: 0.3mm 1mm;
    }

    .start-zone { font-size: 5.5pt; color: #78716c; margin-left: auto; font-weight: 600; }

    /* A Hireling's two numbers. Mirrors .card-uses and .card-sacrifice in
       resources/js/Components/CardPreview.vue. */
    .uses {
        flex: 0 0 8mm;
        display: flex;
        align-items: center;
        justify-content: center;
        font-size: 8.5pt;
        font-weight: 700;
        background: #115e59;
        color: #fdfcf9;
        border-left: 0.25mm solid #fdfcf9;
        text-align: center;
        line-height: 1.05;
        padding: 0 0.5mm;
    }

    .sacrifice {
        display: inline-flex;
        align-items: center;
        gap: 0.5mm;
        font-size: 5.5pt;
        font-weight: 600;
        background: #fee2e2;
        color: #7f1d1d;
        border-radius: 1mm;
        padding: 0.3mm 1mm;
    }

    .back {
        position: absolute;
        inset: 0;
        display: flex;
        align-items: center;
        justify-content: center;
        background: #1c1917;
        color: #fdfcf9;
    }

    .back-inner {
        position: absolute;
        top: {{ $bleed }}mm; right: {{ $bleed }}mm; bottom: {{ $bleed }}mm; left: {{ $bleed }}mm;
        border: 0.4mm solid #57534e;
        border-radius: {{ $radius }}mm;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        gap: 2mm;
        text-align: center;
        padding: 4mm;
    }

    .back-mark { font-size: 20pt; line-height: 1; }
    .back-name {
        font-family: Georgia, "Times New Roman", serif;
        font-size: 8pt;
        letter-spacing: 0.18em;
        text-transform: uppercase;
        color: #d6d3d1;
    }

    /*
     * Icons are inline SVG so they survive being rendered from file:// for the
     * PDF. Mirrors .icon in resources/css/app.css.
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

    .sheet-note {
        max-width: {{ $sheet['w'] }}mm;
        margin: 6mm auto 2mm;
        color: #fafaf9;
        font-size: 10pt;
    }

    .sheet-note .warn { color: #fca5a5; font-weight: 600; }

    @media print {
        body { background: #fff; }
        .page { margin: 0; }
        .sheet-note { display: none; }
    }
</style>
</head>
<body>

<div class="sheet-note">
    {{ $scenario->name }} — {{ \App\Support\PrintOptions::DECKS[$options->deck] ?? $options->deck }} ·
    {{ $cardCount }} cards · {{ $cols }}×{{ $rows }} per sheet · {{ count($pages) }} {{ \Illuminate\Support\Str::plural('sheet', count($pages)) }}
    @if ($skipped > 0)
        · first {{ $skipped }} {{ \Illuminate\Support\Str::plural('cell', $skipped) }} left blank
    @endif
    @if ($omitted > 0)
        · {{ $omitted }} {{ \Illuminate\Support\Str::plural('card', $omitted) }} left out of this run
    @endif
    @if ($options->overflows())
        <span class="warn">· the cards do not fit inside this sheet, reduce the margin or the card size</span>
    @endif
</div>

@forelse ($pages as $page)
    <div class="page">
        @if ($options->cropMarks)
            @foreach ($verticalTrims as $x)
                <div class="crop crop-v" style="left: {{ $x }}mm; top: 0.5mm; height: {{ $markTop }}mm;"></div>
                <div class="crop crop-v" style="left: {{ $x }}mm; bottom: 0.5mm; height: {{ $markBottom }}mm;"></div>
            @endforeach
            @foreach ($horizontalTrims as $y)
                <div class="crop crop-h" style="top: {{ $y }}mm; left: 0.5mm; width: {{ $markLeft }}mm;"></div>
                <div class="crop crop-h" style="top: {{ $y }}mm; right: 0.5mm; width: {{ $markRight }}mm;"></div>
            @endforeach
        @endif

        <div class="grid">
            @foreach ($page as $card)
                {{-- A null cell is a label that has already been peeled off:
                     it prints as a blank so the rest stay where they belong. --}}
                <div class="cell">
                    @if ($card)
                        @include('print.partials.card', ['card' => $card, 'options' => $options])
                    @endif
                </div>
            @endforeach
        </div>
    </div>

    @if ($options->backs)
        <div class="page">
            <div class="grid">
                @foreach ($mirror($page) as $card)
                    <div class="cell">
                        @if ($card)
                        <div class="back">
                            <div class="back-inner">
                                <div class="back-mark">◆</div>
                                <div class="back-name">{{ $scenario->name }}</div>
                            </div>
                        </div>
                        @endif
                    </div>
                @endforeach
            </div>
        </div>
    @endif
@empty
    <div class="page">
        <p style="font-size: 11pt; color: #78716c;">
            @if ($omitted > 0)
                Nothing to print. Every card in this deck was left out of the run.
            @else
                Nothing to print. This deck has no cards yet.
            @endif
        </p>
    </div>
@endforelse

</body>
</html>
