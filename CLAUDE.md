# Card Forge — notes for Claude Code

This repository is the platform. `design/` is the game, and `design/CLAUDE.md` is the designer's
original brief — read it and the `design/rules/` files before changing anything about the game.

## The one rule

**This is the designer's game. You are building the tool.** Do not resolve an open question from
`design/rules/05-decisions-and-open-questions.md` on your own, and do not quietly turn a
*(placeholder)* number into a real one. Ask.

## Stack

Laravel 11, Vue 3, Inertia 3, Tailwind, SQLite in development. No auth: it is a single-designer
local tool. Chosen to match the designer's existing **CardForge** scaffold so the two can merge
later. The designer runs PHP 8.5, so the suite is run on 8.4 and 8.5 before anything ships.

**8.4 is the floor, not 8.2.** The Symfony 8.1 components in `composer.lock` require `>= 8.4.1`,
so the lock has not been installable on 8.2 or 8.3 since that upgrade; `composer.json` now says
`^8.4` to match. Going back to 8.2 would mean pinning Symfony 7, which is a bigger change than it
sounds — ask before doing it.

## Running it

`./setup` then `composer dev`. Both are tested. There is also a `Dockerfile` and
`docker-compose.yml`, which have **never been built** — they were written in a sandbox with no
access to Debian's package repositories. Treat them as unverified until someone runs
`docker compose up` for real.

## Where things live

| Path | What |
|---|---|
| `design/` | the rules markdown and scenario JSON — the source of truth, tracked by git |
| `app/Console/Commands/ImportDesign.php` | `design:import`, design/ → database |
| `app/Console/Commands/ExportDesign.php` | `design:export`, database → design/ |
| `app/Support/DesignFolder.php` | those two from a button, and what git says about the result |
| `resources/js/Pages/Design/Index.vue` | `/design`: import, export, and the commit that follows |
| `app/Support/Icons.php` | **generated** — every icon as an SVG path, from Font Awesome |
| `build/icons.mjs` | the icon map; edit it and run `npm run icons` |
| `app/Support/Colour.php` | a picked colour → a head band, its ink and a readable type line |
| `resources/js/colour.js` | the same colour rules in the browser — **keep these two in step** |
| `app/Support/Markup.php` | the `{omen}` / `{unique}` / `{config:key}` markup, server side |
| `app/Support/PlayerScaled.php` | a number written as an equation counting the players |
| `resources/js/playerScaled.js` | the same equations in the browser — **keep these two in step** |
| `resources/js/Components/ScaledNumberField.vue` | the number ⇄ equation toggle in every editor |
| `resources/js/Components/ScaledValue.vue` | one of those numbers shown the way the card shows it |
| `app/Models/Keyword.php` | the keyword library: the designer's own `{token}`s |
| `app/Models/CardType.php` | the card types: the shared library and a scenario's own |
| `resources/js/markup.js` | the same markup in the browser — **keep these two in step** |
| `app/Support/CardPresenter.php` | the one card shape used by the editor, preview and print |
| `app/Support/PrintOptions.php` | sheet and card geometry, all in millimetres |
| `app/Support/PrintSelection.php` | which cards of the chosen deck actually go on the sheet |
| `app/Support/PrintCatalogue.php` | every printable card under one `group:id` key, for the print pool |
| `app/Http/Controllers/PrintPoolController.php` | `/print/pool`: cards from anywhere on one run |
| `app/Http/Controllers/Concerns/PrintsItems.php` | the sheet, options page and PDF every print page shares |
| `app/Support/Storyline.php` | the arrow rule: which half of each split card resolves |
| `app/Support/DeckAssembly.php` | a scenario's base deck plus the modules chosen for a play |
| `app/Support/PlayerDeck.php` | a character's cards against the deck rules, and what does not add up |
| `app/Support/DeckBuild.php` | a deck being built: a character, a domain, and what is taken from it |
| `app/Support/DomainPool.php` | the same for a domain: the shared half of a deck |
| `app/Support/CardStats.php` | the counting, curves and pair checks both of those share |
| `app/Support/RulesMarkdown.php` | the rulebook's markdown — headings, lists, tables — server side |
| `resources/js/rulesMarkdown.js` | the same markdown in the browser — **keep these two in step** |
| `app/Support/RulebookOptions.php` | the printed rulebook's page: sheet, margins, columns, text size |
| `app/Support/PdfRenderer.php` | one HTML page to a PDF, through headless Chromium |
| `resources/views/print/` | the print sheet, inline CSS so it renders from `file://` for the PDF |
| `resources/views/print/rulebook.blade.php` | the printed rulebook, same inline-CSS rule as the sheet |
| `resources/views/print/partials/card-css.blade.php` | the card design's CSS — **shared** by the sheet and the pocket page |
| `app/Support/RulesAppendices.php` | the tunable numbers and the keyword glossary, for every page that lists them |
| `app/Support/Pocket.php` | the pocket page: every rule and every card, grouped by owner |
| `resources/views/pocket.blade.php` | that page — one file, inline everything, readable on a phone |
| `app/Console/Commands/BuildPocket.php` | `pocket:build`, which writes it to a file |
| `.github/workflows/pocket.yml` | builds it from `design/` and publishes it to GitHub Pages |
| `resources/js/Components/CardPreview.vue` | the on-screen card — mirrors the print partial |
| `resources/js/Components/CardZoom.vue` | the full-size card overlay, driven by `useCardZoom.js` |
| `resources/js/Components/Icon.vue` | `<Icon name="omen" />`, drawing the paths the server shares |
| `resources/js/Pages/Scenarios/Play.vue` | the solo playtest table: reveal, storyline, Dread, beats, health |
| `resources/js/omenReveal.js` | the reveal step: how many cards one omen pool brings out |

## Things that will bite

