# Card Forge

The design platform for the card game in [`design/`](design/): a cooperative game where players
face an **entity** through a story of set-aside beats, and **omen** is the escalation engine.

It does three things, which are the three things the designer asked for:

1. **Create and edit cards** — entity deck cards, module cards, board cards, story beats, and the
   player side: characters, their signature cards, their kit and their upgrades.
2. **Create and edit the rules** — the rulebook markdown and the tunable numbers behind it.
3. **Print the cards and the rules** — print-ready sheets and a PDF, at real card sizes with crop
   marks and bleed, and the rulebook itself as pages of text.

Plus three tools for testing the design: a **deck assembly** view that builds a scenario's deck from
the modules chosen for a play, a **storyline preview** that lays cards out in a row and shows which
half of each split card resolves, and a **character page** that checks a player's 20 against the
deck rules and reports what does not add up.

## Running it

```bash
git clone https://github.com/amyiludur/card-forging.git
cd card-forging
./setup            # installs everything, creates the database, imports design/
composer dev       # starts the app, rebuilding assets as you edit
```

Then open **http://localhost:8000**.

Needs PHP with `pdo_sqlite`, Composer, and Node 20 or newer. **PHP 8.4 or newer**: the Symfony
components in the lock file require it, and the suite is run on 8.4 and 8.5. On Windows run `./setup` from **Git Bash** or WSL; PowerShell and cmd cannot run it.

`./setup` is safe to re-run: it skips whatever is already done and never touches your `.env`. Re-run
it after pulling, since it picks up dependency changes and clears stale caches. To serve what is
already built without the asset watcher, `php artisan serve` is enough.

### Or with Docker

```bash
docker compose up
```

Same address. The image carries PHP, Node and Chromium, so the PDF export works with nothing
installed on your machine, and `design/` and the database are mounted from the host so they survive
rebuilds.

> The Docker path has not been run end to end — the sandbox it was written in cannot reach Debian's
> package repositories, so the image was never built. The scripted setup above is the tested one.
> If `docker compose up` fails, it will be in the `apt-get`/Node layer of the `Dockerfile`.

```bash
docker compose up -d                                    # in the background
docker compose exec app php artisan design:export       # write the editor back to design/
docker compose exec app php artisan design:import       # reload design/ into the editor
docker compose down                                     # stop
CARD_FORGE_PORT=9000 docker compose up                  # on another port
```

## The design folder is the source of truth

`design/` holds the rules markdown, the scenario JSON, the character files in `design/players/` and
the domain files in `design/players/domains/`, and it is what git tracks. The database is the editor's working copy. Two commands move between
them:

```bash
php artisan design:import   # design/ -> database  (safe to re-run; matches on name/slug)
php artisan design:export   # database -> design/  (then commit design/)
```

So the loop is: import, edit in the browser, export, commit. Version history comes from git, and
the rules editor also keeps its own per-document history inside the app for quick undo.

## Icons

