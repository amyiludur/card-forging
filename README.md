# Card Forge

The design platform for the card game in [`design/`](design/): a cooperative game where players
face an **entity** through a story of set-aside beats, and **omen** is the escalation engine.

It does three things, which are the three things the designer asked for:

1. **Create and edit cards** — entity deck cards, module cards, board cards and story beats.
2. **Create and edit the rules** — the rulebook markdown and the tunable numbers behind it.
3. **Print the cards** — print-ready sheets and a PDF, at real card sizes with crop marks and bleed.

Plus two tools for testing the design: a **deck assembly** view that builds a scenario's deck from
the modules chosen for a play, and a **storyline preview** that lays cards out in a row and shows
which half of each split card resolves.

## Running it

```bash
git clone https://github.com/amyiludur/card-forging.git
cd card-forging
./setup            # installs everything, creates the database, imports design/
composer dev       # starts the app, rebuilding assets as you edit
```

Then open **http://localhost:8000**.

Needs PHP with `pdo_sqlite`, Composer, and Node 20 or newer. The test suite runs on **PHP 8.2
through 8.5**. On Windows run `./setup` from **Git Bash** or WSL; PowerShell and cmd cannot run it.

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

`design/` holds the rules markdown and the scenario JSON, and it is what git tracks. The database
is the editor's working copy. Two commands move between them:

```bash
php artisan design:import   # design/ -> database  (safe to re-run; matches on name/slug)
php artisan design:export   # database -> design/  (then commit design/)
```

So the loop is: import, edit in the browser, export, commit. Version history comes from git, and
the rules editor also keeps its own per-document history inside the app for quick undo.

## Markup in card and rules text

Two plain-text tokens, typed by hand or inserted from the editor's palette:

| Token | Renders as |
|---|---|
| `{omen}` `{gold}` `{damage}` `{dread}` `{health}` | the icon |
| `{config:startingOmen}` | the current value of that tunable number |

A config reference means changing a number in **Tunable numbers** changes every card and every
paragraph that reads it. Values still marked *placeholder* render underlined, so a draft looks
like a draft everywhere it appears.

The print options also offer **auto icons**, which turns "2 omen" into "2 ◆" at print time without
touching the stored text.

## Printing

`/print/{scenario}` lays out a deck and shows what will come off the printer:

- Entity deck, board cards or story beats, each as its own deck. Cards print one copy per quantity.
- Poker (63.5 × 88.9 mm), bridge, tarot, square, or a custom size in millimetres.
- A4 or Letter, with the grid computed from the card size, margin, gutter and bleed. If the cards
  do not fit, the page says so rather than silently cropping them.
- Crop marks in the sheet margin, clear of the neighbouring cards.
- Optional card backs on alternating sheets, row-mirrored so a long-edge duplex flip lines up.

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
| `RuleDocument` + `RuleDocumentVersion` | the rulebook markdown with history |

An entity or board card belongs to a scenario's base deck **or** to a module, never both.

Character and player cards are not designed yet, so they have no tables. They slot in beside
`EntityCard` when they are.

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
(layout switching, X-cost, arrows, faces), the rules and config editors, and the print layout maths.
Run on PHP 8.2 through 8.5.

## If something goes wrong

**`composer install` refuses to install the lock file on your PHP version.** A dependency has
declared it does not support that PHP. `composer update <the package it named> -W` refreshes it;
the lock file should then be committed so nobody else hits it.

**Blank page, or the browser console says `Cannot read properties of null`.** Blade caches the
compiled root view, and a copy from an older Inertia version renders markup the current client
cannot read. `php artisan view:clear` fixes it. `./setup` does this for you.

**`'vite' is not recognized` / `Failed to open stream: vendor/autoload.php`.** The install did not
finish, so `node_modules` and `vendor` are missing. Run `./setup` again and read its first error.
