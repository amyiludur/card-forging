<?php

namespace App\Http\Controllers;

use App\Models\Scenario;
use App\Models\StoryBeat;
use App\Rules\PerPlayerEquation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class StoryBeatController extends Controller
{
    public function store(Request $request, Scenario $scenario): RedirectResponse
    {
        $data = $this->validated($request);
        $data['order'] ??= (int) $scenario->storyBeats()->max('order') + 1;

        $scenario->storyBeats()->create($data);

        return back()->with('success', 'Beat added.');
    }

    public function update(Request $request, StoryBeat $beat): RedirectResponse
    {
        $beat->update(array_filter(
            $this->validated($request),
            fn ($value, $key) => ! ($key === 'order' && $value === null),
            ARRAY_FILTER_USE_BOTH
        ));

        return back()->with('success', 'Beat saved.');
    }

    public function destroy(StoryBeat $beat): RedirectResponse
    {
        $beat->delete();

        return back()->with('success', 'Beat deleted.');
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'order' => ['nullable', 'integer', 'min:1', 'max:99'],
            'name' => ['required', 'string', 'max:120'],
            'flavour' => ['nullable', 'string'],
            'on_reach' => ['nullable', 'string'],
            'advance' => ['nullable', 'string'],
            'on_advance' => ['nullable', 'string'],
            'dread_change' => ['required', 'integer', 'min:-9', 'max:9'],
            'dread_change_equation' => ['nullable', 'string', 'max:120', new PerPlayerEquation],
        ]);
    }
}