- **Icons are inline SVG, never a webfont or an icon stylesheet.** The print sheet is rendered from
  `file://` by headless Chromium, where a `<link>` or an `@font-face` URL does not load, so a
  webfont would print empty boxes. `app/Support/Icons.php` is generated from the Font Awesome
  package by `build/icons.mjs`: to add or change an icon, edit the map there and run
  `npm run icons` — don't hand-edit the PHP. The same paths reach the browser through Inertia's
  `markup.paths` prop, so `Icon.vue` and `Icons::svg()` cannot drift. There is a test asserting the
  sheet carries no `<link>` and no `@font-face`. The pocket page has the same test for its own
  reason: it is read on a phone with nothing to fetch from.
- **The card's CSS is one file now, and two pages include it.** `resources/views/print/partials/card-css.blade.php`
  holds the card design itself; the print sheet and the pocket page each include it and add their own
  surroundings. So a change to `.card-head` or `.effect` lands on both, which is the point — and
  anything about the *sheet* (the grid, the crop marks, the paper) belongs in `sheet.blade.php`,
  not in the partial. It takes `$bleed` and `$radius` and nothing else, both in mm, both from
  `PrintOptions`; the pocket page passes zero bleed, because nothing is being cut out. The card
  design still has two implementations — this CSS and `CardPreview.vue` — and they still have to be
  kept in step. A third copy was the thing worth avoiding.
- **`{perPlayer}` is a sixth icon, and camelCase for the same reason `{dreadRule}` is.** The token
  regex is lowercase only, so a camelCase token can never collide with a keyword the designer names
  — which is why `{perPlayer}` gets its own pattern in **both** halves rather than going through the
  token pass. It is in `Markup::ICONS` like the other five, but its fallback is the words *per
  player* rather than a symbol: it stands for a count, not a thing, so `toPlain()` has to leave
  something readable in a design-folder diff. Report, don't correct: the editor still refuses a
  keyword named after it.
- **The five icon tokens are code; every other `{token}` is data.** `Markup::ICONS` holds the game's
  own symbols and stays in code, because they are drawn from `Icons` and the print sheet depends on
  them. Everything else a card says in one word — Unique, Fired, Bottom draw — is a row in
  `keywords`, edited at `/rules/keywords`, so adding one needs no release. An icon token always wins
  over a keyword of the same name, and the editor will not let a keyword take one of those names.
  A token is `[a-z][a-z0-9-]*`, so `{bottom-draw}` is a keyword like any other — that is why the
  token regex in **both** halves of the markup is no longer `[a-z]+`.
- **`{dreadRule}` is neither of those: one scenario's sentence, not a symbol and not a number.**
  It writes the scenario's `dread_effect` onto a card that belongs to that scenario, so the rule is
  written once and quoted. camelCase like a config key and for the same reason — it names a field,
  not a symbol — which also means it can never collide with a keyword, because the token regex is
  lowercase only. **The rule is substituted into the text before anything else runs**, in both
  halves, so its own icons, keywords and numbers render like any other card text and a `<br>` in it
  behaves; the replacement is never rescanned, so a rule that says `{dreadRule}` is reported rather
  than looped on. Resolved **per card, off the card's own scenario** (`CardPresenter`), not per
  page: a list mixing scenarios still gets each card right. A module card has no scenario — a module
  is played with whichever scenario the table chose — so it prints `?dreadRule`, and so does a
  scenario whose Dread effect is not written yet. Report, don't correct. The browser re-renders card
  text rather than using the server's `html`, so the rule travels with the card as `dread_rule` and
  `CardPreview` passes it into `renderMarkup`; change one half of `expandDreadRule` and change the
  other. `toPlain()` writes the rule out but leaves an unfilled token as typed — there is no red
  span in a design-folder diff — and nothing expands on the way to disk, so `design:export` still
  writes `{dreadRule}`.
- **`{dreadAmount}` is the same trick for the scenario's number.** It writes the scenario's starting
  Dread onto a card that belongs to it, camelCase and resolved per card off the card's own scenario,
  exactly like `{dreadRule}` — a module card prints `?dreadAmount`, and so does a scenario whose
  number is somehow empty. What goes in is `Scenario::startingDread()->markup()`, so an equation
  counting the players arrives as `1 + 1{perPlayer}` and draws the icon through the pass that
  already exists: **a card prints the equation, never a figure**, the same as every other scaled
  number. The rule is expanded **before** the number, so a `dread_effect` quoting `{dreadAmount}`
  carries it onto every card that quotes the rule. `CardPresenter::dreadOf()` is the one place the
  pair is resolved and `dreadProps()` the one place it travels to the browser, so a card can never
  be given the rule and not the number; `Markup::withDread()` takes both for the same reason.
- **`{this}` is the card's own name.** Resolved per card in `CardPresenter` (`Markup::withName()`)
  and off `card.name` in `CardPreview`, so renaming a card renames it everywhere its text says
  `{this}`. It is expanded **after** the Dread pair, so a `dread_effect` saying `{this}` names each
  card it is quoted on. Unlike `{dreadRule}` it is lowercase, so it shares the keyword namespace:
  `Markup::reservedTokens()` is what stops a keyword being called `this`, and the keyword editor
  reads it. Text on no card, or a card not named yet, prints the red `?this`; `toPlain()` leaves an
  unfilled one as typed, and `design:export` writes `{this}`, never the name. Two halves, as ever.
- **A number can be an equation counting the players, and the equation is the value.** `PlayerScaled`
  (and `resources/js/playerScaled.js`, the other half) reads `1 + 1perPlayer`, `2 * 1perPlayer`,
  `3 + 2(perPlayer)` — whole numbers, `perPlayer`, `+ - *`, brackets, and a number next to a bracket
  or the count meaning multiplication. A recursive descent parser, never `eval()`, because it runs on
  text the designer types. **There is no division**, because a rounding rule would be a decision
  about the game rather than about the tool; a `/` is reported as something the equation cannot use.
  The lowercase spelling reads the same as the camelCase one and the stored text keeps whatever was
  typed — only what is drawn is made canonical. The two halves were last confirmed to agree on 611
  generated equations, the same way the colour rules were.
