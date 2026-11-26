<?php

namespace App\Http\Controllers;

use App\Models\Scenario;
use App\Models\ScenarioTownPick;
use App\Models\TownAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * A scenario picking a common district for its town, and the line it adds to
 * it. The district itself is edited at /town, never here.
 */
class TownPickController extends Controller
{
    public function store(Request $request, Scenario $scenario): RedirectResponse
    {
        $data = $request->validate([
            'town_action_id' => [
                'required', 'integer',
                // Only a common district can be picked: a scenario's or a
                // module's own already has a town to be in.
                Rule::exists('town_actions', 'id')->whereNull('scenario_id')->whereNull('module_id'),
                // A district is in a town once.
                Rule::unique('scenario_town_picks', 'town_action_id')->where('scenario_id', $scenario->id),
            ],
            'extra' => ['nullable', 'string'],
        ], [
            'town_action_id.unique' => 'This town already has that district.',
        ]);

        $scenario->townPicks()->create($data + ['sort' => $scenario->nextTownSort()]);

        $name = TownAction::find($data['town_action_id'])?->name;

        return back()->with('success', "{$name} added to the town.");
    }

    public function update(Request $request, ScenarioTownPick $pick): RedirectResponse
    {
        $data = $request->validate(['extra' => ['nullable', 'string']]);

        $pick->update(['extra' => trim((string) ($data['extra'] ?? '')) === '' ? null : $data['extra']]);

        return back()->with('success', 'Saved.');
    }

    public function destroy(ScenarioTownPick $pick): RedirectResponse
    {
        $name = $pick->townAction?->name;
        $pick->delete();

        return back()->with('success', "{$name} taken out of the town.");
    }
}
