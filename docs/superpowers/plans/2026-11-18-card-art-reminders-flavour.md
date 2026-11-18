# Card Art, Keyword Reminders and Flavour Text Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Let the designer upload a picture for any card that has an editor and print it in a window they can size. Print each keyword's reminder text on the sheet wherever it fits. Give player, board, entity and town cards a flavour line, printed after the rules text.

**Architecture:** Picture files live in `design/art/<kind>/`, and the card's row stores the path, the window size, crop-or-fit and a focus point. `App\Support\CardArt` (PHP) and `resources/js/cardArt.js` are the two halves of the art rules. The print sheet inlines each distinct picture once, as a CSS class with a `data:` URI. Reminders come from a new `keywords.reminder` field, falling back to `description`. The server attaches them to each text block, and an inline script on the sheet keeps each one only if the card does not overflow with it.

**Tech Stack:** Laravel 11 / PHP 8.4+, Vue 3 + Inertia 3, Blade print views rendered by headless Chromium, PHPUnit.

**Spec:** `docs/superpowers/specs/2026-11-18-card-art-reminders-flavour-design.md`

## Global Constraints

- Every change to a card face goes into **both** halves: `resources/js/Components/CardPreview.vue`, and `resources/views/print/partials/card.blade.php` with `card-css.blade.php`.
- `design:export` writes `art`, `flavour` and `reminder` **only when they are set**. A design folder with none of them must export byte for byte.
- The print sheet and the pocket page carry no `<link>` and no `@font-face`. Every picture is a `data:` URI in the inline `<style>`.
- The art kinds are exactly `entity`, `board`, `town`, `player`, `character`, `beat` and `tale`. The setup card gets no art.
- The window defaults to 40%. An entity card always has a window (`art_size ?? 40`). A portrait card has one only when it has art or a size has been set (`art_size ?? (art ? 40 : 0)`). An `art_size` of 0 hides the window.
- `art_fit` is `cover` or `contain`, default `cover`. `art_focus` is `[x, y]`, each 0–100, default `[50, 50]`.
- Uploads must be PNG, JPEG or WebP, at most 8 MB (`max:8192`).
- Flavour is plain text, not markup. It is the **last thing in `.card-body`**, after every text block and its reminders, on every card kind that has flavour, beat and Tale cards included. The character's identity line stays under the head.
- Reminders appear only on the print sheet. `CardPreview` and the pocket page show none.
- Report, don't correct. A missing art file is reported and never cleared, and removing a card's art never deletes the file.
- **Do not commit.** The working tree already holds the designer's uncommitted work in files this plan touches (`ImportDesign.php`, `Markup.php`, `markup.js`, `Cards/Form.vue`, `PlayerCards/Form.vue`, `CLAUDE.md`). Each task ends at green tests, and the designer commits.
- **Never run the whole suite.** The `DesignFolder` tests touch the real git repository, so always run `php artisan test --filter=…`.
- No test may write into the real `design/` folder. Art tests point `cardforge.design_path` at a temp directory.

## Review Focus

1. **A card name with nothing to slug** (`"???"`, emoji only). Uploading art for it should store `art/<kind>/card.png`, not `art/<kind>/.png`. The test goes in Task 2.
2. **A path-traversal request.** `GET /art/../../.env` and `GET /art/%2e%2e/.env` should 404, never return the file. The test goes in Task 2.
3. **A card whose art file was deleted from disk.** It should still print: the blank tinted window, the card named in the sheet note, and nothing thrown. The test goes in Task 5.
4. **Twenty copies of a card, or two cards sharing one picture.** The sheet should hold exactly one `data:` URI for that file. The test goes in Task 5.
5. **A reminder whose own text uses a keyword, or a card using `{fastplay:2}` twice.** Each keyword should be reminded once per card, and the reminder text never scanned for more reminders. The test goes in Task 6.

---

### Task 1: Schema, config and the `HasArt` model trait