- **A printed card prints the equation, never a figure.** It cannot know how many people are at the
  table, so `1 + 1{perPlayer}` is what the setup card, the character card and the beat card carry —
  `CardPresenter::scaled()` builds it, `renderScaled()` in `CardPreview.vue` builds the same thing,
  two copies of one rule. **`/scenarios/{slug}/play` is the one place a player count exists**, since
  the playtest table already knows who is out on the table, so it is the one place an equation
  becomes a number: the dials seed against the party a game starts with and the notes beside them say
  what the equation comes to at that size. Adding someone mid-game never re-seeds a dial — the table
  keeps counters, it does not run the game.
- **The number beside the equation is kept, not overwritten.** `starting_dread_equation`,
  `dread_change_equation` and the character's three are nullable columns beside the integers they
  scale; null is the whole of "this is a plain number". The integer is what the editor's toggle puts
  back, so turning an equation off gives the designer what they had. A tunable number has no second
  column to keep, so turning one off there leaves 0 — `RulesConfig::typeFor()` is the one place that
  decides whether a value is an `int` or an `equation`, and a string only becomes an equation by
  naming the player count, which is how `"deck-bottom"` and `"top"` stay strings.
- **A design file holds one key, a number or an equation**, the way board card health has held either
  since v1: `"startingDread": 2` or `"startingDread": "1 + 1perPlayer"`. So a folder nobody has scaled
  comes back out byte for byte, the same rule a colour and a Hireling's two numbers follow. Board card
  health needs none of this and got none: it was already free text.
- **A keyword renders through CSS classes, not Tailwind utilities**, because the server and the
  browser both emit it: `Markup::keywordHtml()` and `keywordHtml()` in `resources/js/markup.js` build
  the same span, and `.markup-keyword` is defined in `resources/css/app.css` and again in the print
  sheet's inline CSS. Change one of those four and change the others. `.markup-missing` — the red
  `?key` a `{config:…}` or a `{dreadRule}` nothing filled in prints — lives in both stylesheets for
  the same reason.
- **A card type's colour is the designer's; everything derived from it is the tool's.** The colour
  fills the card's **head band** as picked, and the **type line** under it takes the same colour
  darkened only as far as it has to be to read on the cream body — `Colour::onPaper()`, which stops
  at the first step that clears WCAG AA rather than going to black, so as much of the hue survives
  as can. The ink over the band is picked the same way, so a pale type flips the head to dark text.
  `app/Support/Colour.php` and `resources/js/colour.js` are the two halves — the print sheet builds
  the style server side and the preview in the browser, because the editor has to show a colour the
  moment it is picked. Change one and change the other; a 300-colour cross-check is how they were
  last confirmed to agree.
- **A split card's head carries both its halves' colours, top colour at the top.** Two different
  type colours make a `to bottom` gradient, and because no one ink reads over both stops the card
  name gets a halo of the opposite ink — the same trick `.arrow-edge` already uses. Two halves of
  one type, or one half coloured and the other not, stay a flat band. `headStyle` in
  `CardPreview.vue` and the `@php` block at the top of `print/partials/card.blade.php`.
- **The chips in the head keep their own ink.** `.card-omen`, `.card-health` and `.card-uses` (and
  `.omen`, `.health`, `.uses` in the print sheet) carry dark backgrounds of their own, so they set
  `color: #fdfcf9` explicitly rather than inheriting from a head band that may now be pale.
- **A card type belongs to the shared library or to one scenario, never both.** `scenario_id` is
  nullable on `card_types`: null is the shared library every scenario and module draws on, and a set
  one is that scenario's own — the Kraken's Tide. `CardType::for($scenario)` is what everything asks
  for the offerable set, and a module has no scenario so it gets the shared library alone, for the
  same reason it prints no `{dreadRule}`. **Slugs are unique across the whole table**, shared or
  owned, so a design file's `"type": "tide"` and a `?type=tide` filter can only mean one thing; a
  name already taken gets a numbered slug and the flash message says which. Deleting a scenario
  takes its own types with it.
- **A card keeps a type it already carries, even one that is not offered to it.** A design file can
  type a Wendigo card with the Kraken's Tide. The editor offers it anyway, marked with whose it is,
  and saves it back unchanged — report, don't correct. What the editor will not do is hand out a new
  one: `offerableTypeIds()` is the whole of that rule.
- **A character's two colours are one gradient, and either may be empty.** `colour` and
  `colour_secondary` on `characters`, printed as a `135deg` band from one to the other. One alone is
  a flat band; neither is the dark `#3f2b56` head the card printed before a colour could be picked.
  Same two halves as every other card rule.
- **A hero's colours are worn by every card they bring, not just the character card.** A player card
  reads them off its **character** (`CardPresenter::playerCard()`), so a Gunslinger card is a
  Gunslinger card on sight. **A domain has the same two colours a character does**, and a domain
  card falls back to its own domain's colours when it has no character to take them from —
  `character?->colour ?? domain?->colour` — and only then to the dark blue head every player card
  printed before either could be picked. The gold chip in the head was picked to match that blue, so
  once a hero or a domain colours their cards it follows the head: `Colour::chip()` is a darker
  shade of the head's own colour, inked like any other band. `chipStyle` in `CardPreview.vue` and
  `$chipStyle` in the print partial, two copies of one rule. The card editor's preview is passed the
  owner's colours alongside the form (`PlayerCards/Form.vue`), or it would show a navy head for a
  card that prints purple.
- **Renaming or deleting a keyword never rewrites the text that used it.** An unknown token prints
  as typed, so the designer's words survive; the editor says how many pieces of text are affected
  and leaves the decision with them. Same rule as everywhere else: report, don't correct.
- **`Markup::ICONS` is the fallback, not the icon.** It still holds `◆ ● ✦ ▲ ♥`, which is what
  `toPlain()` writes so a design-folder diff stays readable as text. `toHtml()` draws the SVG.
- **A newline in card text is a `<br>`, and that is the only formatting there is.** Both halves of
  the markup do it on the *escaped* text, before any token becomes real HTML, so a `<br>` the
  designer types stays escaped and a break can never land inside a generated `<svg>`. `toPlain()`
  leaves the newline alone, so the design folder keeps the text as typed. Don't reach for
  `white-space: pre-line` instead: the html is dropped into a dozen containers and only the tag
  travels with it. The rules page splits its markdown per line before calling `renderMarkup`, so
  this does not touch it.
