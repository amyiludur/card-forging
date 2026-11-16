<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\MovesRows;
use App\Models\Tale;
use App\Models\TaleMode;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A Tale's difficulty modes. How many there are and what each does is the
 * designer's: a mode is a name and their text, and the tool caps neither.
 * Their order is the order the log sheet's mode line prints in.
 */
class TaleModeController extends Controller
{
    use MovesRows;

    public function store(Request $request, Tale $tale): RedirectResponse
    {
        $data = $this->validated($request);
        $data['sort'] ??= (int) $tale->modes()->max('sort') + 1;

        $tale->modes()->create($data);

        return $this->back($tale, 'Mode added.');
    }

    public function update(Request $request, TaleMode $mode): RedirectResponse
    {
        $mode->update($this->validated($request));

        return $this->back($mode->tale, 'Mode saved.');
    }

    /** One place up or down among the Tale's modes, in one request. */
    public function move(Request $request, TaleMode $mode): RedirectResponse
    {
        $this->moveAmong($request, $mode->tale->modes(), $mode);

        return $this->back($mode->tale, 'Mode moved.');
    }

    public function destroy(TaleMode $mode): RedirectResponse
    {
        $tale = $mode->tale;
        $mode->delete();

        return $this->back($tale, 'Mode deleted.');
    }

    private function validated(Request $request): array
    {
        return array_filter($request->validate([
            'name' => ['required', 'string', 'max:80'],
            'text' => ['nullable', 'string'],
            'is_placeholder' => ['boolean'],
            'sort' => ['nullable', 'integer'],
        ]), fn ($value, $key) => ! ($key === 'sort' && $value === null), ARRAY_FILTER_USE_BOTH);
    }

    private function back(Tale $tale, string $message): RedirectResponse
    {
        return redirect("/tales/{$tale->slug}?tab=modes")->with('success', $message);
    }
}