**Files:**
- Create: `database/migrations/2026_11_18_000001_add_art_flavour_and_reminders.php`
- Create: `config/cardforge.php`
- Create: `app/Models/Concerns/HasArt.php`
- Modify: `app/Models/{EntityCard,BoardCard,TownAction,PlayerCard,Character,StoryBeat,TaleCard}.php` to use `HasArt`, and add `flavour` to `$fillable` on `EntityCard`, `BoardCard`, `TownAction` and `PlayerCard`
- Modify: `app/Models/Keyword.php`: `reminder` becomes fillable and goes into `markupMap()`
- Modify: `app/Console/Commands/ImportDesign.php:86` and `ExportDesign.php:39`, to take the default path from config
- Test: `tests/Feature/CardArtModelTest.php`

**Interfaces:**
- Produces these columns:
  - On all 7 tables: `art` (string, nullable), `art_size` (unsigned tinyint, nullable), `art_fit` (string(10), nullable) and `art_focus` (json, nullable).
  - On `entity_cards`, `board_cards`, `town_actions` and `player_cards`: `flavour` (text, nullable).
  - On `keywords`: `reminder` (text, nullable).
- Produces `config('cardforge.design_path')` = `env('CARDFORGE_DESIGN_PATH', base_path('design'))`.
- Produces the trait `HasArt`. Its `initializeHasArt(): void` merges the four art fields into the fillables, and casts `art_size` to `integer` and `art_focus` to `array`.
- Produces a `'reminder' => $k->reminder` entry in each keyword of `Keyword::markupMap()`.

- [ ] **Step 1: Write the failing tests.**
  - `test_every_art_kind_stores_and_casts_its_art()`: on one row of each of the 7 models, save `art='art/x/a.png'`, `art_size=55`, `art_fit='contain'` and `art_focus=[10, 90]`. Assert `fresh()->art_focus === [10, 90]` and `fresh()->art_size === 55`.
  - `test_flavour_is_fillable_on_the_four_new_kinds()`: the same, with `flavour='Salt and silence.'`.
  - `test_a_keyword_carries_its_reminder_into_the_markup_map()`: `Keyword::markupMap()['unique']['reminder'] === 'One per deck.'`.
- [ ] **Step 2: Run** `php artisan test --filter=CardArtModelTest`. Expected: FAIL on unknown columns.
- [ ] **Step 3: Write the migration, the config, the trait and the model changes.** The migration's `down()` drops the columns. Change the import and export default path to `config('cardforge.design_path')`, and keep `--path` as the override.
- [ ] **Step 4: Run** `php artisan test --filter="CardArtModelTest|DesignImportTest|DesignExportTest"`. Expected: PASS.

---

### Task 2: `CardArt` — storing, resolving, uploading and serving pictures

**Files:**
- Create: `app/Support/CardArt.php`
- Create: `app/Http/Controllers/ArtController.php`
- Modify: `routes/web.php` (two routes)
- Create: `resources/js/cardArt.js`
- Test: `tests/Unit/CardArtTest.php`, `tests/Feature/ArtUploadTest.php`