- **The browser preview and the print sheet are two implementations of one card design.** Change
  one and change the other, or what the designer sees stops being what they get.
- **The rulebook prints as pages of text, and that is why it is not the card sheet.** A card sheet is
  a grid of fixed shapes laid out in `.page` divs `PrintOptions::paginate()` chunked; a rulebook is a
  column of text whose breaks fall wherever the words run out of paper, so it has no `.page` at all —
  the margins live on `@page` and the content flows. `resources/views/print/rulebook.blade.php` is
  the whole of it, and `RulebookOptions` is its own class for the same reason: the two share the
  paper (`PrintOptions::sheetFor()` and `optionalLength()`, so "a custom sheet falls back to A4" and
  "an edge left empty is absent, not zero" stay one rule each) and nothing else. `columns` means
  columns of text there and columns of cards here, which is the clearest sign they are not one thing.
- **`RulesMarkdown` and `rulesMarkdown.js` are two halves of one rulebook.** The rules editor renders
  its preview while the designer is still typing, so it cannot use the server's HTML; the print sheet
  renders saved text server side, so it cannot use the browser's. Same rule as every card face: change
  one and change the other. They emit **plain unclassed HTML** — `<h2>`, `<ul>`, `<table>` — and
  `.rules-prose` styles it, once in `resources/css/app.css` and once in the rulebook sheet's inline
  CSS, for the same reason `.markup-keyword` is a class and not a pile of utilities. The two were
  last confirmed to agree by rendering all 11 markdown files in `design/` through both and comparing
  — 58k characters, identical. Two differences that check has to normalise away are **inside the
  older `Markup`/`markup.js` pair, not this one**: `e()` escapes `'` as `&#039;` and `escapeHtml()`
  does not, and a `{config:…}` span carries `.markup-config` on the server but Tailwind utilities in
  the browser. Both are harmless on screen and neither is the block markdown; don't "fix" one half.
- **The rulebook's markdown is the subset the design folder is written in, not markdown.** Headings,
  bullets, numbered lists (nested by indent), tables, `**bold**`, `*italic*`, `` `code` `` — and the
  card markup inside all of them. There is no blockquote, no fenced code and no inline link, because
  nothing in `design/` uses one. A table's header is the row above the `|---|` separator; a table
  written without one is all body, which is how a two-column list of terms gets typed. A nested
  bullet goes **inside** the item above it, not beside it: `<ol><li>a<ul>…</ul></li>` is the shape,
  and getting that wrong is invalid HTML that browsers quietly render anyway.
- **A heading's id comes from the words the designer typed, not from what a token resolves to.**
  `## Start on {config:startingOmen} omens` is `start-on-omens`, so changing a tunable number cannot
  silently move a heading and break the contents link pointing at it. Ids are prefixed per document
  (two documents may both have a "Rules" heading) and numbered when one document repeats itself.
  `RulesMarkdown::headings()` generates them the same way `toHtml()` does, so the contents list and
  the page agree by construction rather than by both being careful.
- **The printed rulebook has no page numbers, and cannot have.** Headless Chromium does not implement
  the `@page` margin boxes (`@bottom-center { content: counter(page) }`) that CSS numbers pages with,
  and Chromium's own header/footer prints the `file://` URL beside the number. The contents links are
  real links in the PDF instead. Don't reach for a `position: fixed` running footer — it repeats on
  every page but cannot count them, and it lands on top of the text.
- **The rulebook writes no rules.** The documents are the designer's; the two appendices only list
  what is already in the editor, and every placeholder is flagged as one. The tunable numbers
  appendix prints a value by running `{config:key}` through the markup, so the appendix and a
  paragraph quoting the same number can never disagree, and the keyword glossary draws each keyword
  by running `{token}` the same way. The sheet also names any placeholder number the printed rules
  quote — report, don't correct, like everywhere else.
- **A sticker sheet's grid is given, not guessed.** Left alone, `PrintOptions` still fits as many
  cards as the paper holds. Say otherwise and it is used exactly as said: the four margins
  (`margin_top` and friends), the two gaps (`gutter_x` / `gutter_y`), the grid itself
  (`columns` / `rows`, where 0 still means "fit"), the sheet (`sheet_size=custom` with
  `custom_sheet_width` / `custom_sheet_height`, falling back to A4 rather than to nothing), the
  corner radius, and a printer nudge (`offset_x` / `offset_y`) that moves the grid and its crop
  marks together. A grid that runs off the paper is reported, never shrunk — report, don't correct.
  Every one of them travels in the query string, so a sheet lined up once is a bookmark.
- **An edge measurement left empty is absent, not zero.** `margin_top` and the rest are nullable and
  fall back to `margin`; `gutter_x` / `gutter_y` fall back to `gutter`. 0 is a measurement a label
  sheet really does want, so the form sends an empty field as empty and never as 0 — which is why
  the print options page drops empty values out of the query rather than writing them.
- **`skip` blanks the first cells, and a blank cell is a null in the page.** `PrintOptions::paginate()`
  is the only place pages are chunked, and it pads the front with nulls for labels already peeled
  off the sheet; the print sheet lays a null out as an empty cell. Dropping them instead would print
  every card one label out of place. The skip is clamped to leave one cell, so it can never eat a
  whole sheet.
- **A scenario is five piles of cards, and all five print.** The setup card, the entity deck, the
  board cards, the story beats and the town: each is its own choice on the print page, and
  *Everything* means all five. A town card is `.town-card` in `resources/views/print/partials/card.blade.php` and the
  `kind === 'town'` branch of `CardPreview.vue` — two copies of one rule, like every other card
  face — and the scenario's Town tab shows that same face above the table, so what is edited and
  what prints cannot drift. A district puts its gold cost where a deck card puts a cost and the
  omen it adds where a board card puts health, which is free because a district has no health.
- **The setup card is the designer's sentence, not the tool's summary.** `scenarios.setup` is free
  text, one step per line, and `Scenario::setupSteps()` is the only place a line becomes a step —
  the print sheet and the preview both read the split from there, so they can never disagree about
  what a step is. The face is `.setup-card` in `resources/views/print/partials/card.blade.php` and
  the `kind === 'setup'` branch of `CardPreview.vue`, two copies of one rule like every other card,
  and the scenario's Setup tab shows that same face beside the steps. A step renders through the
  markup like any other card text, `{dreadRule}` included, because a setup step is card text.
