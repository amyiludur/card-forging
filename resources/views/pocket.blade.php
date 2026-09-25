@php
    /** @var \App\Support\PrintOptions $options */
    use Illuminate\Support\Str;

    // Card slots are the card's real size in millimetres, the same measurement
    // the print sheet lays out, and the zoom below is what makes one readable
    // on a phone. Nothing here changes the card: it is the printed card, held
    // closer.
    $cardW = $options->cardWidth();
    $cardH = $options->cardHeight();
@endphp
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="color-scheme" content="dark light">
<title>{{ $title }}</title>
<style>
    /*
     * One file, no requests. This page is read on a phone with no server to
     * reach and possibly no network at all, so there is no stylesheet link, no
     * webfont and no script src anywhere in it — the same rule the print sheet
     * follows for headless Chromium, for a different reason. There is a test
     * asserting it, which is also why this note names neither tag.
     *
     * The card faces are the print sheet's own partial and its own CSS
     * (resources/views/print/partials/card-css.blade.php). Everything in here
     * is the page around them: the bar, the panels, the slots and the prose.
     */
    :root {
        --ink: #e7e5e4;
        --ink-dim: #a8a29e;
        --ink-faint: #78716c;
        --bg: #1c1917;
        --bg-raised: #292524;
        --bg-bar: #171310;
        --line: #3f3a36;
        --gold: #d6b24c;
        --warn-bg: #fde68a;
        --warn-ink: #78350f;
        /* Overwritten by the size control, which fits whole cards to the
           screen. This is what a phone with no script still gets. */
        --zoom: 1.4;
        --pad: 14px;
    }

    * { box-sizing: border-box; }

    html {
        -webkit-text-size-adjust: 100%;
        /* Enough that a heading jumped to lands under the bar and not behind it. */
        scroll-padding-top: 7.5rem;
    }

    body {
        margin: 0;
        padding: 0 0 4rem;
        background: var(--bg);
        color: var(--ink);
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif;
        font-size: 16px;
        line-height: 1.5;
    }

    [hidden] { display: none !important; }

    a { color: var(--gold); }

    /* The bar: what the page is, and the two ways into it. It sticks, because
       a phone reading the rules wants the cards one tap away. */
    .bar {
        position: sticky;
        top: 0;
        z-index: 10;
        background: var(--bg-bar);
        border-bottom: 1px solid var(--line);
        padding: max(env(safe-area-inset-top, 0px), 8px) var(--pad) 8px;
    }

    .bar-title {
        font-family: Georgia, "Times New Roman", serif;
        font-size: 1.05rem;
        font-weight: 600;
        letter-spacing: 0.01em;
        margin: 0;
    }

    .bar-note {
        font-size: 0.72rem;
        color: var(--ink-faint);
        margin: 0.15rem 0 0;
    }

    .tabs { display: flex; gap: 6px; margin-top: 8px; }

    .tab {
        flex: 1;
        appearance: none;
        font: inherit;
        font-size: 0.9rem;
        font-weight: 600;
        color: var(--ink-dim);
        background: var(--bg-raised);
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 9px 10px;
        cursor: pointer;
    }

    .tab[aria-selected="true"] {
        color: #1c1917;
        background: var(--gold);
        border-color: var(--gold);
    }

    .tab-count { font-weight: 400; opacity: 0.75; }

    /* The tools the cards panel needs: find a card, and set how big a card is.
       They live in the bar so both are reachable while scrolling a deck. */
    .tools { display: flex; gap: 6px; margin-top: 8px; align-items: center; }

    .find {
        flex: 1;
        min-width: 0;
        font: inherit;
        font-size: 0.9rem;
        color: var(--ink);
        background: var(--bg-raised);
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 9px 10px;
    }

    .find::placeholder { color: var(--ink-faint); }

    .sizes { display: flex; gap: 4px; }

    .size {
        appearance: none;
        font: inherit;
        font-size: 0.78rem;
        font-weight: 700;
        color: var(--ink-dim);
        background: var(--bg-raised);
        border: 1px solid var(--line);
        border-radius: 8px;
        padding: 9px 11px;
        cursor: pointer;
    }

    .size[aria-pressed="true"] { color: #1c1917; background: var(--ink-dim); border-color: var(--ink-dim); }

    main { padding: var(--pad); }

    /* A jump list: the documents, or the owners of the cards. */
    .jump { display: flex; flex-wrap: wrap; gap: 6px; margin: 0 0 1.25rem; padding: 0; list-style: none; }

    .jump a {
        display: inline-block;
        font-size: 0.8rem;
        text-decoration: none;
        color: var(--ink-dim);
        background: var(--bg-raised);
        border: 1px solid var(--line);
        border-radius: 999px;
        padding: 5px 11px;
    }

    .jump .jump-count { color: var(--ink-faint); }

    .section { margin: 0 0 2.5rem; }

    /* Not sticky: the bar above it already is, and two things stuck to the top
       of a phone screen is one of them hidden behind the other. */
    .section-head {
        background: var(--bg);
        padding: 0.4rem 0 0.5rem;
        border-bottom: 1px solid var(--line);
        margin-bottom: 1rem;
    }

    .section-kind {
        font-size: 0.68rem;
        font-weight: 700;
        letter-spacing: 0.14em;
        text-transform: uppercase;
        color: var(--gold);
    }

    .section-title {
        font-family: Georgia, "Times New Roman", serif;
        font-size: 1.35rem;
        font-weight: 600;
        margin: 0.1rem 0 0;
    }

    .section-note { font-size: 0.8rem; color: var(--ink-faint); margin: 0.2rem 0 0; }

    .group { margin-bottom: 1.75rem; }

    .group-title {
        font-size: 0.72rem;
        font-weight: 700;
        letter-spacing: 0.12em;
        text-transform: uppercase;
        color: var(--ink-dim);
        margin: 0 0 0.6rem;
    }

    /*
     * The deck of slots. A slot is one card at its real size, zoomed: the CSS
     * zoom property scales the layout box as well as the drawing, so the slot
     * takes the room the zoomed card needs and the row wraps on its own. The
     * size control works the zoom out from the screen width, so a whole number
     * of whole cards fits across and the page never scrolls sideways.
     */
    .deck { display: flex; flex-wrap: wrap; gap: 14px; }

    .slot { width: max-content; }

    .cell {
        position: relative;
        width: {{ $cardW }}mm;
        height: {{ $cardH }}mm;
        zoom: var(--zoom);
    }

    /* Under the card: what the card face does not say. The name is on the card;
       this is where it lives and how many of it a deck calls for. */
    .slot-meta {
        margin-top: 5px;
        font-size: 0.72rem;
        line-height: 1.35;
        color: var(--ink-faint);
        display: flex;
        flex-wrap: wrap;
        gap: 4px 7px;
        align-items: baseline;
    }

    .slot-qty { color: var(--ink-dim); font-weight: 700; }

    .empty { color: var(--ink-faint); font-style: italic; margin: 0; }

    /*
     * The rules. Third stylesheet for .rules-prose, after resources/css/app.css
     * (the editor) and the inline CSS of resources/views/print/rulebook.blade.php
     * (the printed book) — App\Support\RulesMarkdown emits no classes of its
     * own, so there is nothing here that can disagree with either of them about
     * what the markdown means. It is only that a phone is not paper: the measure
     * is one column, the type is bigger, and nothing avoids a page break.
     */
    .rules-prose { font-size: 1rem; line-height: 1.6; }

    .rules-prose h1,
    .rules-prose h2,
    .rules-prose h3,
    .rules-prose h4,
    .rules-prose h5,
    .rules-prose h6 {
        font-family: Georgia, "Times New Roman", serif;
        font-weight: 600;
        line-height: 1.25;
        margin: 1.6em 0 0.5em;
    }

    .rules-prose h1 {
        font-size: 1.45rem;
        margin-top: 2.2em;
        padding-bottom: 0.3em;
        border-bottom: 1px solid var(--line);
        color: #fafaf9;
    }

    .document:first-of-type .rules-prose h1 { margin-top: 0; }

    .rules-prose h2 { font-size: 1.18rem; color: var(--gold); }
    .rules-prose h3 { font-size: 1.02rem; }
    .rules-prose h4, .rules-prose h5, .rules-prose h6 { font-size: 0.95rem; color: var(--ink-dim); }

    .rules-prose p { margin: 0 0 0.8em; }
    .rules-prose ul, .rules-prose ol { margin: 0 0 0.9em; padding-left: 1.3em; }
    .rules-prose li { margin-bottom: 0.35em; }
    .rules-prose li > ul, .rules-prose li > ol { margin: 0.35em 0 0; }
    .rules-prose hr { border: 0; border-top: 1px solid var(--line); margin: 1.5em 0; }
    .rules-prose strong { color: #fafaf9; }
    .rules-prose em { color: var(--ink-dim); }

    /* A table is the one thing here allowed to be wider than the screen, so it
       scrolls inside itself rather than pushing the page sideways. Its own box
       is the scroller, because RulesMarkdown emits no wrapper to be one. */
    .rules-prose table {
        display: block;
        width: max-content;
        max-width: 100%;
        overflow-x: auto;
        border-collapse: collapse;
        font-size: 0.88rem;
        margin: 0 0 1em;
    }

    .rules-prose th {
        text-align: left;
        vertical-align: top;
        font-weight: 700;
        color: #fafaf9;
        padding: 0.35em 0.7em 0.35em 0;
        border-bottom: 1px solid var(--ink-faint);
    }

    .rules-prose td {
        vertical-align: top;
        padding: 0.35em 0.7em 0.35em 0;
        border-bottom: 1px solid var(--line);
    }

    .rules-prose code {
        font-family: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", monospace;
        font-size: 0.85em;
        background: var(--bg-raised);
        border-radius: 3px;
        padding: 0.1em 0.3em;
    }

    /*
     * The two appendices, and the flag every placeholder in them carries: the
     * numbers and the keywords are the designer's own, listed, and a number
     * that has not been decided says so wherever it is read.
     */
    .appendix { margin-top: 2.5rem; }

    .appendix-note { font-size: 0.85rem; font-style: italic; color: var(--ink-faint); margin: 0 0 1em; }

    .flag {
        display: inline-block;
        font-size: 0.62rem;
        font-weight: 700;
        letter-spacing: 0.08em;
        text-transform: uppercase;
        background: var(--warn-bg);
        color: var(--warn-ink);
        border-radius: 3px;
        padding: 0 4px;
        white-space: nowrap;
        vertical-align: 0.1em;
    }

    .appendix-key {
        font-family: ui-monospace, SFMono-Regular, Consolas, "Liberation Mono", monospace;
        font-size: 0.8rem;
        color: var(--ink-faint);
    }

    .appendix-meaning { color: var(--ink-faint); font-size: 0.85rem; }

    /*
     * What the rules are being read against: how old this page is, and where
     * the game itself lives. A page carried to a table should say which it is.
     */
    .foot {
        margin-top: 3rem;
        padding-top: 1rem;
        border-top: 1px solid var(--line);
        font-size: 0.78rem;
        line-height: 1.6;
        color: var(--ink-faint);
    }

    .foot .warn { color: #fbbf24; }

    /* The card design, drawn exactly as the print sheet draws it. */
    @include('print.partials.card-css', ['bleed' => $options->bleed, 'radius' => $options->cornerRadius])

    /*
     * The two markup colours the card CSS sets for paper, said again for a dark
     * page. Nothing else about the markup changes: a keyword is still small
     * caps, an icon is still the same inline SVG.
     */
    .rules-prose .markup-missing { color: #fca5a5; }
    .markup-keyword-placeholder { color: #b45309; }
    .rules-prose .markup-keyword-placeholder { color: #fbbf24; }
</style>
</head>
<body>

<header class="bar">
    <h1 class="bar-title">{{ $title }}</h1>
    <p class="bar-note">{{ $builtAt }} · {{ $counts['documents'] }} {{ Str::plural('document', $counts['documents']) }} · {{ $counts['cards'] }} {{ Str::plural('card', $counts['cards']) }}</p>

    <div class="tabs" role="tablist">
        <button class="tab" type="button" role="tab" id="tab-rules" aria-controls="panel-rules" aria-selected="true">Rules</button>
        <button class="tab" type="button" role="tab" id="tab-cards" aria-controls="panel-cards" aria-selected="false">
            Cards <span class="tab-count">{{ $counts['cards'] }}</span>
        </button>
    </div>

    {{-- Only the cards are searched: the rules are read, and a phone browser
         already finds a word in a page of text better than a filter would. --}}
    <div class="tools" id="tools" hidden>
        <input class="find" id="find" type="search" inputmode="search" autocomplete="off"
               placeholder="Find a card by name or text" aria-label="Find a card">
        <div class="sizes" role="group" aria-label="Card size">
            <button class="size" type="button" data-per-row="1" aria-pressed="false">1</button>
            <button class="size" type="button" data-per-row="2" aria-pressed="false">2</button>
            <button class="size" type="button" data-per-row="3" aria-pressed="false">3</button>
        </div>
    </div>
</header>

<main>

<div id="panel-rules" role="tabpanel" aria-labelledby="tab-rules">
    @if ($documents === [])
        <p class="empty">There are no rules documents yet.</p>
    @else
        <ul class="jump">
            @foreach ($documents as $document)
                <li><a href="#{{ $document['slug'] }}-doc">{{ $document['title'] }}</a></li>
            @endforeach
            <li><a href="#appendix-numbers">Numbers</a></li>
            <li><a href="#appendix-keywords">Keywords</a></li>
        </ul>

        @foreach ($documents as $document)
            <section class="document" id="{{ $document['slug'] }}-doc">
                <div class="rules-prose">{!! $document['html'] !!}</div>
            </section>
        @endforeach
    @endif

    <section class="appendix rules-prose" id="appendix-numbers">
        <h1>Tunable numbers</h1>
        <p class="appendix-note">
            The numbers the rules above read through <code>{config:…}</code>, as they stand today. A number flagged as
            a placeholder has not been decided.
        </p>
        <table>
            <thead><tr><th>Number</th><th>Value</th><th>Key</th></tr></thead>
            <tbody>
            @foreach ($numbers as $row)
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

    <section class="appendix rules-prose" id="appendix-keywords">
        <h1>Keywords</h1>
        <p class="appendix-note">
            The designer's own keywords, as the cards print them. A keyword flagged as a placeholder is still being
            decided.
        </p>
        @if ($keywords === [])
            <p class="empty">No keywords have been written yet.</p>
        @else
            <table>
                <thead><tr><th>Keyword</th><th>What it means</th><th>Typed</th></tr></thead>
                <tbody>
                @foreach ($keywords as $row)
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
</div>

<div id="panel-cards" role="tabpanel" aria-labelledby="tab-cards" hidden>
    @if ($sections === [])
        <p class="empty">There are no cards yet.</p>
    @else
        <ul class="jump" id="jump-cards">
            @foreach ($sections as $section)
                <li data-jump="{{ $section['id'] }}">
                    <a href="#{{ $section['id'] }}">{{ $section['title'] }} <span class="jump-count">{{ $section['count'] }}</span></a>
                </li>
            @endforeach
        </ul>

        <p class="empty" id="no-matches" hidden>No card says that.</p>

        @foreach ($sections as $section)
            <section class="section" id="{{ $section['id'] }}" data-section>
                <div class="section-head">
                    <div class="section-kind">{{ $section['kind'] }}</div>
                    <h2 class="section-title">{{ $section['title'] }}</h2>
                    @if ($section['note'])
                        <p class="section-note">{{ $section['note'] }}</p>
                    @endif
                </div>

                @forelse ($section['groups'] as $group)
                    <div class="group" data-group>
                        <h3 class="group-title">{{ $group['title'] }} <span class="jump-count">{{ count($group['cards']) }}</span></h3>
                        <div class="deck">
                            @foreach ($group['cards'] as $entry)
                                <div class="slot" data-find="{{ $entry['find'] }}">
                                    <div class="cell">
                                        @include('print.partials.card', ['card' => $entry['card'], 'options' => $options])
                                    </div>
                                    <div class="slot-meta">
                                        @if ($entry['qty'] > 1)
                                            <span class="slot-qty">×{{ $entry['qty'] }}</span>
                                        @endif
                                        <span>{{ $entry['source'] }}</span>
                                    </div>
                                </div>
                            @endforeach
                        </div>
                    </div>
                @empty
                    <p class="empty">Named, and no cards written yet.</p>
                @endforelse
            </section>
        @endforeach
    @endif
</div>

</main>

<footer class="foot">
    <p>
        Built from <code>design/</code> on {{ $builtAt }}. The design folder is the source of truth; this page is a
        copy of it, so a card edited since then is edited in the editor and not here.
        @if ($placeholderNumbers !== [])
            <br><span class="warn">The rules above quote {{ count($placeholderNumbers) }} placeholder
            {{ Str::plural('number', count($placeholderNumbers)) }}: {{ implode(', ', $placeholderNumbers) }}.</span>
        @endif
    </p>
</footer>

<script>
/*
 * Everything the page does once it is open, inline for the same reason the CSS
 * is: there is nothing to fetch it from. It does three things — the two tabs,
 * the card filter, and fitting whole cards across whatever screen this is —
 * and the page is readable with all three of them switched off.
 */
(function () {
    'use strict';

    var root = document.documentElement;

    // A phone in a private window throws rather than returning null, and a
    // remembered tab is not worth a broken page.
    var remember = {
        get: function (key) { try { return localStorage.getItem(key); } catch (e) { return null; } },
        set: function (key, value) { try { localStorage.setItem(key, value); } catch (e) { /* nothing to do */ } }
    };

    /* ----- the two tabs ----- */

    var tabs = {
        rules: { tab: document.getElementById('tab-rules'), panel: document.getElementById('panel-rules') },
        cards: { tab: document.getElementById('tab-cards'), panel: document.getElementById('panel-cards') }
    };

    var tools = document.getElementById('tools');

    function show(name) {
        Object.keys(tabs).forEach(function (key) {
            var on = key === name;
            tabs[key].tab.setAttribute('aria-selected', on ? 'true' : 'false');
            tabs[key].panel.hidden = !on;
        });

        // The filter and the size buttons belong to the cards, so they go with
        // them rather than sitting dead above the rules.
        tools.hidden = name !== 'cards';
        remember.set('pocket.tab', name);

        if (name === 'cards') {
            fit();
        }
    }

    Object.keys(tabs).forEach(function (key) {
        tabs[key].tab.addEventListener('click', function () {
            show(key);
            window.scrollTo(0, 0);
        });
    });

    /* ----- fitting whole cards across the screen ----- */

    var CARD_MM = {{ $cardW }};
    // A CSS pixel is 1/96in by definition, so a card's width in pixels is not
    // a measurement anything has to take.
    var CARD_PX = CARD_MM * 96 / 25.4;
    var GAP = 14;

    var sizeButtons = Array.prototype.slice.call(document.querySelectorAll('.size'));
    var main = document.querySelector('main');
    var perRow = parseInt(remember.get('pocket.perRow'), 10) || 0;

    function room() {
        var style = window.getComputedStyle(main);

        return main.clientWidth - parseFloat(style.paddingLeft) - parseFloat(style.paddingRight);
    }

    function fit() {
        var available = room();

        if (!available || available < 0) {
            return;
        }

        // Nothing chosen yet: as many cards as the screen holds at a size worth
        // reading, which on a phone is one.
        var across = perRow || (available < 520 ? 1 : available < 900 ? 2 : 3);
        var zoom = (available - GAP * (across - 1)) / (across * CARD_PX);

        root.style.setProperty('--zoom', Math.max(0.6, Math.min(2.4, zoom)).toFixed(3));

        sizeButtons.forEach(function (button) {
            button.setAttribute('aria-pressed', parseInt(button.dataset.perRow, 10) === across ? 'true' : 'false');
        });
    }

    sizeButtons.forEach(function (button) {
        button.addEventListener('click', function () {
            perRow = parseInt(button.dataset.perRow, 10);
            remember.set('pocket.perRow', String(perRow));
            fit();
        });
    });

    window.addEventListener('resize', fit);
    window.addEventListener('orientationchange', fit);

    /* ----- finding a card ----- */

    var find = document.getElementById('find');
    var slots = Array.prototype.slice.call(document.querySelectorAll('.slot'));
    var groups = Array.prototype.slice.call(document.querySelectorAll('[data-group]'));
    var sections = Array.prototype.slice.call(document.querySelectorAll('[data-section]'));
    var jumps = Array.prototype.slice.call(document.querySelectorAll('#jump-cards [data-jump]'));
    var nothing = document.getElementById('no-matches');

    function filter() {
        // Every word has to be on the card, so "kraken tentacle" narrows rather
        // than widens. The words come off the rendered face, so a card is found
        // by what it says and not only by its name.
        var terms = find.value.toLowerCase().split(/\s+/).filter(Boolean);
        var showing = 0;

        slots.forEach(function (slot) {
            var text = slot.dataset.find || '';
            var match = terms.every(function (term) { return text.indexOf(term) !== -1; });

            slot.hidden = !match;

            if (match) {
                showing++;
            }
        });

        // A group or an owner with nothing left in it goes too, so the page is
        // the answer rather than a list of headings over empty space.
        groups.forEach(function (group) {
            group.hidden = !group.querySelector('.slot:not([hidden])');
        });

        sections.forEach(function (section) {
            section.hidden = terms.length > 0 && !section.querySelector('.slot:not([hidden])');
        });

        jumps.forEach(function (jump) {
            var section = document.getElementById(jump.dataset.jump);
            jump.hidden = section ? section.hidden : false;
        });

        if (nothing) {
            nothing.hidden = showing > 0 || terms.length === 0;
        }
    }

    if (find) {
        find.addEventListener('input', filter);
        // A phone that restores the last page keeps what was typed in the box.
        if (find.value) {
            filter();
        }
    }

    /* ----- open where it was left ----- */

    show(remember.get('pocket.tab') === 'cards' ? 'cards' : 'rules');
    fit();
})();
</script>

</body>
</html>