**Interfaces:**
- Consumes: the Task 1 columns and config.
- Produces in `App\Support\CardArt`:
  - Constants: `KINDS`, `DEFAULT_SIZE = 40`, `FITS = ['cover', 'contain']`, and `MIMES = ['png' => 'image/png', 'jpg' => 'image/jpeg', 'jpeg' => 'image/jpeg', 'webp' => 'image/webp']`.
  - `static designPath(): string` — reads the config.
  - `static windowSize(?string $art, ?int $size, bool $landscape): int`.
  - `static absolute(?string $art, ?string $designPath = null): ?string` — the real file path. It returns null when the path is empty, the file is missing, or the path resolves outside `<designPath>/art` (checked with `realpath` and a prefix comparison).
  - `static store(UploadedFile $file, string $kind, string $name): string` — creates the directory and returns `art/<kind>/<slug>.<ext>`. The slug is `Str::slug($name) ?: 'card'`; a name already taken becomes `<slug>-2`, `-3` and so on.
  - `static cssClass(string $art): string` — `'art-'.substr(sha1($art), 0, 12)`.
  - `static dataUri(string $art): ?string` — null when `absolute()` is null.
  - `static present(Model $card, bool $landscape): array` — returns `art`, `art_size` (raw), `art_window` (the resolved int), `art_fit` and `art_focus` (with defaults applied), `art_missing` (true when a path is set and no file exists) and `art_class` (string or null).
  - `static rules(): array` — the validation rules:
    - `art`: nullable string, max:255, must start with `art/`
    - `art_size`: nullable integer, 0–90
    - `art_fit`: nullable, one of `FITS`
    - `art_focus`: nullable array, size:2, and `art_focus.*` an integer 0–100
- Produces two routes:
  - `POST /art` (`art.store`). It takes `file`, `kind` (one of `KINDS`) and `name`, and returns JSON `{path, url}`.
  - `GET /art/{path}` (`art.show`, `where('path', '.*')`). It streams the file with its MIME type, or returns 404.
- Produces in `resources/js/cardArt.js`, following the same rules as the PHP half:
  - `DEFAULT_SIZE`
  - `windowSize(card, landscape)`
  - `artUrl(art)` — `/art/` followed by the part of the path after `art/`.
  - `artStyle(card)` — `{ backgroundImage, backgroundSize, backgroundPosition }`, or `{}` when there's no art.

- [ ] **Step 1: Write the failing unit tests.**
  - `windowSize`: `(null, null, true) === 40`, `(null, null, false) === 0`, `('art/a.png', null, false) === 40`, `(null, 25, false) === 25` and `('art/a.png', 0, true) === 0`.
  - `absolute()` returns null for `art/../../.env`, for `../secret.png`, and for a path that does not exist.
  - `cssClass` returns the same class for the same path every time.
- [ ] **Step 2: Write the failing feature tests in `ArtUploadTest`.** `setUp` points `cardforge.design_path` at `sys_get_temp_dir().'/cardforge-art-'.uniqid()`, and `tearDown` removes it.
  - `test_an_upload_lands_in_the_design_folder_under_its_kind()`: post `UploadedFile::fake()->image('x.png')` with kind `player` and name `Lucky Coin`. Assert the returned `path === 'art/player/lucky-coin.png'` and that the file exists.
  - `test_a_second_upload_of_the_same_name_is_numbered()`: the second upload returns `lucky-coin-2.png`.
  - `test_a_name_with_nothing_to_slug_is_called_card()`: the name `???` gives `art/player/card.png`. *(Review Focus 1)*
  - `test_it_refuses_a_file_that_is_not_an_image_or_too_big()`: a `.txt` file and a 9000 KB image both get a 422.
  - `test_it_refuses_an_unknown_kind()`: 422.
  - `test_it_serves_a_stored_picture()`: 200, with `Content-Type: image/png`.
  - `test_it_will_not_serve_outside_the_art_folder()`: `GET /art/../../.env` and `GET /art/%2e%2e/%2e%2e/.env` both 404. *(Review Focus 2)*
- [ ] **Step 3: Run** `php artisan test --filter="CardArtTest|ArtUploadTest"`. Expected: FAIL.
- [ ] **Step 4: Implement `CardArt`, `ArtController`, the routes and `cardArt.js`.**
- [ ] **Step 5: Run the same tests.** Expected: PASS.

---

### Task 3: The controllers accept art and flavour, and the presenter sends them

