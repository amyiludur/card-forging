<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MovesRows;
use App\Models\Tale;
use App\Models\TaleLogEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * What a Tale's campaign log records. The kind says how an entry is written
 * down — a number, a box, a note, a list of rewards — never what it means.
 * Their order is the order the log sheet's rows print in.
 */
class TaleLogEntryController extends Controller
{
    use MovesRows;

    public function store(Request $request, Tale $tale): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort'] ??= (int) $tale->logEntries()->max('sort') + 1;

        $tale->logEntries()->create($data);

        return $this->back($tale, 'Log entry added.');
    }

    public function update(Request $request, TaleLogEntry $entry): RedirectResponse
    {
        $entry->update($this->validated($request));

        return $this->back($entry->tale, 'Log entry saved.');
    }

    /** One place up or down among the Tale's log entries, in one request. */
    public function move(Request $request, TaleLogEntry $entry): RedirectResponse
    {
        $this->moveAmong($request, $entry->tale->logEntries(), $entry);

        return $this->back($entry->tale, 'Log entry moved.');
    }

    public function destroy(TaleLogEntry $entry): RedirectResponse
    {
        $tale = $entry->tale;
        $entry->delete();

        return $this->back($tale, 'Log entry deleted.');
    }

    private function validated(Request $request): array
    {
        return array_filter($request->validate([
            'name' => ['required', 'string', 'max:80'],
            'kind' => ['required', Rule::in(TaleLogEntry::KINDS)],
            'scope' => ['required', Rule::in(TaleLogEntry::SCOPES)],
            'is_placeholder' => ['boolean'],
            'sort' => ['nullable', 'integer'],
        ]), fn ($value, $key) => ! ($key === 'sort' && $value === null), ARRAY_FILTER_USE_BOTH);
    }

    private function back(Tale $tale, string $message): RedirectResponse
    {
        return redirect("/tales/{$tale->slug}?tab=log")->with('success', $message);
    }
}
