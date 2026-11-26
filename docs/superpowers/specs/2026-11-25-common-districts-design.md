# Common districts — design

*Approved 2026-11-25 ("work on it"). Built the same day; see "As built" at the end for where the
build differs from this draft.*

## Intent

Some districts turn up in more than one town. The Smithy is in both the Kraken and the March of the
Dead Legion, and so is the Chapel, and the two Chapels have already drifted apart. The designer wants
a place to write a **common** district once and have scenarios use it.

## Decided by the designer

- **Each scenario chooses which common districts it wants.** A common district is never in a town
  that did not pick it. This keeps the core rule that "each scenario defines its own town".
- **A scenario can add its own extra line to a common district it picks.** The Kraken's
  "+1 omen while the Whirlpool is in play" is added by the Kraken to the common Chapel, not written
  into the Chapel itself.
- **Approach A** (below).

## Goals

- Write a district once and have every scenario that picks it print and play the same card.
- Let a scenario add its own line to a district it picks.
- A design folder with no common districts exports byte for byte.

## Non-goals

- A scenario changing a common district's gold cost, omen or effect. It can only **add** a line.
  (My call: the designer asked for an extra line, and changing the numbers would make it a
  different district.)
- A module picking common districts. A module still brings its own district, as it does today.
- Reordering a town in the editor. Districts cannot be reordered today; a pick goes on the end,
  the same way a new district does.

## Anti-goals

- **Copying a common district into a scenario.** Editing the Smithy must change every town that
  picks it. That copying is how the two Chapels drifted.
- **A second kind of district.** A common district is a `town_actions` row like any other, so the
  card face, art, `{card:…}` names, printing and the table need no second implementation.

## Approaches

- **A. A common district is a district with no owner.** *Picked.* `town_actions` rows with neither
  `scenario_id` nor `module_id`, the way a shared card type has no `scenario_id`. A scenario's picks
  sit in a pivot table holding the extra line. Everything that shows a town asks one method for it.
- **B. A separate `common_districts` table.** Rejected. It would mean a second card face, a second
  art owner, a second `{card:…}` kind and a second set of print and table code to keep in step.
- **C. Picking copies the district into the scenario.** Rejected. It brings back the drift this is
  meant to stop.

## Design

### Data

- `town_actions` with `scenario_id` and `module_id` both null is a **common district**.
  `TownAction::scopeCommon()` and `TownAction::isCommon()`.