**Files:**
- Modify: the validation arrays (`validated()` or `validate()`) in `BoardCardController`, `TownActionController`, `EntityCardController`, `PlayerCardController`, `CharacterController`, `StoryBeatController` and `TaleCardController`. Each spreads `...CardArt::rules()`. The first four also add `'flavour' => ['nullable', 'string', 'max:500']`.
- Modify: the duplicate actions, `EntityCardController::duplicate` and `PlayerCardController::duplicate`, so a copy keeps the art columns. Check first: they already do if they use `replicate()`.
- Modify: `app/Support/CardPresenter.php`.
  - `entityCard` spreads `CardArt::present($card, true)`.
  - `boardCard`, `playerCard`, `character`, `townAction`, `storyBeat` and `taleCard` spread `CardArt::present($x, false)`.
  - The four new flavour kinds add `'flavour' => $x->flavour`.
- Test: `tests/Feature/CardArtEditorTest.php`

**Interfaces:**
- Consumes: `CardArt::rules()` and `CardArt::present()` from Task 2.
- Produces: every presented card except the setup card carries `art`, `art_size`, `art_window`, `art_fit`, `art_focus`, `art_missing` and `art_class`. Entity, board, player and town cards also carry `flavour`.

- [ ] **Step 1: Write the failing tests.** Run `importDesignWithoutDomains()` first.
  - `test_a_board_card_saves_its_art_and_flavour()`: PUT `/board-cards/{id}` with the four art fields and `flavour`, then assert they're stored. Write the same test for a town district, an entity card (PUT `/cards/{id}` with its required fields) and a player card.
  - `test_art_settings_out_of_range_are_refused()`: `art_size=95`, `art_fit='stretch'`, `art_focus=[50]` and `art='../x.png'` each fail validation.
  - `test_the_presenter_resolves_the_window()`: an entity card with no art has `art_window` 40; a board card with no art has `art_window` 0.
  - `test_a_duplicated_card_keeps_its_picture()`.
- [ ] **Step 2: Run** `php artisan test --filter=CardArtEditorTest`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test --filter="CardArtEditorTest|EntityCardEditorTest|ScenarioEditorTest|CharacterTest|TaleCardTest|HirelingTest"`. Expected: PASS, apart from the failures CLAUDE.md already lists as depending on this week's draft.

---

### Task 4: Art, flavour and reminders survive the design folder round trip

**Files:**
- Modify: `app/Support/CardArt.php`, adding `forDesign(Model $card): array` and `fromDesign(mixed $value): array`.
- Modify: `app/Console/Commands/ExportDesign.php`.
  - Spread `CardArt::forDesign($x)` into `entityCard()`, both board-card maps (scenario and module), `townAction()`, `playerCard()`, `character()`, the beat map and the Tale card map.
  - The four new flavour kinds write `'flavour'` only when it is neither null nor empty.
  - `keywords()` writes `'reminder'` only when it is set.
- Modify: `app/Console/Commands/ImportDesign.php`.
  - At every creation site, spread `...CardArt::fromDesign($card['art'] ?? null)`. The sites are: entity cards at :351 and :814, board cards at :382 and :855, districts at :397 and :869, player cards at :724, characters at :622, beats at :790 and Tale cards at :480.
  - The four new flavour kinds read `'flavour' => $card['flavour'] ?? null`, and keywords read `'reminder'`.
  - After each card, if `art` is set and `CardArt::absolute($art, $path)` is null, warn: `"  {name}: art \"{art}\" is not in the design folder; kept as written."`
- Test: `tests/Feature/ArtDesignRoundTripTest.php`

**Interfaces:**
- `forDesign()` returns:
  - `[]` when `art` is null;
  - `['art' => 'art/…png']` when every other setting is at its default (size null, fit null or `cover`, focus null or `[50, 50]`);
  - otherwise `['art' => ['file' => …, 'size'?, 'fit'?, 'focus'?]]`, listing only the settings that differ from the default, in that order.
- `fromDesign()` turns either form back into the four columns, and turns null into four nulls.

- [ ] **Step 1: Write the failing tests.**
  - `test_a_folder_with_no_art_flavour_or_reminders_exports_byte_for_byte()`: import `design/`, export to a temp path and compare every file. The existing round-trip tests already follow this pattern; reuse it.
  - `test_art_with_only_a_file_writes_a_string()`.
  - `test_art_with_settings_writes_an_object_of_what_was_set()`: size 30 and focus [20, 80] write `{"file":…, "size":30, "focus":[20,80]}`, with no `fit`.
  - `test_art_flavour_and_reminder_survive_export_then_import()`: set them on a board card, a player card, an entity card and a keyword. Export to a temp path, wipe the database, import from the temp path, and assert they come back equal.
  - `test_art_naming_a_missing_file_is_imported_as_written_and_warned()`: `expectsOutputToContain('is not in the design folder')`.
- [ ] **Step 2: Run** `php artisan test --filter=ArtDesignRoundTripTest`. Expected: FAIL.
- [ ] **Step 3: Implement.**
- [ ] **Step 4: Run** `php artisan test --filter="ArtDesignRoundTripTest|DesignExportTest|DesignImportTest|ColourDesignRoundTripTest|PlayerDesignRoundTripTest|TaleDesignRoundTripTest|DomainDesignRoundTripTest|KeywordTest"`. Expected: PASS.

---

### Task 5: The card faces, in both halves — the art window, and flavour after the rules

**Files:**
- Modify: `resources/views/print/partials/card-css.blade.php`.
  - `.entity-art` gains `background-color: #eeebe4; background-repeat: no-repeat;`, and its basis comes from an inline style.
  - New class: `.card-art { flex: 0 0 auto; border-bottom: 0.25mm solid #1c1917; background-color: #eeebe4; background-repeat: no-repeat; }`, sized by an inline `height: <n>%`.
  - `.beat-flavour` stays, for the character's identity line only.
  - New class: `.flavour`, in the same italic serif 7pt `#57534e`, with `padding: 0 2mm 1.4mm`.
