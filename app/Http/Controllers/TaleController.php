<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\PlayerCard;
use App\Models\Scenario;
use App\Models\Tale;
use App\Models\TaleLogEntry;
use App\Models\TaleStep;
use App\Support\CardPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

/**
 * A Tale's own page: its story, its scenarios in order, its modes, its log
 * entries and its three kinds of card, one tab each. Nothing here is written
 * on the designer's behalf: a Tale starts with no modes and no entries.
 */
class TaleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Tales/Index', [
            'tales' => Tale::withCount(['steps', 'modes', 'logEntries', 'cards', 'rewardCards', 'modules'])
                ->orderBy('sort')->orderBy('name')->get()
                ->map(fn (Tale $t) => [
                    'slug' => $t->slug,
                    'name' => $t->name,
                    'status' => $t->status,
                    'overview' => $t->overview,
                    'set_icon' => $t->set_icon,
                    'is_placeholder' => $t->is_placeholder,
                    'steps_count' => $t->steps_count,
                    'modes_count' => $t->modes_count,
                    'log_entries_count' => $t->log_entries_count,
                    'cards_count' => $t->cards_count,
                    'reward_cards_count' => $t->reward_cards_count,
                    'modules_count' => $t->modules_count,
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Tales/Form', ['tale' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $this->validated($request);
        $data['slug'] = $this->uniqueSlug(Str::slug($data['name']));
        $data['sort'] = (int) Tale::max('sort') + 1;

        $tale = Tale::create($data);

        return to_route('tales.show', $tale)->with('success', "Created {$tale->name}.");
    }

    public function show(Tale $tale): Response
    {
        $tale->load([
            'steps.scenario', 'steps.modules', 'modes', 'logEntries', 'cards.tale',
            'rewardCards.tale', 'modules',
        ]);

        $presenter = CardPresenter::make();

        return Inertia::render('Tales/Show', [
            'tale' => $this->taleProps($tale),
            'steps' => $tale->steps->values()->map(fn (TaleStep $step, int $i) => [
                'id' => $step->id,
                'number' => $i + 1,
                'scenario' => $step->scenario?->only(['id', 'slug', 'name']),
                'intro' => $step->intro,
                'outro' => $step->outro,
                'notes' => $step->notes,
                'sort' => $step->sort,
                'modules' => $step->modules->map(fn (Module $m) => $m->only(['id', 'slug', 'name', 'tale_id']))->values(),
                // A step's text quotes its own scenario, so the browser needs
                // that scenario's three tokens, the way a card's preview does.
                ...$presenter->scenarioTokens($step->scenario),
            ]),
            'modes' => $tale->modes->map->only(['id', 'name', 'text', 'is_placeholder', 'sort'])->values(),
            'logEntries' => $tale->logEntries->map->only(['id', 'name', 'kind', 'scope', 'is_placeholder', 'sort'])->values(),
            // The Tale's own modules: its entity-side cards.
            'modules' => $tale->modules->map(fn (Module $m) => [
                'id' => $m->id,
                'slug' => $m->slug,
                'name' => $m->name,
                'set_icon' => $m->set_icon,
                'deck_size' => $m->deckSize(),
            ])->values(),
            'cards' => $tale->cards->map(fn ($c) => $presenter->taleCard($c))->values(),
            'rewards' => $tale->rewardCards->map(fn (PlayerCard $c) => $presenter->playerCard($c))->values(),
            // For the step picker: any scenario, and the shared modules a
            // step may name beside the Tale's own.
            'scenarios' => Scenario::orderBy('name')->get(['id', 'slug', 'name']),
            'sharedModules' => Module::shared()->orderBy('name')->get(['id', 'slug', 'name']),
            'logKinds' => TaleLogEntry::KINDS,
            'logScopes' => TaleLogEntry::SCOPES,
        ]);
    }

    public function edit(Tale $tale): Response
    {
        return Inertia::render('Tales/Form', ['tale' => $this->taleProps($tale)]);
    }

    public function update(Request $request, Tale $tale): RedirectResponse
    {
        $tale->update($this->validated($request));

        return to_route('tales.show', $tale)->with('success', 'Tale saved.');
    }

    public function destroy(Tale $tale): RedirectResponse
    {
        $tale->delete();

        return to_route('tales.index')->with('success', 'Tale deleted.');
    }

    private function taleProps(Tale $tale): array
    {
        return $tale->only(['id', 'slug', 'name', 'status', 'overview', 'set_icon', 'is_placeholder']);
    }

    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'status' => ['nullable', 'string', 'max:255'],
            'overview' => ['nullable', 'string'],
            'set_icon' => ['nullable', 'string', 'max:8'],
            'is_placeholder' => ['boolean'],
        ]);
    }

    /** A name already taken gets a numbered slug, the same rule a card type follows. */
    private function uniqueSlug(string $base): string
    {
        $slug = $base ?: 'tale';
        $n = 1;

        while (Tale::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
