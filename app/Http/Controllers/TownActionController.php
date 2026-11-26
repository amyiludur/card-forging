<?php

namespace App\Http\Controllers;

use App\Support\CardArt;
use App\Models\Module;
use App\Models\Scenario;
use App\Models\TownAction;
use App\Support\CardPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class TownActionController extends Controller
{
    public function store(Request $request, Scenario $scenario): RedirectResponse
    {
        $data = $this->validated($request);
        // After the picks too: a town is the two merged by sort.
        $data['sort'] = $scenario->nextTownSort();

        $scenario->townActions()->create($data);

        return back()->with('success', 'Town action added.');
    }

    /** A district the module brings into the town when it is chosen. */
    public function storeForModule(Request $request, Module $module): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort'] = (int) $module->townActions()->max('sort') + 1;

        $module->townActions()->create($data);

        return back()->with('success', 'Town action added.');
    }

    /**
     * The common districts: written once, for any scenario to pick. Each says
     * which towns use it, so an edit here is made knowing where it lands.
     * Shown with no scenario, so a Dread token prints `?dreadRule` here and the
     * picking scenario's rule in its own town.
     */
    public function index(): Response
    {
        $presenter = CardPresenter::make();

        $districts = TownAction::common()->with('picks.scenario')->orderBy('sort')->orderBy('name')->get();

        return Inertia::render('Town/Index', [
            'districts' => $districts->map(fn (TownAction $a) => $presenter->townAction($a) + [
                'used_by' => $a->picks->map(fn ($p) => $p->scenario?->name)->filter()->sort()->values()->all(),
            ])->values(),
        ]);
    }

    /** A common district, owned by no scenario and no module. */
    public function storeCommon(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort'] = (int) TownAction::common()->max('sort') + 1;

        TownAction::create($data);

        return back()->with('success', 'Common district added.');
    }

    public function update(Request $request, TownAction $townAction): RedirectResponse
    {
        $townAction->update($this->validated($request));

        return back()->with('success', 'Town action saved.');
    }

    public function destroy(TownAction $townAction): RedirectResponse
    {
        // A common district's picks go with it (the foreign key cascades), so
        // every town that used it simply no longer has it.
        $towns = $townAction->picks()->count();
        $townAction->delete();

        return back()->with('success', $towns > 0
            ? "Town action deleted, and taken out of {$towns} ".($towns === 1 ? 'town' : 'towns').'.'
            : 'Town action deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'effect' => ['nullable', 'string', 'max:255'],
            'gold_cost' => ['nullable', 'integer', 'min:0', 'max:99'],
            'omen' => ['required', 'integer', 'min:0', 'max:99'],
            'note' => ['nullable', 'string'],
            'flavour' => ['nullable', 'string', 'max:500'],
            // A picture and its placement: App\Support\CardArt.
            ...CardArt::rules(),
        ]);
    }
}
