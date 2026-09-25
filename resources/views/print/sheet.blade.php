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

    /*
     * The card design itself, shared with every other page that draws a card:
     * see resources/views/print/partials/card-css.blade.php for why it lives
     * in a partial rather than here.
     */
    @include('print.partials.card-css', ['bleed' => $bleed, 'radius' => $radius])

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
    @if (($offset ?? 0) > 0)
        · starting at card {{ $offset + 1 }} of {{ $runTotal }}, the first {{ $offset }} already printed
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
                                <div class="back-name">{{ $backName ?? $scenario->name }}</div>
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
            @if (($offset ?? 0) > 0)
                Nothing to print. The offset starts the run after its last card.
            @elseif ($omitted > 0)
                Nothing to print. Every card in this deck was left out of the run.
            @else
                Nothing to print. This deck has no cards yet.
            @endif
        </p>
    </div>
@endforelse

</body>
</html>
