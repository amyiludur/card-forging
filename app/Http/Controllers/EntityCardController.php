<?php

namespace App\Http\Controllers;

use App\Models\CardType;
use App\Models\EntityCard;
use App\Models\Module;
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
        $module = $this->currentModule($request);

        $query = EntityCard::with(['faces.cardType', 'addedByBeat', 'scenario', 'module']);

        if ($module) {
            $query->where('module_id', $module->id);
        } elseif ($scenario) {
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
            'module' => $module ? ['slug' => $module->slug, 'name' => $module->name, 'set_icon' => $module->set_icon] : null,
            'cards' => $cards->map(fn (EntityCard $c) => $presenter->entityCard($c) + [
                'scenario_slug' => $c->scenario?->slug,
                'module_slug' => $c->module?->slug,
            ])->values(),
            'cardTypes' => CardType::orderBy('sort')->get(['slug', 'name']),
            'traits' => $module?->traits ?? $scenario?->traits ?? [],
            'filters' => $request->only('type', 'layout', 'trait', 'q'),
            'deckSize' => $cards->sum('qty'),
        ]);
    }

    public function create(Request $request): Response
    {
        $module = $this->currentModule($request);
        $scenario = $module ? null : ($this->currentScenario($request) ?? Scenario::orderBy('name')->firstOrFail());

        return Inertia::render('Cards/Form', $this->formProps($scenario, $module, null));
    }

    public function store(Request $request): RedirectResponse
    {
        $module = $this->currentModule($request);
        $scenario = $module ? null : ($this->currentScenario($request) ?? Scenario::orderBy('name')->firstOrFail());
        $data = $this->validated($request);

        $card = new EntityCard($data + [
            'scenario_id' => $scenario?->id,
            'module_id' => $module?->id,
        ]);

        $card->sort = (int) EntityCard::where($module ? 'module_id' : 'scenario_id', $module?->id ?? $scenario->id)->max('sort') + 1;
        $card->save();

        $this->syncFaces($card, $request->input('faces', []));

        return to_route('cards.edit', $card)->with('success', "Created {$card->name}.");
    }

    public function edit(EntityCard $card): Response
    {
        $card->load(['faces.cardType', 'scenario', 'module']);

        return Inertia::render('Cards/Form', $this->formProps($card->scenario, $card->module, $card));
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
        $copy->sort = (int) EntityCard::where($card->module_id ? 'module_id' : 'scenario_id', $card->module_id ?? $card->scenario_id)->max('sort') + 1;
        $copy->save();

        foreach ($card->faces as $face) {
            $copy->faces()->create($face->only('half', 'card_type_id', 'text', 'sort'));
        }

        return to_route('cards.edit', $copy)->with('success', 'Card duplicated.');
    }

    public function destroy(EntityCard $card): RedirectResponse
    {
        $target = $card->module_id
            ? ['module' => $card->module->slug]
            : ['scenario' => $card->scenario->slug];

        $card->delete();

        return to_route('cards.index', $target)->with('success', 'Card deleted.');
    }

    private function formProps(?Scenario $scenario, ?Module $module, ?EntityCard $card): array
    {
        $scenario?->load('storyBeats');

        return [
            'scenario' => $scenario ? [
                'slug' => $scenario->slug,
                'name' => $scenario->name,
                'traits' => $scenario->traits ?? [],
                'printed_arrows' => $scenario->printed_arrows,
                // What {dreadRule} writes onto this card, so the editor's
                // preview fills it in live rather than at the next save.
                'dread_effect' => $scenario->dread_effect,
            ] : null,
            'module' => $module ? [
                'slug' => $module->slug,
                'name' => $module->name,
                'set_icon' => $module->set_icon,
                'traits' => $module->traits ?? [],
            ] : null,
            'card' => $card ? CardPresenter::make()->entityCard($card) : null,
            'cardTypes' => CardType::orderBy('sort')->get(['id', 'slug', 'name', 'description']),
            // Only a scenario has beats; a module card is added by the scenario it joins.
            'beats' => $scenario
                ? $scenario->storyBeats->map(fn ($b) => ['id' => $b->id, 'order' => $b->order, 'name' => $b->name])->values()
                : collect(),
        ];
    }

    private function currentScenario(Request $request): ?Scenario
    {
        $slug = $request->string('scenario')->toString();

        return $slug ? Scenario::where('slug', $slug)->first() : null;
    }

    private function currentModule(Request $request): ?Module
    {
        $slug = $request->string('module')->toString();

        return $slug ? Module::where('slug', $slug)->first() : null;
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
            // v2: required on every card, whatever its layout.
            'arrow' => ['required', Rule::in(['top', 'bottom'])],
            'notes' => ['nullable', 'string'],
            'is_placeholder' => ['boolean'],
            'faces' => ['array', 'min:1', 'max:2'],
            'faces.*.half' => ['required', Rule::in(['single', 'top', 'bottom'])],
            'faces.*.card_type_id' => ['nullable', 'exists:card_types,id'],
            'faces.*.text' => ['nullable', 'string'],
        ]);

        // An X-cost card has no printed number.
        if ($data['layout'] === 'x-cost' || ($data['omen_is_x'] ?? false)) {
            $data['omen_is_x'] = true;
            $data['omen_cost'] = null;
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
