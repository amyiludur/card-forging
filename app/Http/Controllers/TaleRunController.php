<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Tale;
use App\Models\TaleLogEntry;
use App\Models\TaleRun;
use App\Models\TaleStep;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Playthroughs of a Tale: starting one, its mode, and writing a scenario's
 * results into its log. What is written is checked against the Tale's own
 * entries and rewards. Nothing here applies a result to a game — the table
 * deals earned rewards, and that is all it takes from the log.
 */
class TaleRunController extends Controller
{
    public function store(Request $request, Tale $tale): RedirectResponse
    {
        $tale->runs()->create($this->validated($request, $tale));

        return $this->back($tale, 'Playthrough started.');
    }

    public function update(Request $request, TaleRun $run): RedirectResponse
    {
        $run->update($this->validated($request, $run->tale));

        return $this->back($run->tale, 'Playthrough saved.');
    }

    public function destroy(TaleRun $run): RedirectResponse
    {
        $tale = $run->tale;
        $run->delete();

        return $this->back($tale, 'Playthrough deleted.');
    }

    /** Off to the table, on the next scenario this playthrough has not recorded. */
    public function play(TaleRun $run): RedirectResponse
    {
        $step = $run->nextStep();

        if ($step === null) {
            return redirect("/tales/{$run->tale->slug}?tab=playthroughs")
                ->with('error', $run->tale->steps()->exists()
                    ? 'Nothing left to play in this playthrough.'
                    : 'This Tale has no scenarios yet.');
        }

        return redirect("/scenarios/{$step->scenario->slug}/play?run={$run->id}");
    }

    /** What one scenario left in the log. Recording it again replaces it whole. */
    public function record(Request $request, TaleRun $run, TaleStep $step): RedirectResponse
    {
        abort_unless($step->tale_id === $run->tale_id, 404);

        $run->results()->updateOrCreate(
            ['tale_step_id' => $step->id],
            ['values' => $this->clean($run, (array) $request->input('values', [])), 'played_at' => now()],
        );

        return $this->back($run->tale, 'Recorded '.($step->scenario?->name ?? 'the scenario').'.');
    }

    private function validated(Request $request, Tale $tale): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:80'],
            // One of this Tale's own modes, or none.
            'tale_mode_id' => ['nullable', 'integer', Rule::exists('tale_modes', 'id')->where('tale_id', $tale->id)],
        ]);
    }

    /**
     * The stored values: every one of the Tale's entries, in its own kind, and
     * nothing else. An entry the request leaves out is stored empty, so a step
     * recorded again is replaced rather than merged.
     */
    private function clean(TaleRun $run, array $values): array
    {
        $entries = $run->tale->logEntries()->get()->keyBy('id');
        $rewards = $run->tale->rewardCards()->where('role', 'reward')->pluck('id')->map(fn ($id) => (int) $id)->all();
        $characters = Character::pluck('slug')->all();
        $fail = fn (string $message) => throw ValidationException::withMessages(['values' => $message]);

        foreach (array_keys($values) as $key) {
            if (! $entries->has((int) $key)) {
                $fail('That log entry is not one of this Tale\'s.');
            }
        }

        $one = function (TaleLogEntry $entry, mixed $value) use ($rewards, $fail) {
            return match ($entry->kind) {
                'number' => $value === null || $value === ''
                    ? null
                    : (filter_var($value, FILTER_VALIDATE_INT) !== false
                        ? (int) $value
                        : $fail("\"{$entry->name}\" takes a whole number.")),
                'check' => filter_var($value, FILTER_VALIDATE_BOOLEAN),
                'note' => $value === null || $value === '' ? null : (string) $value,
                'rewards' => collect((array) ($value ?? []))
                    ->map(fn ($id) => in_array((int) $id, $rewards, true) ? (int) $id : $fail("\"{$entry->name}\" can only hold this Tale's reward cards."))
                    ->unique()
                    ->values()
                    ->all(),
                default => $value,
            };
        };

        $clean = [];

        foreach ($entries as $id => $entry) {
            $value = $values[$id] ?? $values[(string) $id] ?? null;

            if ($entry->scope === 'party') {
                $clean[$id] = $one($entry, $value);

                continue;
            }

            // One value per character, keyed by the character's slug.
            if ($value !== null && (! is_array($value) || array_is_list($value) && $value !== [])) {
                $fail("\"{$entry->name}\" is written per character.");
            }

            $perCharacter = [];

            foreach ((array) $value as $slug => $each) {
                if (! in_array($slug, $characters, true)) {
                    $fail("There is no character \"{$slug}\".");
                }

                $perCharacter[$slug] = $one($entry, $each);
            }

            $clean[$id] = $perCharacter;
        }

        return $clean;
    }

    private function back(Tale $tale, string $message): RedirectResponse
    {
        return redirect("/tales/{$tale->slug}?tab=playthroughs")->with('success', $message);
    }
}