- Modify: `resources/views/print/partials/card.blade.php`. When `$options->showArt` is true:
  - An entity card keeps `.entity-art`, with `flex-basis: {art_window}%` and the class `art_class`. It draws no window when `art_window` is 0.
  - Board, player, character, town, beat and Tale cards draw `<div class="card-art {art_class}" style="height:{art_window}%; background-size:{fit}; background-position:{x}% {y}%">` straight after `.card-head`, but only when `art_window > 0`.
- Also in `card.blade.php`: `flavour` goes after the last text block inside `.card-body` on entity, board, player, town, beat and Tale cards. Beat and Tale flavour moves down from above the body.
- Modify: `app/Support/PrintOptions.php`, adding the constructor parameter `public bool $showArt = true`. It is not read from the request or written to the query; the sheet always shows art.
- Modify: `app/Http/Controllers/Concerns/PrintsItems.php::sheetFor()`. After the cards are rendered, collect the distinct `art` paths and pass two things to the view:
  - `$artCss` — one `.art-xxxx { background-image: url(data:…) }` line for each path whose `dataUri()` is not null;
  - `$missingArt` — the names of the cards whose `art_missing` is true.
- Modify: `resources/views/print/sheet.blade.php`. Print `{!! $artCss !!}` inside `<style>`, and add `· art missing for: {names}` to `.sheet-note`, with the class `warn`.
- Modify: `app/Support/Pocket.php`, or wherever the pocket page builds its `PrintOptions`, to pass `showArt: false`.
- Modify: `resources/js/Components/CardPreview.vue`, mirroring all of the above.
  - Use `windowSize` and `artStyle` from `cardArt.js`, and `artUrl()` for the image.
  - Add `.card-art` to the scoped CSS.
  - Move `.card-flavour` to the end of the body on the beat and Tale branches, and add it to the entity branch (end of the rules body) and to the board, player and town branches.
- Test: `tests/Feature/CardArtPrintTest.php`

**Interfaces:**
- Consumes: the `CardArt::present()` output from Task 3; `CardArt::dataUri()` and `cssClass()` from Task 2; `windowSize`, `artStyle` and `artUrl` from Task 2's `cardArt.js`.

