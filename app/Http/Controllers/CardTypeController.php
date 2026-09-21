<?php

namespace App\Http\Controllers;

use App\Models\CardType;
use App\Models\Scenario;
use App\Support\Colour;
use App\Support\Icons;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The card types: what a card does in one word, and the colour it prints in.
 *
 * A type is either shared — every scenario and module can use it — or one
 * scenario's own. Both live in this one table and both are edited here; a
 * scenario's own are added from its own page, because that is where the
 * designer is when they want one.
 *
 * Renaming or recolouring a type reaches every card already typed with it, the
 * way a keyword does. Deleting one leaves those cards typed as nothing, which
 * the page says before it happens rather than after.
 */
class CardTypeController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Rules/CardTypes', [
            'types' => $this->library(),
            // Every icon is offerable: a type the designer adds is not one of
            // the five the icon map was built for.
            'icons' => array_keys(Icons::forBrowser()),
            'scenarios' => Scenario::orderBy('name')->get(['id', 'name', 'slug']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $type = CardType::create($this->validated($request));

        return back()->with('success', "Card type {$type->name} added as {{$type->slug}}.");
    }

    /** A type of this scenario's own, added from the scenario's own page. */
    public function storeForScenario(Request $request, Scenario $scenario): RedirectResponse
    {
        $type = CardType::create([...$this->validated($request), 'scenario_id' => $scenario->id]);

        // The slug is named because a name already taken gets a numbered one,
        // and the designer should read that here rather than find it later.
        return back()->with('success', "Card type {$type->name} added to {$scenario->name} as {{$type->slug}}.");
    }

    public function update(Request $request, CardType $cardType): RedirectResponse
    {
        $cardType->update($this->validated($request, $cardType));

        return back()->with('success', 'Card type saved.');
    }

    public function destroy(CardType $cardType): RedirectResponse
    {
        $faces = $cardType->faces()->count();
        $cardType->delete();

        // Report, don't correct: the cards stay, they are simply typed as
        // nothing until the designer types them again.
        return back()->with('success', $faces > 0
            ? "Card type deleted. {$faces} card face(s) now have no type."
            : 'Card type deleted.');
    }

    /** Every type, shared first, each with what uses it. */
    private function library()
    {
        return CardType::with('scenario:id,name,slug')
            ->withCount('faces')
            ->orderByRaw('scenario_id is not null')
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->map(fn (CardType $type) => [
                'id' => $type->id,
                'slug' => $type->slug,
                'name' => $type->name,
                'description' => $type->description,
                'colour' => $type->hex,
                'icon' => $type->icon,
                'icon_name' => $type->icon_name,
                'sort' => $type->sort,
                'scenario_id' => $type->scenario_id,
                'scenario' => $type->scenario?->name,
                'scenario_slug' => $type->scenario?->slug,
                // Reported, never enforced: deleting a used type is the
                // designer's call and the cards survive it either way.
                'faces_count' => $type->faces_count,
            ])
            ->values();
    }

    /**
     * The types a scenario's page shows: the shared library it draws on, and
     * the ones it owns. Used by ScenarioController too, so one page and one
     * editor can never disagree about what a scenario may type a card with.
     */
    public static function forScenario(?Scenario $scenario)
    {
        return CardType::for($scenario)
            ->withCount('faces')
            ->orderByRaw('scenario_id is not null')
            ->orderBy('sort')
            ->orderBy('name')
            ->get()
            ->map(fn (CardType $type) => [
                'id' => $type->id,
                'slug' => $type->slug,
                'name' => $type->name,
                'description' => $type->description,
                'colour' => $type->hex,
                'icon_name' => $type->icon_name,
                'scenario_id' => $type->scenario_id,
                'faces_count' => $type->faces_count,
            ])
            ->values();
    }

    /**
     * A slug off the name, for the designer who did not type one. Slugs are
     * unique across the table, so a name already taken gets a number rather
     * than a failed save.
     */
    private function slugFrom(string $name, ?CardType $type): string
    {
        $base = Str::slug($name) ?: 'type';
        $slug = $base;

        for ($n = 2; CardType::where('slug', $slug)->whereKeyNot($type?->id)->exists(); $n++) {
            $slug = "{$base}-{$n}";
        }

        return $slug;
    }

    private function validated(Request $request, ?CardType $type = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'slug' => [
                'nullable', 'string', 'max:60',
                'regex:/^[a-z][a-z0-9-]*$/',
                // Unique across the whole table, shared or owned: a design
                // file's "type": "tide" and a ?type=tide filter each have to
                // mean exactly one thing.
                Rule::unique('card_types', 'slug')->ignore($type),
            ],
            'description' => ['nullable', 'string', 'max:2000'],
            'colour' => ['nullable', 'string', 'max:7', 'regex:/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'icon' => ['nullable', 'string', Rule::in(array_keys(Icons::forBrowser()))],
            'sort' => ['nullable', 'integer', 'min:0'],
        ], [
            'slug.regex' => 'A slug is lowercase letters, digits and hyphens: attack, deep-tide.',
            'slug.unique' => 'Another card type already answers to that slug.',
            'colour.regex' => 'A colour is a hex code: #1c1917 or #abc.',
        ]);

        return [
            ...$data,
            'slug' => ($data['slug'] ?? null) ?: $this->slugFrom($data['name'], $type),
            // Stored the way it was picked, so a design-folder diff reads as
            // the designer typed it.
            'colour' => Colour::normalise($data['colour'] ?? null),
            'sort' => $data['sort'] ?? 0,
        ];
    }
}
