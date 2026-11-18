# Card art, keyword reminders and flavour text

## Why

The designer wants the printed cards to carry real art: partly to make playtest copies feel like
the finished game, partly to try out an art direction. They also want printed cards to explain
their keywords when there is room, and a flavour line on the card kinds that have none. All three
are tool work. None of them decides anything about the game.

## Goals

1. **Art on every card that has a row.** Upload a picture in a card's editor and it prints on the
   card. Entity cards keep their landscape window on the left. Portrait cards get a window across
   the top, under the head.
2. **Reminder text that appears only where it fits.** On the print sheet, a card's keywords get a
   short italic reminder after the text they appear in. A reminder is kept only if the card does not
   overflow with it in.
3. **A flavour line** on player, board, entity and town cards, the kinds that lack one today.

## Non-goals and anti-goals

- No art on the **setup card**. It is a list of steps and has no card row of its own. *(builder's
  call; easy to add later)*
- **The pocket page shows no art.** It is one self-contained file read on a phone, and every
  picture inlined would make it heavy. Its cards draw no window, so the text keeps the room.
- No image editing: no cropping tool, rotation or filters. Placement is the three settings below.
- **Anti-goal:** pictures that live outside `design/`. The folder is the source of truth, and an
  import deletes whatever it lacks. A picture kept elsewhere would be lost on import or would come
  apart from its card.