- [ ] **Step 1: Write the failing tests.** Use the temp design path from Task 2, with a fake PNG stored through `CardArt::store`.
  - `test_a_picture_prints_as_one_data_uri_however_many_copies()`: a board card with qty 3 and a second board card using the same file. Assert `substr_count($html, 'data:image/png;base64,') === 1` and that `.card-art art-` appears 4 times. *(Review Focus 4)*
  - `test_the_sheet_still_loads_nothing_from_outside()`: no `<link`, no `@font-face` and no `/art/` URL.
  - `test_a_portrait_card_without_art_draws_no_window_and_an_entity_card_does()`.
  - `test_a_missing_picture_prints_a_blank_window_and_is_named()`: art set, then the file deleted. The sheet returns 200, has no `data:` for that card, and names the card in `.sheet-note`. *(Review Focus 3)*
  - `test_flavour_prints_after_the_rules_text()`: in a board card's HTML, `strpos('class="flavour"') > strpos('class="effect"')`. Do the same for a beat card and a Tale card.
  - `test_the_pocket_page_draws_no_art()`: no `card-art` and no `data:image`. Use `str_contains` with a message, never `assertStringContainsString`; see CLAUDE.md.
- [ ] **Step 2: Run** `php artisan test --filter=CardArtPrintTest`. Expected: FAIL.
- [ ] **Step 3: Implement the print half.**
- [ ] **Step 4: Run** `php artisan test --filter="CardArtPrintTest|PrintTest|PlayerPrintTest|DomainPrintTest|TalePrintTest|PocketTest|PrintPoolTest"`. Expected: PASS, apart from the known draft-dependent town assertion in `PrintTest`.
- [ ] **Step 5: Implement the `CardPreview.vue` half, and run** `npm run build`. Expected: the build succeeds.
- [ ] **Step 6: Compare the two halves.** In the running app (`composer dev`), give one board card and one entity card a picture through tinker. Compare the preview on the scenario page with `/print/{slug}/sheet?deck=board` and `?deck=entity`. The window size, the crop and the flavour at the bottom should match.

---

### Task 6: Keyword reminders, printed where they fit

**Files:**
- Modify: `app/Support/Markup.php`, adding `reminders(string $text, array &$seen = []): array`.
- Modify: `app/Support/CardPresenter.php`, which attaches the reminders to each text block (shapes below), keeping one `$seen` per card.
- Modify: `app/Support/PrintOptions.php`, adding `public bool $reminders = true`. `fromRequest` reads `$request->has('reminders') ? $request->boolean('reminders') : true`, and the query or array output writes `'reminders' => $this->reminders`.
- Create: `resources/views/print/partials/reminders.blade.php`, which prints `<div class="reminder" hidden>(…)</div>` for each reminder, but only when `$options->reminders && $options->showArt`. The `showArt` check is what keeps reminders off the pocket page.
- Modify: `card.blade.php`, to include that partial after each text block.
- Modify: `card-css.blade.php`, adding `.reminder { font-size: 6.5pt; font-style: italic; line-height: 1.25; color: #57534e; margin-top: 0.8mm; }`.
- Modify: `resources/views/print/sheet.blade.php`, adding the fit script before `</body>`.
- Modify: `resources/js/Pages/Print/Options.vue`, adding a *Reminder text where it fits* checkbox, bound the same way as `show_placeholders`.
- Modify: `app/Http/Controllers/KeywordController.php` (validation: `reminder` is a nullable string, max:300) and `resources/js/Pages/Rules/Keywords.vue` (a *Reminder* field under *Description*, in both the add row and the edit rows).
- Test: `tests/Unit/MarkupTest.php` (extend it), `tests/Feature/KeywordReminderTest.php`

