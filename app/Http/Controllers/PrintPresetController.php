<?php

namespace App\Http\Controllers;

use App\Models\PrintPreset;
use App\Support\PrintOptions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A print setup saved under a name — every print options page offers the same
 * list, so a sticker sheet lined up once travels to every scenario, character
 * and domain printed on it, not just the one it was saved from.
 */
class PrintPresetController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $name = trim($data['name']);

        // The same parsing and clamping a query string gets, so a preset can
        // never hold a corner radius or a margin the sheet itself would reject.
        $options = PrintOptions::fromRequest($request)->toArray();

        // Saving under a name already in use overwrites it — the same way
        // printing a URL you have already bookmarked just updates the bookmark.
        PrintPreset::updateOrCreate(['name' => $name], ['options' => $options]);

        return back()->with('success', "Saved as \"{$name}\".");
    }

    public function destroy(PrintPreset $preset): RedirectResponse
    {
        $name = $preset->name;
        $preset->delete();

        return back()->with('success', "Deleted \"{$name}\".");
    }
}