- **Anti-goal:** pictures stored as text inside the JSON. Every diff would turn to noise.
- The on-screen preview does **not** show reminder text. Fitting depends on the printed size, so
  the reminders are a property of the print sheet. *(builder's call)*

## Constraints (from the codebase)

- The print sheet is rendered from `file://` by headless Chromium, so nothing can be fetched.
  Every picture has to be inside the page.
- The card design has two implementations: `CardPreview.vue`, and the print partial with
  `card-css.blade.php`. Every change to a card face goes into both.
- `design:export` writes the folder verbatim. A key appears **only when it is set**, so a folder
  with no art and no flavour comes back out byte for byte.
- Placeholders and "report, don't correct" apply as everywhere else: a card pointing at a missing
  file is reported, never silently cleared.
- The full test suite touches the real git repository (`DesignFolder` tests). Run tests with
  `--filter`.

## Approaches considered

**Storage.** (A) Files in `design/art/`, with the card's file pointing at its picture. (B) Base64
inside the card JSON. (C) Laravel public storage. **Picked: A.** B makes every diff unreadable. C
sits outside the design folder, so it breaks the source-of-truth rule and the import would orphan it.

**Fitting reminders.** (A) A small inline script on the print sheet that adds each reminder and
takes it back out if the card overflows. (B) A server-side estimate by character count. **Picked:
A.** It is exact, and Chromium runs it before it prints, so the PDF and the HTML sheet agree. B
guesses wrong around icons and line breaks.

**What a reminder says.** A new optional short `reminder` field on each keyword. When it is empty,
the keyword's full `description` is used. **Picked by the designer.** Their Carve and Break
definitions run to three sentences each.

## Design

### 1. Art

**Data.** Four nullable columns on each of `entity_cards`, `board_cards`, `town_actions`,
`player_cards`, `characters`, `story_beats` and `tale_cards`:

| column | meaning | default when null |
|---|---|---|
| `art` | path relative to `design/`, e.g. `art/player/lucky-coin.png` | no picture |
| `art_size` | window size in percent: width on an entity card, height on a portrait card. 0 hides the window | see below |
| `art_fit` | `cover` (crop to fill) or `contain` (show whole, letterboxed) | `cover` |
| `art_focus` | JSON `[x, y]`, 0–100: the point kept in view when cropping | `[50, 50]` |

**Window default.** Every card draws a 40% window by default, **blank until it has a picture** —
the designer's call (2026-11-18), replacing the earlier "portrait cards only once they have a
picture". An `art_size` that is set always wins, and 0 hides the window on any card.
`App\Support\CardArt` (new) is the one place that rule lives. It is mirrored in
`resources/js/cardArt.js`: two halves, keep in step.

**Where the window sits.** Entity: the existing `.entity-art`, with its `flex-basis` set from
`art_size`. Portrait: a new `.card-art` block between `.card-head` and the body,
`flex: 0 0 <size>%` of the card's inner height, with a bottom border like the entity window's
right border. The picture is drawn as a CSS background: `background-size: cover|contain;
background-position: x% y%`, on the same `#eeebe4` tint the empty window already uses.

**Files.** Uploads go to `design/art/<kind>/<slug>.<ext>`, where kind is one of `entity`, `board`,
`town`, `player`, `character`, `beat` or `tale`, and the slug comes from the card's name. A taken
name gets a number. PNG, JPEG and WebP only, 8 MB cap. *(builder's call on the cap)* Removing a
card's art clears the column and **leaves the file**, because another card or a duplicate may use
it. The `/design` page lists art files that no card references, as a report, never a delete.

**Serving on screen.** `GET /art/{path}` streams from `design/art/` and refuses anything that
resolves outside it. `CardPresenter` adds `art_url`, `art_size` (resolved), `art_fit` and
`art_focus` to every card it presents. `art_missing` is true when the path names no file.
`CardPreview` draws the window from those values.

**Print sheet.** `PrintsItems` collects the distinct art paths on the sheet. Each one goes into the
inline `<style>` once, as `.art-<hash> { background-image: url(data:<mime>;base64,…) }`, and the
card carries the class. Twenty copies of a card cost one image. The existing
"no `<link>`, no `@font-face`" test still passes, and a new test asserts the art is a `data:` URI. A
missing file prints the blank window, and the sheet names the card the way it names placeholders.

**Pocket page.** Passes a flag to the partial, and the partial draws no art window at all.

**Editor.** Each card editor gets an *Art* block: upload or replace, remove, the size field
(blank = default), fill/fit, and a click-on-the-picture focus point. The preview beside the form
updates live. The editors are `Cards/Form.vue` (entity), the board card and town district forms on
the scenario/module pages, `PlayerCards/Form.vue`, the character form, the beat form and the Tale
card form. One shared `ArtField.vue` component serves all of them. Uploading posts the file
straight away to `POST /art` (kind + name) and gets back the path, which the form saves with
everything else. That way an unsaved card can still show its picture.

**Design files.** Each card writes an `"art"` key **only when `art` is set**:
`"art": "art/player/lucky-coin.png"` when nothing else is set. When anything else is set, it writes
an object: `"art": {"file": …, "size": 40, "fit": "contain", "focus": [50, 20]}`, listing only the
keys that are not default. Import reads both forms. A path naming no file is imported as written,
with a warning, and is reported on the card's page. *Commit* on `/design` already commits
everything under `design/`, so the pictures go with their cards.

### 2. Keyword reminders

- `keywords.reminder`, nullable text. It is edited at `/rules/keywords` beside the description,
  travels in `Keyword::markupMap()`, and is exported to `keywords.json` **only when set**.
- `Markup` gains `reminders(string $text): array`. For each distinct keyword the text uses, in order
  of first use, it returns `{token, html}`, where the html is `<b>Name</b>: reminder` (or the
  description when there is no reminder) rendered through the markup, so icons draw. A keyword with
  neither prints no reminder.
- `CardPresenter` attaches the reminders to each text block: each face of an entity card, the
  effect of a player, board or town card, a character's abilities, and a beat's three blocks. A
  keyword is reminded **once per card**, at its first block.
- The partial prints them after their block as
  `<div class="reminder" data-order="n">(…)</div>`: italic, 6.5pt, muted.
- An inline script at the end of `sheet.blade.php` goes through every card's reminders in order.
  It hides each reminder, shows it, and checks whether the card's body or rules column now
  overflows (`scrollHeight > clientHeight + 0.5`). If it does, the reminder goes back to hidden.
  Each reminder is decided greedily, in order.
- Print options get a checkbox, *Reminder text where it fits* (`reminders=1`), which is **on** by
  default and travels in the query string like every other setting. The pocket page prints no
  reminders, because it already has a keyword glossary.

### 3. Flavour text

- A nullable `flavour` text column on `entity_cards`, `board_cards`, `town_actions` and
  `player_cards`. It is plain text, like the beat and Tale flavour, not markup.
- **Flavour goes after the rules text** (the designer's call, 2026-11-18). It is the last thing in
  `.card-body`, under every text block and its reminders, and is printed in the existing italic
  serif style (`.beat-flavour`, renamed `.flavour`). One rule covers every flavour line, so the
  beat and Tale flavour that sits above the body today moves down there too. The character's
  identity line is not flavour and stays under the head. On an entity card, flavour sits at the
  bottom of the rules column's body, under both halves.
- Flavour is never dropped to make room. Only a reminder is dropped, so the fit script measures
  with the flavour already in place.
- Each of those four editors gets a *Flavour* textarea, and the design files write `"flavour"` only
  when it is set.

## Calls made by the builder, for the designer to check

- No art on the setup card.
- ~~A portrait card shows a window only once it has a picture~~ — changed by the designer: every
  card shows a blank 40% window by default.
- Removing art leaves the file, and orphans are reported on `/design`.
- Reminders are print-only and on by default, with a checkbox to turn them off.
- Beat and Tale flavour moves below the rules text too, so there is one rule for every flavour line.
- 8 MB upload cap; PNG, JPEG and WebP only.

## Left to the builder

The exact sizes of the reminder and flavour type; the slug and numbering of art filenames; how the
focus point is picked in the UI; test layout.