**Interfaces:**
- `Markup::reminders()` returns `list<array{token: string, html: string}>`.
  - It returns one entry for each keyword token in `$text` that isn't already in `$seen`, in order of first use, and adds each token to `$seen`.
  - The html is `'<b>'.e(name).'</b>: '.$this->toHtml(reminder ?? description)`.
  - A keyword with neither a reminder nor a description is skipped.
  - The reminder text is rendered with `toHtml` and is **never** passed back through `reminders()`.
- The presenter attaches reminders in these shapes:
  - Entity cards: `faces[i].reminders`.
  - Player, board, town and Tale cards: `reminders`. A town district reads its `effect`.
  - Characters: `reminders` for the identity ability, and `extra_abilities[i].reminders`.
  - Beats: `reminders => ['on_reach' => […], 'advance' => […], 'on_advance' => […]]`.
- The fit script is written out in full here, because the measuring is the whole point of it:

```html
<script>
document.querySelectorAll('.card').forEach(function (card) {
    var bodies = card.querySelectorAll('.card-body');
    var overflows = function () {
        return Array.prototype.some.call(bodies, function (b) { return b.scrollHeight > b.clientHeight + 0.5; });
    };
    card.querySelectorAll('.reminder').forEach(function (r) {
        r.hidden = false;
        if (overflows()) r.hidden = true;
    });
});
</script>
```

- [ ] **Step 1: Write the failing unit tests in `MarkupTest`.**
  - `test_reminders_come_in_order_of_first_use_once_each()`: the text `{carve} then {break} then {carve}` gives the tokens `['carve', 'break']`.
  - `test_a_reminder_falls_back_to_the_description()`.
  - `test_a_numbered_keyword_is_reminded_once()`: `{fastplay:2} … {fastplay:2}` gives one reminder.
  - `test_reminder_text_is_not_scanned_for_more_reminders()`: a reminder whose text contains `{unique}` gives one entry, not two. *(Review Focus 5)*
  - `test_seen_carries_across_blocks()`.
- [ ] **Step 2: Write the failing feature tests in `KeywordReminderTest`.**
  - `test_a_keyword_saves_its_reminder()`: PUT `/rules/keywords/{id}`.
  - `test_the_sheet_prints_reminders_hidden_with_the_fit_script()`: for a player card using `{fastplay}`, the sheet contains `class="reminder" hidden`, the reminder's words, and `card.querySelectorAll('.reminder')`.
  - `test_reminders_can_be_turned_off()`: with `?reminders=0`, there is no `class="reminder"`.
  - `test_the_pocket_page_prints_no_reminders()`.
  - `test_a_split_card_reminds_each_keyword_once()`: `{carve}` in both halves gives one reminder.
- [ ] **Step 3: Run** `php artisan test --filter="MarkupTest|KeywordReminderTest|KeywordTest"`. Expected: FAIL.
- [ ] **Step 4: Implement, and run** `npm run build`.
- [ ] **Step 5: Run the same tests, adding `PrintTest|PrintOptionsTest` to the filter.** Expected: PASS.
- [ ] **Step 6: Check the fit in Chromium.** Open `/print/kraken/sheet?deck=all` in the browser, and make a PDF from `/print/kraken/pdf` if Chromium is installed. A card with short text should show its reminder; a card full of text should not; and no card's text should be clipped.

---

### Task 7: The editors — an art field, and flavour

**Files:**
- Create: `resources/js/Components/ArtField.vue`
- Modify these editors:
  - `resources/js/Pages/Cards/Form.vue` (entity): art, with `landscape`, and flavour.
  - `resources/js/Components/BoardCardRow.vue`, plus the new-board-card forms in `Scenarios/Show.vue` and `Modules/Show.vue`: art and flavour.
  - `TownActionRow.vue`, plus the new-district forms: art and flavour.
  - `resources/js/Pages/PlayerCards/Form.vue`: art and flavour.
  - `resources/js/Pages/Characters/Form.vue`: art.
  - `resources/js/Components/BeatRow.vue`: art.
  - `resources/js/Components/TaleCardRow.vue`: art.