- **A scenario with no setup written prints no setup card.** Nothing is written on the designer's
  behalf, so an empty field is a pile with nothing in it rather than a blank card or a guessed one —
  the same reason the keyword library ships empty. `Scenario::hasSetup()` is what the print items
  and the scenario page both ask, and the Setup tab is where the gap is named. The card carries two
  of the scenario's own numbers beside the steps — the Dread the dial starts on, in the corner a
  deck card puts its omen cost, and how many modules a play asks for — and **deliberately not the
  deck size**: `deckSize()` counts the beat-added cards too, and only the base deck is shuffled at
  setup, so a card saying "34" beside "shuffle the deck" would be quietly wrong. How the deck is
  built is a step the designer writes.
- **Every print page is built from one list of items.** `PrintController` lays whatever is being
  printed out as items — a group, a `group:id` key, a quantity and a closure that renders the card —
  and the sheet and the card picker both read that one list, so the picker can never offer a
  different set from the one that prints. The group is named after the deck that prints it, which
  is why `PrintOptions::wants()` is the whole of the deck filter and `all` needs no list of its own.
  The closure is what keeps the options page cheap: markup only runs for cards going on a sheet.
- **A run can name what it prints or what it holds back, and naming wins.** `only` and `except` are
  comma-separated `group:id` keys in the query string, like every other print setting, so a run
  lined up once is a bookmark. `only` is a complete answer and is read first; the picker writes
  whichever of the two lists is shorter, except that an empty `only` would read as the whole deck,
  so "print nothing" is always written as a list of what to hold back. The group is part of the key
  because an entity card 12 and a player card 12 are two different cards in two different tables,
  and a key that is not shaped like one is dropped rather than matched — a mangled URL prints the
  deck rather than nothing.
- **Leaving cards out never changes the deck.** A card held back is still a card in the deck: the
  sheet says how many were left out of the run, and a run with nothing in it says that rather than
  looking like an empty deck. Report, don't correct, the same as everywhere else.
- **The print pool is a list of keys, never a copy of a card.** `print_pool_items` holds the same
  `group:id` the picker uses, so a card edited after it was added prints as it now reads, and one
  deleted since leaves the pool the next time the page loads — it has nothing to print. A player
  card is `player:` in the pool whichever list it came from, because a deck page calls its kit
  `extras` and a domain page its upgrades `upgrade`: `PrintCatalogue::poolKey()` and `parse()` are
  the one place that is decided, and each picker item carries its `pool_key` so the browser never
  has to know. The pool prints through the same `Print/Options` page and the same sheet as every
  other print page (`PrintsItems`); what it adds is where the items come from. Like a print preset
  it is a fact about the printer, not the game, so `design:export` knows nothing about it.
- **An offset is cards already printed; `skip` is labels already peeled off.** `offset` is part of
  `PrintSelection`, not `PrintOptions`, because it describes one run, not a sheet lined up once —
  so it is never saved into a print preset. It counts copies in print order after the picker's
  choices, so `offset=2` on a run starting with three Tentacle Lash prints the third. A card the
  offset skips entirely never runs its markup, and an offset past the end says so on the sheet
  rather than printing an empty page silently.
- **`corner_radius` describes the label, not the card.** It reaches the print sheet only. The
  on-screen preview keeps its own rounding on purpose, so this one is not a rule in two halves.
- **The resolved-half highlight belongs to `CardPreview`, on the half element itself.** It used to
  be an overlay positioned at hardcoded percentages, which landed on the gap between the halves
  rather than the half. Pass `:highlight="'top' | 'bottom'"`; don't reintroduce magic offsets.
- **The arrow is on the right edge and points at the NEXT card.** Every entity card has one, whatever
  its layout, and it decides which half of the split card *after* it resolves — never its own halves.
  That was the v1 rule and it is gone. `Storyline::resolve()` is the only implementation; the
  storyline preview renders server-side on purpose so there is no second copy to drift.
- **The playtest table is a table, not a rules engine, and it keeps nothing.** `/scenarios/{slug}/play`
  shuffles the deck, reveals against the omen pool and holds the counters a play would otherwise need
  coins for: Dread X, health for whoever is out on the table, and which story beat is current. The
  whole session lives in the browser's `localStorage`, keyed by the scenario and its chosen modules —
  the same reason a built deck is a query string and not a row. Nothing about a play is a fact about
  the game, so there is no table for it and `design:export` knows nothing about one.
- **The playtest table is the one place the arrow rule is written twice.** `Play.vue` re-implements
  `Storyline::resolve()` in JS because a redirect or a discard has to redraw the line instantly, and
  because a live game has a real discard pile the server-side preview does not. Change one and change
  the other. **The storyline and the discard pile are two piles** for the reason the rules make them
  two: a storyline's first card takes its arrow from the top of the discard — the last card resolved
  — which is what `firstCardArrowSource` says, so the page reads it from there rather than assuming.
- **The reveal step reports; it never applies.** `omenReveal.js` takes cards until their omen cost
  meets or exceeds the pool, then the pool empties. It names the Empowered overshoot, the X a cost-X
  card drained, and a Dread check that came up short — and applies none of them, because the Dread
  effect is the designer's placeholder sentence. The same goes for the two open questions it sits on:
  an X-cost card skips the Dread check because open question 12 records that as what happens
  *currently*, and an empty draw pile is never reshuffled on its own (open question 10), so the pool
  keeps whatever omen went unmatched and the page says so.
- **Entity cards and player cards are different tables and different card faces.** `entity_cards`
  is the thing the game plays against; `player_cards` is what a character brings. Never count them
  as one number: the dashboard reports them separately for that reason.
- **A player card belongs to a character or to a domain, never both**, the same way an entity card
  belongs to a scenario or a module. `character_id` and `domain_id` are both nullable and exactly
  one is set; `PlayerCard::owner()` is what everything else asks. A slug is unique per owner, so the
  Gunslinger and a domain can both hold a "Lucky Coin".
