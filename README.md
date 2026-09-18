# Card Forge

The design platform for the card game in [`design/`](design/): a cooperative game where players
face an **entity** through a story of set-aside beats, and **omen** is the escalation engine.

It does three things, which are the three things the designer asked for:

1. **Create and edit cards** — entity deck cards, board cards and story beats.
2. **Create and edit the rules** — the rulebook markdown and the tunable numbers behind it.
3. **Print the cards** — print-ready sheets and a PDF, at real card sizes with crop marks and bleed.

## Running it

```bash
git clone https://github.com/amyiludur/card-forging.git
cd card-forging
./setup            # installs everything, creates the database, imports design/
composer dev       # starts the app, rebuilding assets as you edit
```

Then open **http://localhost:8000**.

Needs PHP 8.2 or newer with `pdo_sqlite`, Composer, and Node 20 or newer — the same things Laravel
11 already wants, so if CardForge runs, this will. `./setup` is safe to re-run: it skips whatever is
already done and never touches your `.env`. To serve what is already built without the asset
watcher, `php artisan serve` is enough.

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
| `EntityCard` + `EntityCardFace` | a deck card and its one or two typed halves |
| `BoardCard` | a board piece: health (free text, so "12 per player" works), traits, text |
| `TownAction` | a town district: effect, gold cost, omen cost |
| `RuleDocument` + `RuleDocumentVersion` | the rulebook markdown with history |

Character and player cards are not designed yet, so they have no tables. They slot in beside
`EntityCard` when they are.

### Arrows on split cards

The designer's decision: **the arrow is printed on the card, and Redirect places a token over it**
to override it in play. So `arrow` is a field on the card (`top` or `bottom`), and the scenario
carries a `printed_arrows` flag in case that changes. A split card with no arrow chosen yet prints
hollow on both halves, and the scenario page lists the ones still waiting on a decision.

## Tests

```bash
php artisan test
```

Covers the import/export round trip against the real design folder, the card editor's rules
(layout switching, X-cost, arrows, faces), the rules and config editors, and the print layout maths.
