<?php

namespace App\Http\Controllers;

use App\Models\CardType;
use App\Models\Module;
use App\Models\Scenario;
use App\Support\CardPresenter;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class ModuleController extends Controller
{
    public function index(): Response
    {
        return Inertia::render('Modules/Index', [
            'modules' => Module::withCount(['entityCards', 'boardCards'])->orderBy('name')->get()
                ->map(fn (Module $m) => [
                    'slug' => $m->slug,
                    'name' => $m->name,
                    'status' => $m->status,
                    'theme' => $m->theme,
                    'set_icon' => $m->set_icon,
                    'traits' => $m->traits ?? [],
                    'compatible_scenarios' => $m->compatible_scenarios ?? [],
                    'deck_size' => $m->deckSize(),
                    'entity_cards_count' => $m->entity_cards_count,
                    'board_cards_count' => $m->board_cards_count,
                ]),
            'scenarios' => Scenario::orderBy('name')->get(['slug', 'name']),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Modules/Form', [
            'module' => null,
            'scenarios' => Scenario::orderBy('name')->get(['slug', 'name']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $module = Module::create($this->validated($request));

        return to_route('modules.show', $module)->with('success', "Created {$module->name}.");
    }

    public function show(Module $module): Response
    {
        $module->load(['entityCards.faces.cardType', 'entityCards.module', 'boardCards.module']);

        $presenter = CardPresenter::make();

        return Inertia::render('Modules/Show', [
            'module' => [
                'slug' => $module->slug,
                'name' => $module->name,
                'status' => $module->status,
                'theme' => $module->theme,
                'set_icon' => $module->set_icon,
                'traits' => $module->traits ?? [],
                'compatible_scenarios' => $module->compatible_scenarios ?? [],
                'setup' => $module->setup,
                'deck_size' => $module->deckSize(),
            ],
            'cards' => $module->entityCards->map(fn ($c) => $presenter->entityCard($c))->values(),
            'boardCards' => $module->boardCards->map(fn ($c) => $presenter->boardCard($c))->values(),
            'cardTypes' => CardType::orderBy('sort')->get(['id', 'slug', 'name']),
            'scenarios' => Scenario::orderBy('name')->get(['slug', 'name']),
        ]);
    }

    public function edit(Module $module): Response
    {
        return Inertia::render('Modules/Form', [
            'module' => [
                'slug' => $module->slug,
                'name' => $module->name,
                'status' => $module->status,
                'theme' => $module->theme,
                'set_icon' => $module->set_icon,
                'traits' => $module->traits ?? [],
                'compatible_scenarios' => $module->compatible_scenarios ?? [],
                'setup' => $module->setup,
            ],
            'scenarios' => Scenario::orderBy('name')->get(['slug', 'name']),
        ]);
    }

    public function update(Request $request, Module $module): RedirectResponse
    {
        $module->update($this->validated($request, $module));

        return to_route('modules.show', $module)->with('success', 'Module saved.');
    }

    public function destroy(Module $module): RedirectResponse
    {
        $module->delete();

        return to_route('modules.index')->with('success', 'Module deleted.');
    }

    private function validated(Request $request, ?Module $module = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('modules', 'slug')->ignore($module?->id)],
            'status' => ['nullable', 'string', 'max:255'],
            'theme' => ['nullable', 'string'],
            'set_icon' => ['nullable', 'string', 'max:16'],
            // An empty list means the module works with every scenario.
            'compatible_scenarios' => ['array'],
            'compatible_scenarios.*' => ['string', 'exists:scenarios,slug'],
            'traits' => ['array'],
            'traits.*' => ['string', 'max:60'],
            'setup' => ['nullable', 'string'],
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);

        return $data;
    }
}