- **A player card's role names the list its owner's file writes it in.** A character file holds
  `kit` (starts in play, outside the 20), `signature` (one of the 20) and `upgrades`; a domain file
  holds `cards` (role `domain`) and `upgrades`. `design:export` writes each role back into its own
  list, so the role has to stay accurate, and the editor only offers an owner its own roles.
- **Upgrade links are slugs, not foreign keys**, because that is what the design files hold and it
  means importing does not depend on card order. Both ends have to point at each other, so
  **anything that writes one end must call `PlayerCard::syncUpgradeLinks()`** — it writes the other
  end and releases whatever the pair took over. Without it the editor can only half-make a link:
  naming an upgrade on a card left the upgrade not pointing back, and `PlayerDeck::warnings()`
  then told the designer the thing they had just been asked to do was wrong. Deleting a card
  unlinks its partner, and a duplicate starts unlinked because a pair is one to one.
  **A pair lives inside one owner.** `siblings()` scopes by the owner, so writing a pair in a domain
  cannot reach across and release a character's. An ownerless card returns no siblings at all —
  without that guard, "every card whose character is null" would sweep in every domain card there is.
- **A character does not have a domain, and a domain does not have characters.** They are two
  separate things and neither is a column on the other. The place they meet is **deck building**:
  `/decks` pairs one character with one domain and takes 20 of its cards. Do not put the pairing back
  on either row — that was tried and it was wrong.
- **A built deck is not stored — the query string is still the deck.** The whole build — character,
  domain, and how many copies of each pool card — lives there, the way a scenario's chosen modules
  do on the deck assembly page, so a deck can be linked, reloaded and printed without a record of
  its own, and `/print/deck` carries the same query through (`context` on the print options page).
  **A saved deck is a name pointing at that query string, nothing more.** `saved_decks` (the
  `SavedDeck` model, `SavedDeckController`) holds a `build` JSON blob shaped exactly like the query —
  `character` slug, `domain` slug, `take` map — the same way a `PrintPreset` holds `PrintOptions`
  verbatim. `DeckBuild::takeFromRequest()` is the one place a `take[...]` query becomes that map, so
  the deck builder and saving a build read it the same way. It is not a second place the pairing
  lives: nothing about a character or domain changes when a deck is saved. If the character or
  domain a save names is later deleted, loading it behaves exactly as typing that slug into the URL
  by hand would — the deck builder already treats an unresolved slug as "not picked", nothing more
  is added for a saved deck. Saving under a name already in use overwrites it, like a print preset.
  This does not reopen the pairing question two rules up: a saved deck still is not a column on
  `characters` or `domains`.
- **A pool is not 20 cards; it is what 20 are chosen from.** So a pool bigger than the slot count is
  the point — `pool_left` is what a deck leaves behind — and the only wrong pool is one too small to
  supply a deck. Never warn about a big pool, and never make a domain default to 20.
- **`DeckBuild` caps what it cannot honour and says so.** Asking for more copies of a card than the
  pool prints — or than `maxCopiesPerDomainCard` allows — gives a deck that could actually be built,
  plus a warning naming the bound that bit. `limitFor()` is the one place the two bounds meet, and
  the stepper on the deck builder reads the same number through `pool.*.limit`.
  Taking too few or too many overall is reported and left alone, like every other player-side count.
- **The colourless pool only counts while the rules say it does.** `Domain::is_neutral` marks it, and
  `neutralFillsDomainSlots` decides whether its cards can take a slot. A deck built on a pool
  supplying nothing is told so; the pool's own size is still reported as it is.
- **`design:export` writes `players/domains/<slug>.json`, and a character file names no domain at
  all** — which domain a deck uses is the deck's choice, so it is not a fact about the character and
  the handoff character files still export byte for byte. The directory is made on the way past, so a
  design folder with no domains does not grow an empty one. There is a test for both.
- **A domain card prints the pool it came out of**, as a badge in the card foot, falling back to the
  domain's name when it has no set icon — `domainBadge` in `CardPreview.vue` and `$badge` in the
  print partial, two copies of one rule. A character's own card carries no badge.
- **A Hireling is a player card type, and its two numbers belong to it alone.** `uses` and
  `sacrifice_value` are columns on `player_cards`, nullable because nothing but a Hireling has
  them, and `PlayerCard::isHireling()` is what everything asks rather than comparing the string.
  `design:export` writes `uses` and `sacrificeValue` **only for a Hireling**, so a card of any other
  type keeps the file shape the designer already has instead of growing two null keys. The import
  reads them off whatever card carries them: a design file can say anything, and `CardStats` reports
  a stray number rather than dropping it.
- **The Hireling limit is a limit on the table, not on the deck.** `maxHirelingsInPlay` is how many
  one player may have in play at once, so it is printed beside the count (`HirelingSummary.vue`) and
  never warned about. A pool of eight Hirelings is a choice, the same way a pool bigger than the slot
  count is. The number and every rule behind it are the designer's placeholders.
- **Adding an entity card type is no edits at all.** It is a row in `card_types`, edited at
  `/rules/card-types` or, for a scenario's own, on the scenario's page — the same way a keyword is
  data and an icon token is code. The icon falls back to one named after the slug, which is how the
  five shipped types get theirs, so a type named something else picks one from the icon library.
- **Adding a player card type is five edits.** `PlayerCard::TYPES`, the icon in `build/icons.mjs`
  (then `npm run icons` — `IconTest` fails without it), `CardPresenter::PLAYER_TYPES` for the printed
  name, and `typeNames` in `CardPreview.vue` plus `typeLabels` in `PlayerCards/Form.vue` for the
  browser. Four of the five are one fact written in two halves; miss one and the editor and the
  printed card disagree.
- **A Hireling prints its term in the head's right corner and its sacrifice value in the foot** —
  `.card-uses` / `.card-sacrifice` in `CardPreview.vue` and `.uses` / `.sacrifice` in the print
  sheet's inline CSS, two copies of one rule. The corner is the one a board card puts health in,
  which is free here because a Hireling has none. A number the designer has not set prints as `?`,
  not `0`: an unfinished card should say so rather than claim a value.
