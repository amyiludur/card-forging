<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MovesRows;
use App\Models\Tale;
use App\Models\TaleStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * One scenario in a Tale. A step names the scenario — it never owns or copies
 * it — and lists the modules that join it, in the order given.
 */
class TaleStepController extends Controller
{
    use MovesRows;

    public function store(Request $request, Tale $tale): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort'] ??= (int) $tale->steps()->max('sort') + 1;

        $step = $tale->steps()->create($data);
        $this->syncModules($step, $request);

        return $this->back($tale, 'Scenario added.');
    }

    public function update(Request $request, TaleStep $step): RedirectResponse
    {
        $step->update($this->validated($request));
        $this->syncModules($step, $request);

        return $this->back($step->tale, 'Scenario saved.');
    }

    /** One place up or down among the Tale's scenarios, in one request. */
    public function move(Request $request, TaleStep $step): RedirectResponse
    {
        $this->moveAmong($request, $step->tale->steps(), $step);

        return $this->back($step->tale, 'Scenario moved.');
    }

    public function destroy(TaleStep $step): RedirectResponse
    {
        $tale = $step->tale;
        $step->delete();

        return $this->back($tale, 'Scenario removed from the Tale.');
    }

    private function validated(Request $request): array
    {
        return array_filter($request->validate([
            'scenario_id' => ['required', 'integer', 'exists:scenarios,id'],
            'intro' => ['nullable', 'string'],
            'outro' => ['nullable', 'string'],
            'notes' => ['nullable', 'string'],
            'sort' => ['nullable', 'integer'],
            'module_ids' => ['array'],
            'module_ids.*' => ['integer', 'exists:modules,id'],
        ]), fn ($value, $key) => $key !== 'module_ids' && ! ($key === 'sort' && $value === null), ARRAY_FILTER_USE_BOTH);
    }

    /** Only when the request names them, so saving a step's story keeps its modules. */
    private function syncModules(TaleStep $step, Request $request): void
    {
        if (! $request->has('module_ids')) {
            return;
        }

        $step->modules()->sync(
            collect($request->input('module_ids', []))
                ->values()
                ->mapWithKeys(fn ($id, $i) => [(int) $id => ['sort' => $i]])
                ->all()
        );
    }

    private function back(Tale $tale, string $message): RedirectResponse
    {
        return redirect("/tales/{$tale->slug}?tab=scenarios")->with('success', $message);
    }
}
