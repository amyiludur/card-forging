<?php

namespace App\Http\Controllers;

use App\Models\Scenario;
use App\Models\TownAction;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class TownActionController extends Controller
{
    public function store(Request $request, Scenario $scenario): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort'] = (int) $scenario->townActions()->max('sort') + 1;

        $scenario->townActions()->create($data);

        return back()->with('success', 'Town action added.');
    }

    public function update(Request $request, TownAction $townAction): RedirectResponse
    {
        $townAction->update($this->validated($request));

        return back()->with('success', 'Town action saved.');
    }

    public function destroy(TownAction $townAction): RedirectResponse
    {
        $townAction->delete();

        return back()->with('success', 'Town action deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'effect' => ['nullable', 'string', 'max:255'],
            'gold_cost' => ['nullable', 'integer', 'min:0', 'max:99'],
            'omen' => ['required', 'integer', 'min:0', 'max:99'],
            'note' => ['nullable', 'string'],
        ]);
    }
}