- **The editor clears a Hireling's numbers when the type changes, the importer never does.** Typing a
  card back to an Action clears `uses` and `sacrifice_value` in the form, because the editor saves
  what it shows. A card whose design file carries stray numbers keeps them, and the character or
  domain page says so. Same rule as everywhere: the file is the designer's, the form is the tool's.
- **`player_cards.domain` was a free-text stand-in and is gone.** Nothing ever wrote it; the owning
  domain replaces it. The origin picker is only offered on a domain's cards, but every origin stays
  valid, so editing a card whose design file says something odd never rewrites it — it is reported.
- **`PlayerDeck` reports, it never corrects.** A deck of 21 stays a deck of 21 with a note on the
  page. Quietly trimming it to 20 would be deciding something that is the designer's to decide.
- **A card belongs to a scenario or to a module, never both.** `scenario_id` and `module_id` are both
  nullable and exactly one is set. Anything counting cards has to say which it means: deleting a
  scenario must not take module cards with it.
- **`/design` presses the two commands; it is not a third way to move data.** `DesignFolder` runs
  `design:import` and `design:export` and nothing else, so the button and the terminal cannot drift.
  What the page adds is saying what is about to happen first: which files differ, which branch a push
  would go to, and what would stop a commit — a detached HEAD, no `user.email`, no `origin` — named
  before the button is pressed rather than as a failure afterwards. **Import is the destructive one**
  and the page says so twice and asks before running it: the folder is the source of truth, so a card
  the editor has and the folder does not is deleted by it.
- **The commit button commits `design/` and never the code.** The paths are given to `git commit` as
  well as to `git add`, so a tool change sitting in the tree stays there and whatever else was staged
  stays staged — the folder is the game and the rest of the repository is the tool, and one button
  must not carry the second while writing the first. There is a test that edits both and checks only
  one lands. **Every git call goes through `DesignFolder::git()`**, which runs the binary with an
  array of arguments and no shell, so a commit message is a commit message; there is a test that
  commits one full of `$(…)` and backticks and reads it back verbatim.
- **Only the right-hand end of git's output is trimmed.** `git status --porcelain` puts the staged
  and unstaged columns first, and an unstaged change leads with a space — `trim()` ate it and took
  the first character off `design/...` with it. The status columns are matched, never cut at fixed
  offsets, for the same reason, and a rename's `old -> new` keeps the new name. There is a test for
  a staged change specifically, because that is the one where the space is not there.
- **The page can push, and the app has no auth.** That is the same bargain the rest of the tool
  makes — every editor route is open, because it is a single-designer local app — but this one
  reaches the network, so don't expose the app beyond localhost. The commit message is required and
  has no default, so a push is always something the designer typed.
- **`design:import` deletes tunable numbers the design folder has dropped.** v3 removed `handSize`
  and `baseGoldPerRound` followed it, both onto the character card. Without the prune the next
  export writes the dead key straight back, which is exactly what happened once during v3 and is
  why there is a test for it.
- **Health, hand size and gold generation are per character, not tunable numbers.** They live on
  `characters` and print on the character card, so a player reads all three in one place. Adding a
  fourth of these means the same five edits: the column, the character file key, the import and
  export, the card face in both `CardPreview` and the print partial, and the form.
- **A config value can be an object now** (`deckSize` is `{signature, domain}`), so `value_type`
  has a `map` alongside `int`, `bool`, `range` and `string`. `{config:deckSize}` renders
  "20 signature, 20 domain" rather than a range. Both copies of the markup know this.
- **`design:export` writes the design folder verbatim.** Match the existing key names (split faces
  use `position`, not `half`) and the two-space indentation, or every export becomes a huge diff.
  A colour is written **only when one is picked** — a card type's `colour` and `icon`, a scenario's
  own `cardTypes`, a character's `colours: {from, to}` — so a design folder nobody has coloured
  comes back out byte for byte. There is a test for that, and it is the same rule a Hireling's two
  numbers follow.
- **Markdown bodies are exempt from `TrimStrings`** in `bootstrap/app.php`. Without that, every
  save strips the trailing newline and creates a spurious version.
- **Placeholders are load-bearing.** `is_placeholder` on cards and configs drives the flags in the
  UI. Keep new data defaulting to placeholder, not to final.
- **Board card health is free text** so it can hold "12 per player". The exporter turns it back
  into a number only when it is one.
- **Inertia's server and client versions must match.** The v3 client reads the initial page from
  `<script data-page="app" type="application/json">`; v1 wrote it to `<div id="app" data-page>`.
  Upgrading one side alone gives a blank page and a null-deref in the console, not an error.
- **Inertia mounts into a bare `<div id="app">` with no height.** A percentage height or
  `min-h-full` on the layout wrapper resolves against that auto-height div and silently does
  nothing, so short pages leave the sidebar stopping halfway down. `AppLayout` uses `min-h-screen`
  for that reason; don't swap it back.
- **The placeholder flag lives inside `.card-body`, not over the card head.** A character card and
  a board card both put health in the head's right corner, and the flag was landing on top of it.
  One rule for every card kind, in both the preview and the print sheet.
- **An upgrade card prints what it replaces where other cards print where they start.** That
  bottom-right corner is `cornerNote` in `CardPreview.vue` and `$corner` in the print partial —
  two copies of one rule, so change both.
- **A flavour line sits above `.card-body`, not inside it.** Character and beat cards have one, and
  inside the body it runs under the placeholder flag. The print sheet always had it above; the
  preview did not, which is what made the collision show up on one side only.
- **A card preview is wrapped in a button now** (`.card-button`) so clicking it opens `CardZoom`.
  That means the card's whole text is inside a `<button>`: a Playwright selector like
  `button:has-text("Add")` will match a card whose effect text happens to read "Add 1 omen". Scope
  selectors to the form you mean.
- **Blade caches the compiled root view.** After anything that changes the `@inertia` directive's
  output, `php artisan view:clear`, or the old markup keeps being served.
