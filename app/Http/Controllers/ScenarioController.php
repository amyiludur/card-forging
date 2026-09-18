<?php

namespace App\Http\Controllers;

use App\Models\CardType;
use App\Models\Scenario;
use App\Support\CardPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ScenarioController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Scenarios/Index', [
            'scenarios' => Scenario::withCount(['entityCards', 'boardCards', 'storyBeats'])
                ->orderBy('name')
                ->get()
                ->map(fn (Scenario $s) => [
                    'slug' => $s->slug,
                    'name' => $s->name,
                    'entity_type' => $s->entity_type,
                    'status' => $s->status,
                    'overview' => $s->overview,
                    'traits' => $s->traits ?? [],
                    'deck_size' => $s->deckSize(),
                    'board_cards_count' => $s->board_cards_count,
                    'story_beats_count' => $s->story_beats_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Scenarios/Form', ['scenario' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $scenario = Scenario::create($this->validated($request));

        return to_route('scenarios.show', $scenario)->with('success', "Created {$scenario->name}.");
    }

    public function show(Scenario $scenario): Response
    {
        $scenario->load([
            'storyBeats',
            'boardCards.addedByBeat',
            'townActions',
            'entityCards.faces.cardType',
            'entityCards.addedByBeat',
        ]);

        $presenter = CardPresenter::make();

        return Inertia::render('Scenarios/Show', [
            'scenario' => [
                'slug' => $scenario->slug,
                'name' => $scenario->name,
                'entity_type' => $scenario->entity_type,
                'status' => $scenario->status,
                'overview' => $scenario->overview,
                'starting_dread' => $scenario->starting_dread,
                'dread_effect' => $scenario->dread_effect,
                'traits' => $scenario->traits ?? [],
                'win_text' => $scenario->win_text,
                'lose_text' => $scenario->lose_text,
                'printed_arrows' => $scenario->printed_arrows,
                'deck_size' => $scenario->deckSize(),
            ],
            'beats' => $scenario->storyBeats->map(fn ($b) => $presenter->storyBeat($b))->values(),
            'boardCards' => $scenario->boardCards->map(fn ($c) => $presenter->boardCard($c))->values(),
            'townActions' => $scenario->townActions->map(fn ($a) => [
                'id' => $a->id,
                'name' => $a->name,
                'effect' => $a->effect,
                'gold_cost' => $a->gold_cost,
                'omen' => $a->omen,
                'note' => $a->note,
            ])->values(),
            'cards' => $scenario->entityCards->map(fn ($c) => $presenter->entityCard($c))->values(),
            'cardTypes' => CardType::orderBy('sort')->get(['id', 'slug', 'name']),
        ]);
    }

    public function edit(Scenario $scenario): Response
    {
        return Inertia::render('Scenarios/Form', [
            'scenario' => [
                'slug' => $scenario->slug,
                'name' => $scenario->name,
                'entity_type' => $scenario->entity_type,
                'status' => $scenario->status,
                'overview' => $scenario->overview,
                'starting_dread' => $scenario->starting_dread,
                'dread_effect' => $scenario->dread_effect,
                'traits' => $scenario->traits ?? [],
                'win_text' => $scenario->win_text,
                'lose_text' => $scenario->lose_text,
                'printed_arrows' => $scenario->printed_arrows,
            ],
        ]);
    }

    public function update(Request $request, Scenario $scenario): RedirectResponse
    {
        $scenario->update($this->validated($request, $scenario));

        return to_route('scenarios.show', $scenario)->with('success', 'Scenario saved.');
    }

    public function destroy(Scenario $scenario): RedirectResponse
    {
        $scenario->delete();

        return to_route('scenarios.index')->with('success', 'Scenario deleted.');
    }

    private function validated(Request $request, ?Scenario $scenario = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('scenarios', 'slug')->ignore($scenario?->id)],
            'entity_type' => ['required', Rule::in(['creature', 'concept', 'group'])],
            'status' => ['nullable', 'string', 'max:255'],
            'overview' => ['nullable', 'string'],
            'starting_dread' => ['required', 'integer', 'min:0', 'max:99'],
            'dread_effect' => ['nullable', 'string'],
            'traits' => ['array'],
            'traits.*' => ['string', 'max:60'],
            'win_text' => ['nullable', 'string'],
            'lose_text' => ['nullable', 'string'],
            'printed_arrows' => ['boolean'],
        ]);

        // 'slug' is nullable, so it is absent from the validated data when the
        // designer leaves it blank.
        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);

        return $data;
    }
}
