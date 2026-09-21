<?php

namespace App\Http\Controllers;

use App\Models\Keyword;
use App\Support\Icons;
use App\Support\Markup;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The keyword library: the designer's own {token}s.
 *
 * Adding one here is all it takes to type {unique} in card text. The five icon
 * tokens in Markup::ICONS are the game's core symbols and stay in code, so a
 * keyword may not take one of their names.
 */
class KeywordController extends Controller
{
    /**
     * Where card and rules text lives, so the page can say what a keyword is
     * used by before the designer renames or deletes it.
     */
    private const TEXT_COLUMNS = [
        ['entity_card_faces', 'text', 'entity card'],
        ['player_cards', 'text', 'player card'],
        ['board_cards', 'text', 'board card'],
        ['story_beats', 'on_reach', 'story beat'],
        ['town_actions', 'effect', 'town action'],
        ['characters', 'ability_text', 'character ability'],
        ['rule_documents', 'body', 'rules page'],
    ];

    public function index(): Response
    {
        return Inertia::render('Rules/Keywords', [
            'keywords' => Keyword::orderBy('sort')->orderBy('name')->get()
                ->map(fn (Keyword $k) => [
                    'id' => $k->id,
                    'token' => $k->token,
                    'name' => $k->name,
                    'icon' => $k->icon,
                    'show_name' => $k->show_name,
                    'plain' => $k->plain,
                    'description' => $k->description,
                    'is_placeholder' => $k->is_placeholder,
                    'sort' => $k->sort,
                    // Reported, never enforced: deleting a used keyword is the
                    // designer's call, and the text keeps the token either way.
                    'uses' => $this->uses($k->token),
                ])->values(),
            // Every icon is offerable: a keyword can print the game's own
            // symbols or any of the interface ones.
            'icons' => array_keys(Icons::forBrowser()),
            'reserved' => array_keys(Markup::ICONS),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $keyword = Keyword::create($this->validated($request));

        return back()->with('success', "Keyword added. Type {{$keyword->token}} in card or rules text.");
    }

    public function update(Request $request, Keyword $keyword): RedirectResponse
    {
        $was = $keyword->token;
        $keyword->update($this->validated($request, $keyword));

        // A rename leaves the old token in the text it was typed into: the
        // editor says so rather than rewriting the designer's words.
        $note = $keyword->token !== $was && $this->uses($was) > 0
            ? " Text still saying {{$was}} was left alone."
            : '';

        return back()->with('success', 'Keyword saved.'.$note);
    }

    public function destroy(Keyword $keyword): RedirectResponse
    {
        $uses = $this->uses($keyword->token);
        $keyword->delete();

        return back()->with('success', $uses > 0
            ? "Keyword deleted. {$uses} piece(s) of text still say {{$keyword->token}} and now print it as typed."
            : 'Keyword deleted.');
    }

    private function validated(Request $request, ?Keyword $keyword = null): array
    {
        $data = $request->validate([
            'token' => [
                'required', 'string', 'max:40',
                'regex:'.Keyword::TOKEN_PATTERN,
                Rule::notIn(array_keys(Markup::ICONS)),
                Rule::unique('keywords', 'token')->ignore($keyword),
            ],
            'name' => ['required', 'string', 'max:60'],
            'icon' => ['nullable', 'string', Rule::in(array_keys(Icons::forBrowser()))],
            'show_name' => ['boolean'],
            'plain' => ['nullable', 'string', 'max:60'],
            'description' => ['nullable', 'string', 'max:2000'],
            'is_placeholder' => ['boolean'],
            'sort' => ['nullable', 'integer', 'min:0'],
        ], [
            'token.regex' => 'A token is lowercase letters, digits and hyphens: unique, bottom-draw.',
            'token.not_in' => 'That token is one of the game\'s icons already.',
        ]);

        return [
            ...$data,
            'show_name' => $request->boolean('show_name', true),
            // New data is a draft until the designer says otherwise.
            'is_placeholder' => $request->boolean('is_placeholder', true),
            'sort' => $data['sort'] ?? 0,
        ];
    }

    /** How many pieces of text type this token. */
    private function uses(string $token): int
    {
        $needle = '%{'.$token.'}%';

        return collect(self::TEXT_COLUMNS)->sum(
            fn (array $source): int => DB::table($source[0])->where($source[1], 'like', $needle)->count()
        );
    }
}