Icons come from [Font Awesome Free](https://fontawesome.com/license/free) (CC BY 4.0), compiled
into `app/Support/Icons.php` by `npm run icons`. They are **inline SVG**, not a webfont: the print
sheet is rendered from `file://` by headless Chromium, where a stylesheet or font URL would not
load and every icon would print as an empty box.

The server and the browser draw from the same path data, so a card looks the same in the editor and
on paper. To change an icon, edit the map at the top of `build/icons.mjs` and run `npm run icons`.

The game's own symbols map like this, and are the most likely ones to want changing:

| Token | Icon | |
|---|---|---|
| `{omen}` | eye | the escalation resource |
| `{gold}` | coins | the currency |
| `{damage}` | burst | |
| `{dread}` | skull | |
| `{health}` | heart | |

## Markup in card and rules text

Plain-text tokens, typed by hand or inserted from the editor's palette:

| Token | Renders as |
|---|---|
| `{omen}` `{gold}` `{damage}` `{dread}` `{health}` | the icon |
| `{unique}` `{bottom-draw}` | a keyword from the library, defined at `/rules/keywords` |
| `{config:startingOmen}` | the current value of that tunable number |
| `{dreadRule}` | the scenario's Dread effect, written onto the card |

A config reference means changing a number in **Tunable numbers** changes every card and every
paragraph that reads it. Values still marked *placeholder* render underlined, so a draft looks
like a draft everywhere it appears.

`{dreadRule}` does the same for a whole sentence. A card that belongs to a scenario can quote that
scenario's **Dread effect** rather than repeat it, so editing the scenario edits every card that
reads it, and the two can never drift apart. It is only offered where there is one scenario to
read: a module's cards are played with whichever scenario the table chose, so a `{dreadRule}` on
one prints a red `?dreadRule` rather than a guess — the same as a scenario whose Dread effect has
not been written yet. The stored text keeps the token, so the design folder still says
`{dreadRule}` and the rule stays written in one place.

**Line breaks are the one other thing the text does.** Press Enter in an effect box and the card
breaks there; leave a blank line and the card leaves a blank line. Everything else is plain text —
there is no bold, no italics, no lists, no markdown. The design folder stores the newline as you
typed it, so a diff still reads as text.

The print options also offer **auto icons**, which turns "2 omen" into "2 ◆" at print time without
touching the stored text.

## Printing

`/print/{scenario}` lays out a deck and shows what will come off the printer. `/print/module/{slug}`
and `/print/character/{slug}` do the same for a module and for a player's deck.

- Entity deck, board cards or story beats, each as its own deck. Cards print one copy per quantity.
- A character prints its deck cards — the 20, the kit and the upgrades — and its character card.
- Poker (63.5 × 88.9 mm), bridge, tarot, square, or a custom size in millimetres.
- A4 or Letter, with the grid computed from the card size, margin, gutter and bleed. If the cards
  do not fit, the page says so rather than silently cropping them.
- Crop marks in the sheet margin, clear of the neighbouring cards.
- Optional card backs on alternating sheets, row-mirrored so a long-edge duplex flip lines up.

### The rulebook

`/print/rules` prints the rules themselves. A rulebook is a column of text rather than a grid of
shapes, so the page breaks fall wherever the words run out of paper. What is set is the page
itself: the sheet, the four margins, one to three columns, the text size, and whether each document
starts on a new page.

- The picker ticks documents the same way the card picker ticks cards, and the sheet says how many
  were left out of the run.
- A **contents list** built from the designer's own headings. Its links work inside the PDF.
- Two optional appendices, both of them the tool listing what is already in the editor rather than
  writing anything: the **tunable numbers**, and the **keyword library** as a glossary. Every
  placeholder is flagged as one.
- The sheet names any tunable number the printed rules quote that is still a placeholder, so a
  rulebook taken to a playtest says which of its numbers are not decided.

There are no page numbers: headless Chromium does not support the `@page` margin boxes that CSS
counts pages in. The contents links are the way around that.

**Download PDF** renders through headless Chromium. The Docker image ships with it. Running without
Docker, it looks for `chromium`, `chromium-browser` or `google-chrome` — set `CHROMIUM_BINARY` in
`.env` to point somewhere else. If there is no Chromium at all, open the preview in a new tab and
use the browser's own print dialogue with margins set to none: the sheet is real print CSS, so it
comes out the same.

## Data model

| Model | What it holds |
|---|---|
| `RulesConfig` | one tunable number: value, type, whether it is still a placeholder |
| `CardType` | Attack, Hazard, Summon, Curse, Story — shared across scenarios |
| `Scenario` | the entity: type, overview, starting Dread, traits, win and lose text |
| `StoryBeat` | order, flavour, on-reach, advance trigger, on-advance, Dread change |
| `Module` | a themed card set: set icon, theme, which scenarios it suits, its own cards |
| `EntityCard` + `EntityCardFace` | a deck card and its one or two typed halves |
| `BoardCard` | a board piece: health (free text, so "12 per player" works), traits, text |
| `TownAction` | a town district: effect, gold cost, omen cost |
| `Character` | a player character: health, hand size, gold per round, identity ability, title and story |
| `PlayerCard` | a card in a player's deck: role, type, gold cost, omen icons, where it starts |
| `RuleDocument` + `RuleDocumentVersion` | the rulebook markdown with history |

An entity or board card belongs to a scenario's base deck **or** to a module, never both.

### Arrows

**Every** entity card carries an arrow on its right edge — single, split and X-cost alike. It points
at the top or bottom half of the card **to its right** in the storyline, so a split card resolves the
half indicated by the arrow of the card *before* it. The arrow says nothing about its own card's
halves.

The arrow is required (`top` or `bottom`), printed on the card, and Redirect places a token over it
to override it in play.

The **storyline preview** at `/scenarios/{slug}/storyline` is where this gets tested: it draws a row,
highlights the half each split card resolves, and lets you flip any arrow to see Redirect ripple into
the next card. The rule lives in `app/Support/Storyline.php` and nowhere else.

### Characters and player decks

A deck is **20 signature cards plus 20 domain cards**. The signature 20 belong to a character; the
other 20 are taken from a **domain** when the deck is built. Outside the 40 sit the **kit** (starts in play,
like the Gunslinger's Revolver) and the **upgrades** the Smithy swaps a card for.

Each card carries a gold cost to play, the omen icons playing it adds to the pool, an optional shop
price, and where it starts — in the deck or in the player's own shop pile.

Health, hand size and **gold generated per round** are all per character and all print on the
character card, so a player reads them in one place rather than looking up a global. There is no
`baseGoldPerRound` in the tunable numbers for that reason.

Upgrades pair with the card they replace. The pairing is one fact: set it from either card and
both ends are written, and naming a new partner releases the old one. An upgrade prints the card
it replaces in its bottom corner, where other cards print where they start.

`/characters/{slug}` is where a deck gets checked. It reports the signature count against the rule,
the deck-versus-shop split, the omen and gold curves against what the character generates, the type
and keyword mix, and what the Smithy would swap. Where something does not line up — a deck of 21, an upgrade pointing at a card that is
not there, a card carrying more omen than the rules allow — it says so **and changes nothing**.
Whether the cards are wrong or the rule is, is the designer's to decide.

### Domains

A domain is the shared half of a deck: a pool of cards that belongs to no one character, the way a
module's cards belong to the module rather than a scenario. A card lives in a character **or** in a
domain, never both.

Characters and domains are separate things. Neither owns the other, and the place they meet is the
**deck builder** below.

A pool is not twenty cards — it is what twenty are picked from. A domain of 32 leaves 12 to choose
between; a domain of exactly 20 leaves no choice at all; a domain of 12 cannot supply a deck, and
that is the only case the app calls wrong.

The **colourless pool** (`neutral`) is a domain marked as such; whether its cards can take a slot at
all is `neutralFillsDomainSlots` in the tunable numbers, and a deck built on a pool that currently
supplies nothing is told so.

`/domains` is the library. Each domain gets the same page a character does — curves, type and keyword
mix, what the Smithy would swap, and what does not line up — plus its own print sheet. A printed
domain card carries a badge naming its pool, falling back to the domain's name when it has no set
icon, so a pile of cut-out cards can be sorted back into pools.

**No domain is designed yet.** Make one at `/domains/create`, or write
`design/players/domains/<slug>.json` and import it:

```json
{
  "id": "tide",
  "name": "Tide",
  "title": null,
  "status": "draft",
  "identity": "Draws deep and pays later.",
  "setIcon": "TD",
  "neutral": false,
  "cards": [
    {
      "id": "undertow", "name": "Undertow", "qty": 2, "type": "action",
      "traits": ["Deep"], "goldCost": 1, "omenIcons": 1, "shopCost": null,
      "startZone": "deck", "text": "Draw 2, then discard 1.",
      "keywords": [], "upgradesTo": "riptide", "upgradeOf": null, "origin": "domain"
    }
  ],
  "upgrades": [],
  "notes": []
}
```

Character files name no domain: which domain a deck uses is the deck's choice, not the character's.

### Deck building

`/decks` is where a deck comes from. Pick a character, pick a domain — any domain goes with any
character — and take the domain cards this deck carries, a copy at a time or straight from the top of
the pool. It reports the deck as it stands: 20 and 20 against the rule, the omen and gold curves
across **both halves together**, the type and keyword mix, and how many cards start in the shop
rather than the deck. Where it does not add up — 18 taken instead of 20, a pool too small to supply
a deck — it says so and changes nothing.

A built deck is not stored. The whole build lives in the URL, the way a scenario's chosen modules do,
so a deck can be bookmarked, shared and printed without a record of its own:

```
/decks?character=gunslinger&domain=tide&take[undertow]=14&take[swell]=6
```

**Print this deck** carries the same build to `/print/deck`, which lays out the 40 with the character
card, the kit and the upgrades alongside.

### Modules

A module is a themed set of cards dropped into a scenario, in the spirit of Marvel Champions. It owns
its own entity and board cards, carries a **set icon** printed on every one of them so the decks can
be separated after play, and lists the scenarios it suits. A scenario says how many modules a play
asks for and which it recommends.

`/scenarios/{slug}/deck` assembles a deck from a scenario and the modules picked for it, and reports
the card total, omen curve, arrow mix, type counts and where each card came from. Cards a story beat
shuffles in later are listed apart from the starting deck. Picking a module the scenario does not
list warns rather than refuses.

## Tests

```bash
php artisan test
```

Covers the import/export round trip against the real design folder, the card editor's rules
(layout switching, X-cost, arrows, faces), the character editor and the player deck maths, the rules
and config editors, and the print layout maths. Run on PHP 8.4 and 8.5.

## If something goes wrong

**`composer install` refuses to install the lock file on your PHP version.** If it names PHP
`>= 8.4.1`, that is the floor: the Symfony components in the lock need it, and PHP 8.2 and 8.3
cannot run this. Otherwise a single dependency has declared it does not support your PHP —
`composer update <the package it named> -W` refreshes it, and the lock file should then be
committed so nobody else hits it.

**Blank page, or the browser console says `Cannot read properties of null`.** Blade caches the
compiled root view, and a copy from an older Inertia version renders markup the current client
cannot read. `php artisan view:clear` fixes it. `./setup` does this for you.

**`'vite' is not recognized` / `Failed to open stream: vendor/autoload.php`.** The install did not
finish, so `node_modules` and `vendor` are missing. Run `./setup` again and read its first error.
