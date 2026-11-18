<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Domain;
use App\Models\EntityCard;
use App\Models\Module;
use App\Models\PlayerCard;
use App\Models\RulesConfig;
use App\Models\SavedDeck;
use App\Models\Scenario;
use App\Support\CardPresenter;
use App\Support\DeckAssembly;
use App\Support\DeckBuild;
use App\Support\Storyline;
use App\Support\TaleTable;
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
            'others' => Module::shared()->orderBy('name')->get()
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
     * A solo playtest table: shuffle the starting deck, reveal against the omen
     * pool, track Dread, the story beats and the health of whoever is playing.
     * Everything else about actually playing a card — resolving its effect,
     * gold, hands — stays on paper; the browser only keeps the state that would
     * otherwise mean physical cards, counters and a shuffle.
     */
    public function play(Request $request, Scenario $scenario): Response
    {
        $scenario->load(['entityCards.faces.cardType', 'entityCards.addedByBeat', 'storyBeats', 'boardCards.addedByBeat', 'townActions']);

        // Same reason as assembly() and storyline(): {dreadRule} is resolved
        // off the card's own scenario, so it is handed over rather than queried.
        $scenario->entityCards->each->setRelation('scenario', $scenario);
        $scenario->storyBeats->each->setRelation('scenario', $scenario);
        $scenario->boardCards->each->setRelation('scenario', $scenario);
        $scenario->townActions->each->setRelation('scenario', $scenario);

        // A scenario of a Tale playthrough, when ?run= names one whose next
        // scenario this is. Its modules are fixed on; any others are added.
        $taleTable = TaleTable::for($request, $scenario);
        $fixed = $taleTable?->moduleSlugs() ?? [];
        $chosen = array_values(array_unique([...$fixed, ...$this->chosenModules($request, $scenario, $fixed !== [])]));

        $assembly = DeckAssembly::for($scenario, $chosen);
        $presenter = CardPresenter::make();
        $storyline = Storyline::make();
        $config = RulesConfig::map();

        $beatCardsByBeat = $assembly->beatCards()->groupBy('added_by_beat_id');
        $players = $this->playableDecks($presenter, $taleTable);

        return Inertia::render('Scenarios/Play', [
            // The playthrough this game is a scenario of, or null; and why a
            // run named in the URL is not being played, when it is not.
            'tale' => $taleTable?->props($presenter),
            'taleNotice' => $taleTable?->notice(),
            'fixedModules' => $fixed,
            'scenario' => [
                'slug' => $scenario->slug,
                'name' => $scenario->name,
                'starting_dread' => $scenario->starting_dread,
                // Worked out against the party's size in the browser: this is
                // the one place in the app that knows how many players there
                // are, so it is the one place an equation becomes a number.
                'starting_dread_equation' => $scenario->starting_dread_equation,
                // What the omen pool is seeded with: the scenario's own, or the
                // shared tunable number when it sets none. Resolved here so the
                // table has one place to read it from, worked out in the browser
                // like the Dread.
                'starting_omen' => $scenario->startingOmen()->number,
                'starting_omen_equation' => $scenario->startingOmen()->equation,
                'dread_effect' => $scenario->dread_effect,
                // The designer's setup steps, so a new game can be laid out
                // from the page rather than from the printed setup card.
                'setup_html' => $scenario->hasSetup() ? $presenter->setupCard($scenario)['steps_html'] : [],
            ],
            'available' => $scenario->compatibleModules()->map(fn (Module $m) => [
                'slug' => $m->slug,
                'name' => $m->name,
                'set_icon' => $m->set_icon,
                'deck_size' => $m->deckSize(),
                'recommended' => in_array($m->slug, $scenario->recommended_modules ?? [], true),
            ])->values(),
            'others' => Module::shared()->orderBy('name')->get()
                ->reject(fn (Module $m) => $m->worksWith($scenario))
                ->map(fn (Module $m) => ['slug' => $m->slug, 'name' => $m->name, 'deck_size' => $m->deckSize()])
                ->values(),
            'chosen' => $chosen,
            'startingCards' => $assembly->startingCards()->map(fn ($c) => $presenter->entityCard($c))->values(),
            'beats' => $scenario->storyBeats->map(fn ($beat) => $presenter->storyBeat($beat) + [
                'cards' => $beatCardsByBeat->get($beat->id, collect())
                    ->map(fn ($c) => $presenter->entityCard($c))->values(),
            ])->values(),
            // Whoever is at the table: health, and the two numbers a player
            // reads off their own character card beside it.
            'characters' => Character::orderBy('sort')->orderBy('name')->get()
                ->map(fn (Character $c) => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'health' => $c->health,
                    'health_equation' => $c->health_equation,
                    'hand_size' => $c->hand_size,
                    'hand_size_equation' => $c->hand_size_equation,
                    'gold_per_round' => $c->gold_per_round,
                    'gold_per_round_equation' => $c->gold_per_round_equation,
                    'colour' => $c->colour,
                    'colour_secondary' => $c->colour_secondary,
                    'ability_name' => $c->ability_name,
                    'ability_html' => $presenter->character($c)['html'],
                ])->values(),
            // The entity board: the scenario's own cards and the chosen
            // modules', setup and beat-added alike. Which beat puts a card out
            // is the card's own `added_by_beat_id`, the same as the deck.
            'boardCards' => $scenario->boardCards
                ->concat($assembly->modules->flatMap->boardCards)
                ->map(fn ($c) => $presenter->boardCard($c))
                ->values(),
            // The scenario's own districts, then those the chosen modules
            // bring. A module's district joins the town; it never replaces one.
            'town' => $scenario->townActions->sortBy('sort')
                ->concat($assembly->modules->flatMap->townActions)
                ->map(fn ($a) => $presenter->townAction($a))
                ->values(),
            // What a seat can sit down with: the decks saved from the deck
            // builder. Picking one is picking who plays.
            'decks' => $players['decks'],
            'playerCards' => $players['cards'],
            // The tunable numbers the table reads. Placeholders, like the rest.
            'config' => [
                'omenPerTownAction' => $config['omenPerTownAction'] ?? null,
                'omenAtEndOfRound' => $config['omenAtEndOfRound'] ?? null,
                'goldCarriesOver' => $config['goldCarriesOver'] ?? false,
                'goldPouchCapacity' => $config['goldPouchCapacity'] ?? null,
                'shopPurchaseDestination' => $config['shopPurchaseDestination'] ?? 'deck-top',
                'shopRowSize' => $config['shopRowSize'] ?? null,
                'playerDeckOutReshuffle' => $config['playerDeckOutReshuffle'] ?? false,
                'playerDeckOutOmen' => $config['playerDeckOutOmen'] ?? null,
            ],
            'firstCardArrowSource' => $storyline->firstCardArrowSource(),
            'defaultArrow' => $storyline->defaultArrow(),
        ]);
    }

    /**
     * Every deck a seat at the playtest table can be dealt: the decks saved
     * from the deck builder, and nothing else — a seat is a built deck, and
     * the deck says which character is playing. Each is lists of card ids, one
     * per copy, with the cards themselves sent once beside them; a saved deck
     * is still only a pointer at a build, and this is that build read the way
     * the deck builder reads it. One whose character is gone is left out.
     *
     * @return array{decks: array<int, array<string, mixed>>, cards: array<int, array<string, mixed>>}
     */
    private function playableDecks(CardPresenter $presenter, ?TaleTable $taleTable = null): array
    {
        $cards = [];
        $decks = [];

        $ids = function ($list) use (&$cards, $presenter) {
            return $list->map(function (PlayerCard $card) use (&$cards, $presenter) {
                $cards[$card->id] ??= $presenter->playerCard($card);

                return $card->id;
            })->values()->all();
        };

        $characters = Character::all()->keyBy('slug');
        $domains = Domain::all()->keyBy('slug');

        foreach (SavedDeck::orderBy('name')->get() as $saved) {
            $character = $characters->get($saved->build['character'] ?? '');

            if ($character === null) {
                continue;
            }

            $build = DeckBuild::for($character, $domains->get($saved->build['domain'] ?? ''), $saved->build['take'] ?? []);

            $decks[] = [
                'key' => "saved:{$saved->id}",
                'name' => $saved->name,
                'character' => $character->slug,
                'domain' => $build->domain?->name,
                'deck' => $ids($build->deck()),
                'kit' => $ids($build->kit()),
                'upgrades' => $ids($build->upgrades()),
                // In a Tale playthrough, what this character has earned so far.
                // The table deals it into the first seat playing them.
                'rewards' => $taleTable?->rewardIdsFor($character->slug) ?? [],
            ];
        }

        // Every reward the Tale holds, so a seat can be dealt any of them and
        // the Record results form can name them.
        foreach ($taleTable?->rewardCards() ?? [] as $reward) {
            $cards[$reward->id] ??= $presenter->playerCard($reward);
        }

        return ['decks' => $decks, 'cards' => $cards];
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

    /**
     * The modules asked for in the query. Left unasked, a scenario plays with
     * what it recommends — except as a scenario of a Tale, whose own choice of
     * modules is the default and whose extras are only what the query adds.
     */
    private function chosenModules(Request $request, Scenario $scenario, bool $taleChose = false): array
    {
        if ($request->has('modules')) {
            return array_values(array_filter((array) $request->input('modules')));
        }

        // Default to what the scenario recommends, so the page is useful on arrival.
        return $taleChose ? [] : ($scenario->recommended_modules ?? []);
    }
}