- New table `scenario_town_picks`: `id`, `scenario_id` (cascade), `town_action_id` (cascade),
  `extra` (nullable text: the scenario's own line), `sort` (int). Unique on
  (`scenario_id`, `town_action_id`): a scenario picks a district once.
- `sort` shares one ordering with the scenario's own districts' `sort`, so a town is the two lists
  merged by `sort`. That is how the Kraken keeps its Chapel first and its Smithy last.
- `Scenario::town()` is **the one place a scenario's town is built**: its own districts and its
  picks, merged by `sort`, each pick's district carrying its `extra` (as an unsaved attribute) and
  set against this scenario as its Dread context. Everything that shows a scenario's town asks
  this: the Town tab, the print run, the pocket page, the playtest table, and `CardNames` where it
  needs to.

### The card face

- `CardPresenter::townAction()` takes an optional scenario context and the pick's `extra`. A common
  district's `{dreadRule}`, `{dreadAmount}`, `{omenAmount}` resolve against **the scenario that
  picked it**. On its own (the common district page, its own print run) it has no scenario and
  prints `?dreadRule`, like a module card.
- The extra line prints **after** the district's own note, as a separate line, through the same
  markup. Two halves as ever: the `.town-card` branch of the print partial and `kind === 'town'` in
  `CardPreview.vue`, with the `extra_html` / `extra` prop beside `note_html` / `note`.
  (My call. The Kraken's Whirlpool line sits before the gold line today; after this it comes after.)
- `{this}` names the district, as it does now. Keyword reminders cover the extra line too.
- A common district carries no set icon.

### Editor

- **`/town`**, a new page in the sidebar beside the rules pages: the common districts as
  `TownActionRow`s and an add form, the same fields a scenario's district has, art included.
  Each row says which scenarios pick it. Deleting one says how many towns it leaves, and then
  removes it from them (the pick cascades).
- **The scenario's Town tab** gets a *Common districts* block under its own: every common district
  with a checkbox. A picked one shows its card face, with the extra line on it, and a `MarkupField`
  for that extra line. The town preview above shows the merged town, which is what prints.
- Routes: `POST town` / `PUT town-actions/{townAction}` / `DELETE town-actions/{townAction}` reuse
  `TownActionController` (a `storeCommon` beside `store` and `storeForModule`).
  `POST scenarios/{scenario}/town-picks`, `PUT town-picks/{pick}`, `DELETE town-picks/{pick}` for
  the picks.

### Design folder

- **`design/data/town.json`**: a list of common districts, each the same shape a scenario's
  `"town"` entry has (`name`, `effect`, `goldCost`, `omen`, `note`, `flavour`, `art`). Written only
  when there is at least one, so a folder with none does not grow the file. Same rule as
  `keywords.json`.
- **A scenario's `"town"` list** holds its own districts and its picks, **in town order**. A pick is
  written as:

  ```json
  { "common": "chapel", "extra": "+1 omen while the {card:kraken/whirlpool} is in play." }
  ```

  `common` is the district's key, its name as a slug, the same key `{card:…}` uses. `extra` is
  written only when there is one. A scenario with no picks writes its town exactly as it does now.
- **Import** reads `town.json` before scenarios. It prunes common districts the file dropped, and
  each scenario's picks are replaced by its file's. A pick naming a common district that does not
  exist is **skipped with a warning**, the way a Tale step naming a missing scenario is. Report,
  don't correct.
- **Export** writes the same shapes back, picks in their place in the list.

### Printing, pocket page, playtest table

- **Scenario print run:** the *Town* deck prints `Scenario::town()`, so the Kraken's Chapel prints
  with the Kraken's line. Print keys stay `town:<id>`. A pool can hold one district twice under two
  scenarios, so a pick prints as `town:<id>@<scenario-slug>` (`PrintCatalogue::poolKey()` and
  `parse()` are the one place that is decided). My call; the builder may find a simpler key that
  keeps the scenario.
- **`/print/town`**: the common districts on their own, with no extra lines, for printing the shared set.
- **Pocket page:** a *Common districts* section, once. Each scenario's town section lists its whole
  town, picks with their extra lines.
- **Playtest table:** `town` is `Scenario::town()` then the chosen modules' districts, as today.
  Nothing else changes, since a district is keyed by its id and a pick's id is unique in one town.

### `{card:…}` names

- A common district has no owner, so its key is the bare name: `{card:chapel}`. The picker inserts
  the bare form when the owner is empty. Renaming one follows its references as any rename does.

### Converting the two towns already written

Once this is built, the Smithy and the Chapel become common districts in `design/data/town.json`.
The Kraken and the Legion pick them, and each scenario's own differences move into that
scenario's extra line. **The designer decides what the one Chapel and the one Smithy say** (see the
questions below). Nothing is merged on their behalf.

## Tests

- Create, edit and delete a common district. Deleting it drops it from every town that picked it.
- Pick and unpick a district, with and without an extra line. The merged town keeps `sort` order.
- Dread tokens on a picked district resolve against the picking scenario, and print `?dreadRule`
  on the district's own page.
- The print run, pocket page and table all show the picked district with its extra line.
- Export/import round trip with `town.json` and picks. A folder with no common districts comes
  back byte for byte. A pick naming a missing district is skipped and warned about.
- CLAUDE.md gets a "Things that will bite" entry: `Scenario::town()` is the one place a town is
  built.

## As built

- **The extra line is folded into the presented `note`** in `CardPresenter::townAction()`, rather
  than an `extra_html` prop in both card faces. Both faces already print `note`, so the rule lives
  in one place and neither face changed.
- **No `/print/town` page.** The print pool already lists every common district (source "Common
  district"), and a pick prints into the pool as `pick:<id>` — a `pick` group in `PrintCatalogue`
  — so the `group:id` key shape did not change.
- **Import prunes a scenario's own districts and picks its file dropped.** It did not prune a
  town before; without it, moving the Smithy into the library left the scenario's old copy.
- **The Smithy and the Chapel were converted** with no rule added: the Kraken's Chapel carries
  its Whirlpool line as its extra, the Legion's Smithy carries "Can't be used while burning."
  The Legion's Chapel picks the common Chapel as it stands, which gives it a gold cost of 0
  (it had none written) and moves "+1 per town action used" from its effect to the note, with
  the gold icon the other districts use. Whether the Legion's Chapel should burn is still the
  designer's question.

## Left to the builder

- Where `/town` sits in the sidebar, and the exact look of the *Common districts* block.
- Whether the pick's extra line travels as an attribute on the district or as a small value object.
