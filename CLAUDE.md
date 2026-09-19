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
| `app/Support/Icons.php` | **generated** — every icon as an SVG path, from Font Awesome |
| `build/icons.mjs` | the icon map; edit it and run `npm run icons` |
| `app/Support/Markup.php` | the `{omen}` / `{config:key}` markup, server side |
| `resources/js/markup.js` | the same markup in the browser — **keep these two in step** |
| `app/Support/CardPresenter.php` | the one card shape used by the editor, preview and print |
| `app/Support/PrintOptions.php` | sheet and card geometry, all in millimetres |
| `app/Support/Storyline.php` | the arrow rule: which half of each split card resolves |
| `app/Support/DeckAssembly.php` | a scenario's base deck plus the modules chosen for a play |
| `app/Support/PlayerDeck.php` | a character's cards against the deck rules, and what does not add up |
| `app/Support/DeckBuild.php` | a deck being built: a character, a domain, and what is taken from it |
| `app/Support/DomainPool.php` | the same for a domain: the shared half of a deck |
| `app/Support/CardStats.php` | the counting, curves and pair checks both of those share |
| `resources/views/print/` | the print sheet, inline CSS so it renders from `file://` for the PDF |
| `resources/js/Components/CardPreview.vue` | the on-screen card — mirrors the print partial |
| `resources/js/Components/CardZoom.vue` | the full-size card overlay, driven by `useCardZoom.js` |
| `resources/js/Components/Icon.vue` | `<Icon name="omen" />`, drawing the paths the server shares |

## Things that will bite

- **Icons are inline SVG, never a webfont or an icon stylesheet.** The print sheet is rendered from
  `file://` by headless Chromium, where a `<link>` or an `@font-face` URL does not load, so a
  webfont would print empty boxes. `app/Support/Icons.php` is generated from the Font Awesome
  package by `build/icons.mjs`: to add or change an icon, edit the map there and run
  `npm run icons` — don't hand-edit the PHP. The same paths reach the browser through Inertia's
  `markup.paths` prop, so `Icon.vue` and `Icons::svg()` cannot drift. There is a test asserting the
  sheet carries no `<link>` and no `@font-face`.
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
- **The resolved-half highlight belongs to `CardPreview`, on the half element itself.** It used to
  be an overlay positioned at hardcoded percentages, which landed on the gap between the halves
  rather than the half. Pass `:highlight="'top' | 'bottom'"`; don't reintroduce magic offsets.
- **The arrow is on the right edge and points at the NEXT card.** Every entity card has one, whatever
  its layout, and it decides which half of the split card *after* it resolves — never its own halves.
  That was the v1 rule and it is gone. `Storyline::resolve()` is the only implementation; the
  storyline preview renders server-side on purpose so there is no second copy to drift.
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
- **A built deck is not stored.** The whole build — character, domain, and how many copies of each
  pool card — lives in the query string, the way a scenario's chosen modules do on the deck assembly
  page. So a deck can be linked, reloaded and printed without a record of its own, and
  `/print/deck` carries the same query through (`context` on the print options page). If saved decks
  are ever wanted, that is a new table, not a field on `characters`.
- **A pool is not 20 cards; it is what 20 are chosen from.** So a pool bigger than the slot count is
  the point — `pool_left` is what a deck leaves behind — and the only wrong pool is one too small to
  supply a deck. Never warn about a big pool, and never make a domain default to 20.
- **`DeckBuild` caps what it cannot honour and says so.** Asking for more copies of a card than the
  pool prints gives a deck that could actually be built, plus a warning naming the number asked for.
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
- **`player_cards.domain` was a free-text stand-in and is gone.** Nothing ever wrote it; the owning
  domain replaces it. The origin picker is only offered on a domain's cards, but every origin stays
  valid, so editing a card whose design file says something odd never rewrites it — it is reported.
- **`PlayerDeck` reports, it never corrects.** A deck of 21 stays a deck of 21 with a note on the
  page. Quietly trimming it to 20 would be deciding something that is the designer's to decide.
- **A card belongs to a scenario or to a module, never both.** `scenario_id` and `module_id` are both
  nullable and exactly one is set. Anything counting cards has to say which it means: deleting a
  scenario must not take module cards with it.
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
- **Where a bought card goes** (`shopPurchaseDestination`) — the designer likes deck-bottom and says
  they are not certain, so it is a placeholder in the tunable numbers and the description says why.
- **Whether the colourless pool is one pool or several**, and whether a coloured domain may hold a
  neutral card. Both are expressible and neither is assumed.

`design/players/README.md` now records what the designer has settled about domains: building a deck
pairs a character with **one** domain, **any** domain will do, and the deck takes **20 cards out of
it**. Which 20 is a per-deck choice and is deliberately not stored.

The player handoff (`design/players/README.md`) ends with seven things to check in playtesting, and
four more open questions sit inside the character notes. None of them are the tool's to answer.

## Not built yet

**The domains themselves.** The system is built — a domain library, its own cards and upgrades, the
deck builder, the deck maths, the print sheets and the design-folder round trip — but no domain is
designed, so the app ships with none. That is the designer's to write, here or as
`design/players/domains/<slug>.json`.

**The shop and the Smithy as screens.** A card carries its `shop_cost` and its upgrade link, and
the character page lists what the Smithy would swap, but there is no shop or town screen.

Printing the rulebook to PDF is not built either — only the cards are.