- In each editor, the `useForm` gains `art`, `art_size`, `art_fit` and `art_focus`, plus `flavour` where it applies, all filled in from the card.

**Interfaces:**
- `ArtField.vue` takes the props `kind` (one of `CardArt::KINDS`), `name` (a string, used for the file name) and `landscape` (bool), and has the v-models `art`, `size`, `fit` and `focus`. It does this:
  - **Upload:** `window.axios.post('/art', FormData)`, then sets `art` from the response's `path`. A 422 is shown inline.
  - **Remove:** sets `art` to null. It does not delete the file.
  - **Size:** a number input, where blank means the default. Its placeholder shows the resolved default from `windowSize`.
  - **Fill:** a two-way toggle between *Crop to fill* and *Show whole*.
  - **Focus:** clicking the thumbnail sets `[round(x%), round(y%)]` and shows a dot there.
  - **Missing file:** it shows "file missing" when `art_missing` was sent for this card.
- Each editor's live preview passes the form's art fields into `CardPreview`'s `card` prop, so the window changes as you edit.

- [ ] **Step 1: Build `ArtField.vue` and wire it into the editors.**
- [ ] **Step 2: Run** `npm run build`. Expected: the build succeeds.
- [ ] **Step 3: Click through it in the running app.** On each of the seven editors:
  - upload a PNG and see it in the preview;
  - change the size to 60, then to 0;
  - switch to *Show whole*;
  - click a focus point;
  - save, reload, and check everything was kept.

  Then remove the art and check the file is still in `design/art/`. This runs against the real design folder, so afterwards delete the test pictures and put the cards back as they were.
- [ ] **Step 4: Run** `php artisan test --filter="EntityCardEditorTest|ScenarioEditorTest|CharacterTest|TaleCardTest|ModuleTest|ModuleTownTest"`. Expected: PASS, apart from the known draft-dependent failures.

---

### Task 8: Unused pictures reported on `/design`, and notes for the next builder

**Files:**
- Modify: `app/Support/CardArt.php`, adding `static orphans(?string $designPath = null): array`. It lists the files under `<designPath>/art` that no row of the 7 models names, as relative `art/…` paths, sorted.
- Modify: `app/Support/DesignFolder.php::status()`, adding `'unused_art' => CardArt::orphans()`.
- Modify: `resources/js/Pages/Design/Index.vue`. Add an "Art no card uses" list, shown only when it isn't empty, worded as a report, with no delete button.
- Modify: `CLAUDE.md`.
  - Add rows to the "Where things live" table for `CardArt.php`, `cardArt.js`, `ArtController.php` and `ArtField.vue`.
  - Add "Things that will bite" entries for:
    - art lives in `design/art`, and the sheet inlines each file once;
    - the window-default rule, which is written in two halves;
    - flavour comes after the rules text on every card kind;
    - reminders are print-only and fitted by the sheet's script, and the pocket page shows none;
    - `cardforge.design_path` exists so tests never write art into the real folder.
- Test: `tests/Feature/ArtDesignRoundTripTest.php` (extend it)

- [ ] **Step 1: Write the failing test** `test_pictures_no_card_uses_are_listed_not_deleted()`. In the temp design path, store two files and point one card at one of them. Assert `orphans() === ['art/board/other.png']`, and that both files still exist.
- [ ] **Step 2: Run** `php artisan test --filter=ArtDesignRoundTripTest`. Expected: FAIL.
- [ ] **Step 3: Implement it, and update `CLAUDE.md`.**
- [ ] **Step 4: Final verification.** Run `php artisan test --filter="CardArt|ArtUpload|ArtDesign|KeywordReminder|MarkupTest|PrintTest|PocketTest|Design(Import|Export)Test|RoundTrip"` and `npm run build`. Expected: everything passes apart from the draft-dependent failures CLAUDE.md lists, and the build succeeds.
