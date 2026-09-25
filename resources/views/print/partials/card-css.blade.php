@php
    /*
     * The card design, in millimetres, for whatever sizes the card slot is
     * given. Included by both pages that draw a card: the print sheet
     * (resources/views/print/sheet.blade.php) and the pocket page
     * (resources/views/pocket.blade.php).
     *
     * It lives here rather than in either page because a card has one design
     * and the codebase already carries two implementations of it — this CSS
     * and resources/js/Components/CardPreview.vue, which must be kept in step.
     * A third copy, in a second stylesheet, is the copy that would quietly
     * drift. The rules that surround a card are the including page's: the grid
     * and the crop marks belong to the sheet, the slot and its zoom to the
     * pocket page, and neither is in here.
     *
     * $bleed and $radius are the only measurements it takes, both in mm, and
     * both come from App\Support\PrintOptions.
     */
@endphp
    .card {
        position: absolute;
        inset: 0;
        border-radius: {{ $bleed > 0 ? 0 : $radius }}mm;
        overflow: hidden;
        background: #fdfcf9;
        /* The card carries its own ink as well as its own paper: it is drawn
           on a dark page as well as on a white one, and cream paper with the
           page's light text on it is a card nobody can read. */
        color: #1c1917;
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

    /*
     * A chip holding an equation rather than a number: "1 + 1 [icon]" needs the
     * room a single digit does not. The chip widens and the type drops rather
     * than the equation wrapping into the card name beside it. Mirrors
     * .card-omen.scaled and .card-health.scaled in resources/css/app.css.
     */
    .omen.scaled, .health.scaled {
        flex: 0 0 auto;
        min-width: 8mm;
        padding: 0 1.2mm;
        font-size: 7pt;
        white-space: nowrap;
    }
    .markup-config { font-weight: 700; }
    .markup-missing { color: #b91c1c; font-weight: 700; }
    /* A designer-defined keyword. Mirrored by .markup-keyword in resources/css/app.css. */
    .markup-keyword { font-variant-caps: small-caps; font-weight: 600; letter-spacing: .01em; }
