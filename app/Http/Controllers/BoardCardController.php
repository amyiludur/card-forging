<?php

namespace App\Http\Controllers;

use App\Models\BoardCard;
use App\Models\Module;
use App\Models\Scenario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BoardCardController extends Controller
{
    public function store(Request $request, Scenario $scenario): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort'] = (int) $scenario->boardCards()->max('sort') + 1;

        $scenario->boardCards()->create($data);

        return back()->with('success', 'Board card added.');
    }

    /** A module has board cards of its own, and no story beats to hang them on. */
    public function storeForModule(Request $request, Module $module): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort'] = (int) $module->boardCards()->max('sort') + 1;
        $data['added_by_beat_id'] = null;

        $module->boardCards()->create($data);

        return back()->with('success', 'Board card added.');
    }

    public function update(Request $request, BoardCard $boardCard): RedirectResponse
    {
        $boardCard->update($this->validated($request));

        return back()->with('success', 'Board card saved.');
    }

    public function destroy(BoardCard $boardCard): RedirectResponse
    {
        $boardCard->delete();

        return back()->with('success', 'Board card deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'qty' => ['required', 'integer', 'min:1', 'max:99'],
            'health' => ['nullable', 'string', 'max:60'],
            'traits' => ['array'],
            'traits.*' => ['string', 'max:60'],
            'text' => ['nullable', 'string'],
            'added_by_beat_id' => ['nullable', 'exists:story_beats,id'],
            'is_placeholder' => ['boolean'],
        ]);
    }
}
