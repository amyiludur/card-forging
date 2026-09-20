<?php

namespace App\Http\Controllers;

use App\Models\EntityCard;
use App\Models\Module;
use App\Models\Scenario;
use App\Support\CardPresenter;
use App\Support\DeckAssembly;
use App\Support\Storyline;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DeckController extends Controller
{
    /** Pick modules for a scenario and see what the resulting deck looks like. */
    public function assembly(Request $request, Scenario $scenario): Response
    {
        $scenario->load(['entityCards.faces.cardType', 'entityCards.addedByBeat', 'storyBeats']);

        // The cards already know their scenario — it is the one being printed —
        // so it is handed to them rather than queried back per card. Same
        // pattern as a character's cards on the sheets above, and it is what
        // {dreadRule} reads.
        $scenario->entityCards->each->setRelation('scenario', $scenario);

        $chosen = $this->chosenModules($request, $scenario);
        $assembly = DeckAssembly::for($scenario, $chosen);
        $presenter = CardPresenter::make();

        return Inertia::render('Scenarios/Deck', [
            'scenario' => [
                'slug' => $scenario->slug,
                'name' => $scenario->name,
                'modules_required' => $scenario->modules_required,
                'recommended_modules' => $scenario->recommended_modules ?? [],
                'module_note' => $scenario->module_note,
            ],
            'available' => $scenario->compatibleModules()->map(fn (Module $m) => [
                'slug' => $m->slug,
                'name' => $m->name,
                'set_icon' => $m->set_icon,
                'theme' => $m->theme,
                'deck_size' => $m->deckSize(),
                'recommended' => in_array($m->slug, $scenario->recommended_modules ?? [], true),
            ])->values(),
            // Modules that exist but are not listed as compatible, so the designer
            // can still pick one and be warned rather than blocked.
            'others' => Module::orderBy('name')->get()
                ->reject(fn (Module $m) => $m->worksWith($scenario))
                ->map(fn (Module $m) => ['slug' => $m->slug, 'name' => $m->name, 'set_icon' => $m->set_icon, 'deck_size' => $m->deckSize()])
                ->values(),
            'chosen' => $chosen,
            'stats' => $assembly->stats(),
            'incompatible' => $assembly->incompatible()->pluck('name')->values(),
            'countMatchesRules' => $assembly->countMatchesRules(),
            'startingCards' => $assembly->startingCards()->map(fn ($c) => $presenter->entityCard($c))->values(),
            'beatCards' => $assembly->beatCards()->map(fn ($c) => $presenter->entityCard($c))->values(),
        ]);
    }

    /**
     * Lay a row of cards out and show which half of each split card resolves.
     * The fastest way to sanity check the arrow rule and Redirect.
     */
    public function storyline(Request $request, Scenario $scenario): Response
    {
        $scenario->load(['entityCards.faces.cardType', 'entityCards.addedByBeat']);

        // The cards already know their scenario — it is the one being printed —
        // so it is handed to them rather than queried back per card. Same
        // pattern as a character's cards on the sheets above, and it is what
        // {dreadRule} reads.
        $scenario->entityCards->each->setRelation('scenario', $scenario);

        $chosen = $this->chosenModules($request, $scenario);
        $assembly = DeckAssembly::for($scenario, $chosen);
        $pool = $assembly->expand($assembly->startingCards());

        $ids = array_values(array_filter(array_map('intval', (array) $request->input('cards', []))));
        $size = max(2, min(8, (int) $request->input('size', 5)));

        if ($ids === []) {
            $ids = $this->draw($pool, $size);
        }

        // Keep the requested order, and allow the same card twice.
        $byId = $pool->keyBy('id');
        $cards = collect($ids)->map(fn ($id) => $byId->get($id))->filter()->values();

        $flipped = [];

        foreach ((array) $request->input('flip', []) as $position) {
            $flipped[(int) $position] = true;
        }

        $storyline = Storyline::make();
        $resolved = $storyline->resolve($cards, $request->string('incoming')->toString() ?: null, $flipped);

        $presenter = CardPresenter::make();

        return Inertia::render('Scenarios/Storyline', [
            'scenario' => ['slug' => $scenario->slug, 'name' => $scenario->name],
            'chosen' => $chosen,
            'available' => $scenario->compatibleModules()->map(fn (Module $m) => [
                'slug' => $m->slug, 'name' => $m->name, 'set_icon' => $m->set_icon,
            ])->values(),
            'size' => $size,
            'row' => collect($resolved['row'])->map(fn (array $entry) => $entry + [
                'card' => $presenter->entityCard($byId->get($entry['card_id'])),
            ])->values(),
            'incoming' => $resolved['incoming'],
            'outgoing' => $resolved['outgoing'],
            'firstCardArrowSource' => $storyline->firstCardArrowSource(),
            'defaultArrow' => $storyline->defaultArrow(),
            'poolSize' => $pool->count(),
            'deck' => $pool->unique('id')->map(fn (EntityCard $c) => [
                'id' => $c->id,
                'name' => $c->name,
                'layout' => $c->layout,
                'arrow' => $c->pointsAt(),
            ])->values(),
        ]);
    }

    /**
     * A solo playtest table: shuffle the starting deck, draw and reveal one
     * card at a time following the arrow rule, track Dread and the current
     * story beat. Everything else about actually playing a card — resolving
     * its effect, tracking health and gold — stays on paper; the browser only
     * keeps the state that would otherwise mean physical cards and a shuffle.
     */
    public function play(Request $request, Scenario $scenario): Response
    {
        $scenario->load(['entityCards.faces.cardType', 'entityCards.addedByBeat', 'storyBeats']);

        // Same reason as assembly() and storyline(): {dreadRule} is resolved
        // off the card's own scenario, so it is handed over rather than queried.
        $scenario->entityCards->each->setRelation('scenario', $scenario);
        $scenario->storyBeats->each->setRelation('scenario', $scenario);

        $chosen = $this->chosenModules($request, $scenario);
        $assembly = DeckAssembly::for($scenario, $chosen);
        $presenter = CardPresenter::make();

        $beatCardsByBeat = $assembly->beatCards()->groupBy('added_by_beat_id');

        return Inertia::render('Scenarios/Play', [
            'scenario' => [
                'slug' => $scenario->slug,
                'name' => $scenario->name,
                'starting_dread' => $scenario->starting_dread,
                'dread_effect' => $scenario->dread_effect,
            ],
            'available' => $scenario->compatibleModules()->map(fn (Module $m) => [
                'slug' => $m->slug,
                'name' => $m->name,
                'set_icon' => $m->set_icon,
                'deck_size' => $m->deckSize(),
                'recommended' => in_array($m->slug, $scenario->recommended_modules ?? [], true),
            ])->values(),
            'others' => Module::orderBy('name')->get()
                ->reject(fn (Module $m) => $m->worksWith($scenario))
                ->map(fn (Module $m) => ['slug' => $m->slug, 'name' => $m->name, 'deck_size' => $m->deckSize()])
                ->values(),
            'chosen' => $chosen,
            'startingCards' => $assembly->startingCards()->map(fn ($c) => $presenter->entityCard($c))->values(),
            'beats' => $scenario->storyBeats->map(fn ($beat) => $presenter->storyBeat($beat) + [
                'cards' => $beatCardsByBeat->get($beat->id, collect())
                    ->map(fn ($c) => $presenter->entityCard($c))->values(),
            ])->values(),
            'firstCardArrowSource' => Storyline::make()->firstCardArrowSource(),
            'defaultArrow' => Storyline::make()->defaultArrow(),
        ]);
    }

    /**
     * A random hand, but seeded with at least one split card when the deck has
     * any: a row with no split cards shows nothing about the arrow rule.
     */
    private function draw(\Illuminate\Support\Collection $pool, int $size): array
    {
        $shuffled = $pool->shuffle();
        $hand = $shuffled->take($size);

        if ($hand->contains(fn (EntityCard $c) => $c->isSplit())) {
            return $hand->pluck('id')->all();
        }

        $split = $shuffled->first(fn (EntityCard $c) => $c->isSplit());

        if ($split === null) {
            return $hand->pluck('id')->all();
        }

        // Drop it in somewhere after the first card, so it has a card before it
        // to take its arrow from.
        $ids = $hand->pluck('id')->all();
        $ids[max(1, random_int(1, max(1, count($ids) - 1)))] = $split->id;

        return $ids;
    }

    private function chosenModules(Request $request, Scenario $scenario): array
    {
        if ($request->has('modules')) {
            return array_values(array_filter((array) $request->input('modules')));
        }

        // Default to what the scenario recommends, so the page is useful on arrival.
        return $scenario->recommended_modules ?? [];
    }
}
