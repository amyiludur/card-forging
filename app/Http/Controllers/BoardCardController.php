<?php

namespace App\Http\Controllers;

use App\Support\CardArt;
use App\Models\BoardCard;
use App\Models\Module;
use App\Models\Scenario;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

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
        // A module's board cards come out when a card says so, never at setup,
        // so there is nothing to deal them from.
        $data['start'] = BoardCard::START_BOARD;
        $data['deal_group'] = null;

        $module->boardCards()->create($data);

        return back()->with('success', 'Board card added.');
    }

    public function update(Request $request, BoardCard $boardCard): RedirectResponse
    {
        $data = $this->validated($request);

        // Same rule as storeForModule: a module's board card is never dealt.
        if ($boardCard->module_id) {
            $data['start'] = BoardCard::START_BOARD;
            $data['deal_group'] = null;
        }

        $boardCard->update($data);

        return back()->with('success', 'Board card saved.');
    }

    public function destroy(BoardCard $boardCard): RedirectResponse
    {
        $boardCard->delete();

        return back()->with('success', 'Board card deleted.');
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            // None at setup is a card something else brings out.
            'qty' => ['required', 'integer', 'min:0', 'max:99'],
            // Empty prints what setup puts out: BoardCard::printCopies().
            'print_qty' => ['nullable', 'integer', 'min:1', 'max:99'],
            'health' => ['nullable', 'string', 'max:60'],
            'traits' => ['array'],
            'traits.*' => ['string', 'max:60'],
            'text' => ['nullable', 'string'],
            'flavour' => ['nullable', 'string', 'max:500'],
            // A picture and its placement: App\Support\CardArt.
            ...CardArt::rules(),
            'added_by_beat_id' => ['nullable', 'exists:story_beats,id'],
            'start' => ['sometimes', Rule::in(BoardCard::STARTS)],
            'deal_group' => ['nullable', 'string', 'max:60'],
            'is_placeholder' => ['boolean'],
        ]);

        // A beat's card comes out with its beat: being added by one is the
        // whole of how it starts, so it is never dealt as well.
        if (! empty($data['added_by_beat_id'])) {
            $data['start'] = BoardCard::START_BOARD;
        }

        return $this->withDealGroup($data);
    }

    /**
     * A deal group belongs to a dealt card alone: trimmed, and dropped when the
     * card starts on the board or with a beat, so a stale name cannot come back.
     */
    private function withDealGroup(array $data): array
    {
        // A save that says nothing about how the card starts leaves its group alone.
        if (! array_key_exists('start', $data)) {
            unset($data['deal_group']);

            return $data;
        }

        $group = trim((string) ($data['deal_group'] ?? ''));
        $data['deal_group'] = ($data['start'] ?? null) === BoardCard::START_DEALT && $group !== '' ? $group : null;

        return $data;
    }
}
