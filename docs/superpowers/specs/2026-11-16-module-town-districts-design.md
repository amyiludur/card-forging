# Module town districts — design

*Approved 2026-11-16. Small change.*

## Intent

The designer is writing a module, *Revolt of the Peasants*, that adds a **Farm** district to the
town. Today a module can hold entity cards and board cards only; the town belongs to the scenario.

## Decided by the designer

- A module's district **joins** the town when the module is chosen. It does not replace one.
  (This is the designer's answer to part of open question 7, for this case. The rules file is not
  edited unless they ask.)

## Approaches

1. **A district has an owner, scenario or module** — `town_actions.module_id` nullable beside a
   now-nullable `scenario_id`, exactly as `entity_cards` and `board_cards` already do. **Picked**:
   one district shape everywhere, so the editor, the card face, print and the table need no second
   kind.
2. A separate `module_districts` table — rejected, two kinds of district for every reader.
3. A JSON list on `modules` — rejected, same reason, and no row editor to reuse.

## Design

- **Data:** migration makes `town_actions.scenario_id` nullable and adds `module_id` (cascade on
  module delete). `TownAction::module()`, `Module::townActions()`.
- **Module page:** a Town section like the scenario's Town tab — `TownActionRow` per district and
  an add form, posting to `POST modules/{module}/town-actions`.
- **Card face:** `CardPresenter::townAction()` carries `set_icon` off the module; the town face in
  the print partial and in `CardPreview.vue` prints it in the foot (two halves, one rule).
- **Print:** `moduleItems()` includes the module's town cards; `PrintCatalogue` names the owner
  as the module when there is no scenario. Pocket lists them under the module.
- **Playtest table:** `town` is the scenario's districts, then the chosen modules' (builder's
  call: after the scenario's own).
- **Design folder:** a module file writes `"town"` only when the module has a district, so the
  existing module files export byte for byte; import reads it and prunes districts the file dropped.
- **Tests:** create/edit a module district, print it, export/import round trip, existing module
  files unchanged, table includes it only when chosen.

## Not in scope

Module story beats, a module setup card, module-owned card types.