- **Page components live in `resources/js/Pages`, capital P.** Inertia 3 defaults to lowercase
  `pages`; `config/inertia.php` points at ours. Renaming the directory would be a case-only rename
  that macOS and Windows checkouts handle badly, so don't.
- **The tests need the frontend built.** Inertia renders every page through the Vite manifest, and
  `public/build` is not tracked, so a checkout that has not run `npm run build` fails the whole
  feature suite on "Vite manifest not found" rather than on anything it is testing — 50 of 404 last
  time, and they look like real failures in files that have nothing to do with assets. `./setup`
  builds them, which is why this only ever bit CI. `.github/workflows/tests.yml` installs and builds
  the frontend for that reason, and its matrix is `[8.4]` with `fail-fast: false`: 8.2 and 8.3 could
  never get past `composer install` (see the floor above), and those legs fail within seconds, so
  they were taking 8.4 down with them — which is why it kept reporting "cancelled" and the suite's
  real result went unseen.
- **A test must not depend on which domains happen to be drafted.** `ColourDesignRoundTripTest`
  asserted that `players/domains/hunt.json` grows no `colours` key and that setting one colour on
  Hunt writes one key — both true until the designer coloured Hunt, and then two red tests about
  the design folder rather than about the exporter. A test that says "one colour alone" now clears
  the other explicitly, and the "nobody has coloured" one clears every colour first. Same rule as
  `importDesignWithoutDomains()`: test the tool, not this week's draft.
- **Nor on which characters happen to be drafted.** Seven tests wrote "2 characters" into an
  assertion, and drafting the Berserker turned all seven red without anything about the tool
  changing. A count now comes from `Character::count()` or from `characterSlugs()` in
  `tests/TestCase.php`, which reads the character files out of `design/players/` — so the next
  character is covered by the round-trip tests the day it is written instead of breaking them.
- **Don't let a dependency cap the PHP version.** Check `composer.lock` for `~8.x.0`-style
  constraints before committing a lock change; one of those is what broke the first install.

## Open questions this code deliberately does not answer

`design/rules/05-decisions-and-open-questions.md` has 21 of them. Four are wired to config rather
than settled in code, and must stay that way until the designer decides:

- **Which arrow a split card uses** (question 1) and **what the first card in a row uses**
  (question 2) come from `defaultArrow` and `firstCardArrowSource`.
- **Redirect's form** (question 3) — only "flip" is modelled, and the storyline preview says so.
- **Arrow balance** (question 6) — the scenario and deck pages report the top/bottom mix and pass no
  judgement on it.
- **How many copies of one domain card a deck may take** (`maxCopiesPerDomainCard`) — the mechanism
  is built and the number is not set. Empty means the pool's own print run is the only limit, which
  is what the app shipped with before the cap existed, so nothing was decided by adding it.
- **Where a bought card goes** (`shopPurchaseDestination`) — the designer likes deck-bottom and says
  they are not certain, so it is a placeholder in the tunable numbers and the description says why.
- **Whether the colourless pool is one pool or several**, and whether a coloured domain may hold a
  neutral card. Both are expressible and neither is assumed.

The keyword library ships **empty** for the same reason the domains do: the mechanism is the tool's,
the words are the game's. `design/data/keywords.json` is written on export once there is a keyword to
write, and read on import; a design folder with none does not grow the file.

`design/players/README.md` now records what the designer has settled about domains: building a deck
pairs a character with **one** domain, **any** domain will do, and the deck takes **20 cards out of
it**. Which 20 is a per-deck choice and is deliberately not stored.

The player handoff (`design/players/README.md`) ends with seven things to check in playtesting, and
four more open questions sit inside the character notes. None of them are the tool's to answer.

The **Hireling** is the v3.1 handoff's own addition, and every rule behind it is a placeholder the
designer flagged as one: how many uses a card gets, what a sacrifice prevents, **where a sacrificed
Hireling goes** (removed from the game is only the default), whether exhaust should replace the
"once per round" wording on item cards, and what an upgrade does to one. The tool holds the two
numbers, prints them and reports what does not line up. It settles none of them, and
`maxHirelingsInPlay` is a placeholder in the tunable numbers for the same reason.

## Not built yet

**Most of the domains.** The system is built — a domain library, its own cards and upgrades, the
deck builder, the deck maths, the print sheets and the design-folder round trip. The designer has
since drafted **Hunt** (20 cards), **Neutral** (the colourless pool) and **Tide** (named, no cards
yet), in `design/players/domains/<slug>.json`. What goes in them is theirs; the rest is still to
write.

Because of that, a test that builds its own domains calls `importDesignWithoutDomains()` from
`tests/TestCase.php` rather than `design:import`, so it tests the tool and not whichever domains
happen to be drafted. Tests about the design folder's own contents still import it whole.

**The v3.1 domains.** The designer's v3.1 handoff drafts six full domains — Hunt, Tide, Trade, Pact,
Crew and Lore, 30 cards and 5 upgrades each — and Crew is the one the Hirelings live in. They were
deliberately **not** imported: the Hireling work is the tool's, the cards are the game's, and
`design/players/domains/` still holds only what was there before. Importing them is a separate
decision and the designer's to make. `design/players/domains/overview.md` from that handoff, which
is where the Hireling rules are written down, is not in the design folder either.

**The shop and the Smithy as screens.** A card carries its `shop_cost` and its upgrade link, and
the character page lists what the Smithy would swap, but there is no shop or town screen.

**The rules and the cards as one document — built.** Somebody asked: the designer wanted to read the
rules and the current card list on a phone, with this app stopped and on another network. That is
`pocket:build` and `/pocket` (`app/Support/Pocket.php`, `resources/views/pocket.blade.php`), and
`.github/workflows/pocket.yml` publishes it. It is a **reading** page, not a fifth editor: nothing on
it writes, and every placeholder on it is flagged the way the printed rulebook flags one.

**A pocket page that knows it is out of date.** It says when it was built and nothing more. It cannot
know that the editor has moved on since, because it is a file with no server behind it — which is the
whole point of it. If that starts to bite, the honest fix is the workflow running on more than pushes
to `main`, not a page that guesses.
