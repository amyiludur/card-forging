<?php

namespace App\Http\Controllers;

use App\Models\SavedDeck;
use App\Support\DeckBuild;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * A deck build saved under a name — the character, the domain and the take
 * map the deck builder already carries in its query string, kept so it does
 * not have to be rebuilt or bookmarked by hand. The pairing itself still
 * lives nowhere but here: this is a shortcut back to a query string, not a
 * new place decks are assembled.
 */
class SavedDeckController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:80'],
        ]);

        $name = trim($data['name']);

        $build = [
            'character' => $request->string('character')->toString() ?: null,
            'domain' => $request->string('domain')->toString() ?: null,
            'take' => DeckBuild::takeFromRequest($request),
        ];

        // Saving under a name already in use overwrites it — the same way
        // printing a URL you have already bookmarked just updates the bookmark.
        SavedDeck::updateOrCreate(['name' => $name], ['build' => $build]);

        return back()->with('success', "Saved as \"{$name}\".");
    }

    public function destroy(SavedDeck $savedDeck): RedirectResponse
    {
        $name = $savedDeck->name;
        $savedDeck->delete();

        return back()->with('success', "Deleted \"{$name}\".");
    }
}
