<?php

namespace App\Http\Controllers;

use App\Models\CardType;
use App\Models\EntityCard;
use App\Models\Scenario;
use App\Support\CardPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class EntityCardController extends Controller
{
    public function index(Request $request): Response
    {
        $scenario = $this->currentScenario($request);

        $query = EntityCard::with(['faces.cardType', 'addedByBeat', 'scenario']);

        if ($scenario) {
            $query->where('scenario_id', $scenario->id);
        }

        if ($type = $request->string('type')->toString()) {
            $query->whereHas('faces.cardType', fn ($q) => $q->where('slug', $type));
        }

        if ($layout = $request->string('layout')->toString()) {
            $query->where('layout', $layout);
        }

        if ($trait = $request->string('trait')->toString()) {
            $query->whereJsonContains('traits', $trait);
        }

        if ($search = $request->string('q')->toString()) {
            $query->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhereHas('faces', fn ($f) => $f->where('text', 'like', "%{$search}%")));
        }

        $cards = $query->orderBy('sort')->orderBy('name')->get();
        $presenter = CardPresenter::make();

        return Inertia::render('Cards/Index', [
            'scenario' => $scenario ? ['slug' => $scenario->slug, 'name' => $scenario->name] : null,
            'cards' => $cards->map(fn (EntityCard $c) => $presenter->entityCard($c) + [
                'scenario_slug' => $c->scenario->slug,
            ])->values(),
            'cardTypes' => CardType::orderBy('sort')->get(['slug', 'name']),
            'traits' => $scenario?->traits ?? [],
            'filters' => $request->only('type', 'layout', 'trait', 'q'),
            'deckSize' => $cards->sum('qty'),
        ]);
    }

    public function create(Request $request): Response
    {
        $scenario = $this->currentScenario($request) ?? Scenario::orderBy('name')->firstOrFail();

        return Inertia::render('Cards/Form', $this->formProps($scenario, null));
    }

    public function store(Request $request): RedirectResponse
    {
        $scenario = $this->currentScenario($request) ?? Scenario::orderBy('name')->firstOrFail();
        $data = $this->validated($request);

        $card = new EntityCard($data + ['scenario_id' => $scenario->id]);
        $card->sort = (int) EntityCard::where('scenario_id', $scenario->id)->max('sort') + 1;
        $card->save();

        $this->syncFaces($card, $request->input('faces', []));

        return to_route('cards.edit', $card)->with('success', "Created {$card->name}.");
    }

    public function edit(EntityCard $card): Response
    {
        $card->load(['faces.cardType', 'scenario']);

        return Inertia::render('Cards/Form', $this->formProps($card->scenario, $card));
    }

    public function update(Request $request, EntityCard $card): RedirectResponse
    {
        $card->update($this->validated($request));
        $this->syncFaces($card, $request->input('faces', []));

        return back()->with('success', 'Card saved.');
    }

    public function duplicate(EntityCard $card): RedirectResponse
    {
        $card->load('faces');

        $copy = $card->replicate(['id']);
        $copy->name = $card->name.' (copy)';
        $copy->sort = (int) EntityCard::where('scenario_id', $card->scenario_id)->max('sort') + 1;
        $copy->save();

        foreach ($card->faces as $face) {
            $copy->faces()->create($face->only('half', 'card_type_id', 'text', 'sort'));
        }

        return to_route('cards.edit', $copy)->with('success', 'Card duplicated.');
    }

    public function destroy(EntityCard $card): RedirectResponse
    {
        $scenario = $card->scenario;
        $card->delete();

        return to_route('cards.index', ['scenario' => $scenario->slug])->with('success', 'Card deleted.');
    }

    private function formProps(Scenario $scenario, ?EntityCard $card): array
    {
        $scenario->load('storyBeats');

        return [
            'scenario' => [
                'slug' => $scenario->slug,
                'name' => $scenario->name,
                'traits' => $scenario->traits ?? [],
                'printed_arrows' => $scenario->printed_arrows,
            ],
            'card' => $card ? CardPresenter::make()->entityCard($card) : null,
            'cardTypes' => CardType::orderBy('sort')->get(['id', 'slug', 'name', 'description']),
            'beats' => $scenario->storyBeats->map(fn ($b) => ['id' => $b->id, 'order' => $b->order, 'name' => $b->name])->values(),
        ];
    }

    private function currentScenario(Request $request): ?Scenario
    {
        $slug = $request->string('scenario')->toString();

        return $slug ? Scenario::where('slug', $slug)->first() : null;
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'qty' => ['required', 'integer', 'min:1', 'max:99'],
            'layout' => ['required', Rule::in(EntityCard::LAYOUTS)],
            'omen_cost' => ['nullable', 'integer', 'min:0', 'max:99'],
            'omen_is_x' => ['boolean'],
            'traits' => ['array'],
            'traits.*' => ['string', 'max:60'],
            'added_by_beat_id' => ['nullable', 'exists:story_beats,id'],
            'arrow' => ['nullable', Rule::in(['top', 'bottom'])],
            'notes' => ['nullable', 'string'],
            'is_placeholder' => ['boolean'],
            'faces' => ['array', 'min:1', 'max:2'],
            'faces.*.half' => ['required', Rule::in(['single', 'top', 'bottom'])],
            'faces.*.card_type_id' => ['nullable', 'exists:card_types,id'],
            'faces.*.text' => ['nullable', 'string'],
        ]);

        // An X-cost card has no printed number, and only a split card has an arrow.
        if ($data['layout'] === 'x-cost' || ($data['omen_is_x'] ?? false)) {
            $data['omen_is_x'] = true;
            $data['omen_cost'] = null;
        }

        if ($data['layout'] !== 'split') {
            $data['arrow'] = null;
        }

        return collect($data)->except('faces')->all();
    }

    private function syncFaces(EntityCard $card, array $faces): void
    {
        $halves = $card->isSplit() ? ['top', 'bottom'] : ['single'];

        $card->faces()->delete();

        foreach ($halves as $i => $half) {
            $face = collect($faces)->firstWhere('half', $half) ?? $faces[$i] ?? [];

            $card->faces()->create([
                'half' => $half,
                'card_type_id' => $face['card_type_id'] ?? null,
                'text' => $face['text'] ?? null,
                'sort' => $i,
            ]);
        }
    }
}
