<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\Domain;
use App\Models\PlayerCard;
use App\Models\RulesConfig;
use App\Support\CardPresenter;
use App\Support\DomainPool;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class DomainController extends Controller
{
    public function index(): Response
    {
        $config = RulesConfig::map();

        return Inertia::render('Domains/Index', [
            'domains' => Domain::with('cards')->withCount('characters')->orderBy('sort')->orderBy('name')->get()
                ->map(fn (Domain $d) => [
                    'slug' => $d->slug,
                    'name' => $d->name,
                    'title' => $d->title,
                    'status' => $d->status,
                    'identity' => $d->identity,
                    'set_icon' => $d->set_icon,
                    'is_neutral' => $d->is_neutral,
                    'is_placeholder' => $d->is_placeholder,
                    'pool_size' => $d->poolSize(),
                    'upgrade_count' => (int) $d->cards->where('role', PlayerCard::ROLE_UPGRADE)->sum('qty'),
                    'characters_count' => $d->characters_count,
                    'warnings' => count((new DomainPool($d, $config))->warnings()),
                ]),
            // The other half of the 40, so the page can say what it is against.
            'slots' => (int) ($config['deckSize']['domain'] ?? 0),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Domains/Form', ['domain' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $domain = Domain::create($this->validated($request));

        return to_route('domains.show', $domain)->with('success', "Created {$domain->name}.");
    }

    public function show(Domain $domain): Response
    {
        $domain->load('cards');
        // So a card can find its upgrade partner without a query each.
        $domain->cards->each->setRelation('domain', $domain);

        $presenter = CardPresenter::make();
        $pool = DomainPool::for($domain);

        return Inertia::render('Domains/Show', [
            'domain' => [
                'slug' => $domain->slug,
                'name' => $domain->name,
                'title' => $domain->title,
                'status' => $domain->status,
                'identity' => $domain->identity,
                'set_icon' => $domain->set_icon,
                'is_neutral' => $domain->is_neutral,
                'is_placeholder' => $domain->is_placeholder,
                'notes' => $domain->notes ?? [],
            ],
            'cards' => $domain->cards->map(fn (PlayerCard $c) => $presenter->playerCard($c))->values(),
            'stats' => $pool->stats(),
            'upgradePairs' => $pool->upgradePairs(),
            // Reported, never corrected: the designer decides what is wrong.
            'warnings' => $pool->warnings(),
            // Who takes this domain. A domain is shared, so this is a list.
            'characters' => $domain->characters()->get(['slug', 'name'])
                ->map(fn (Character $c) => ['slug' => $c->slug, 'name' => $c->name])->values(),
        ]);
    }

    public function edit(Domain $domain): Response
    {
        return Inertia::render('Domains/Form', [
            'domain' => [
                'slug' => $domain->slug,
                'name' => $domain->name,
                'title' => $domain->title,
                'status' => $domain->status,
                'identity' => $domain->identity,
                'set_icon' => $domain->set_icon,
                'is_neutral' => $domain->is_neutral,
                'notes' => $domain->notes ?? [],
                'is_placeholder' => $domain->is_placeholder,
            ],
        ]);
    }

    public function update(Request $request, Domain $domain): RedirectResponse
    {
        $domain->update($this->validated($request, $domain));

        return to_route('domains.show', $domain)->with('success', 'Domain saved.');
    }

    public function destroy(Domain $domain): RedirectResponse
    {
        // Its cards go with it: they belong to the pool, not to a character.
        $domain->delete();

        return to_route('domains.index')->with('success', 'Domain deleted.');
    }

    private function validated(Request $request, ?Domain $domain = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('domains', 'slug')->ignore($domain?->id)],
            'title' => ['nullable', 'string', 'max:255'],
            'status' => ['nullable', 'string', 'max:255'],
            'identity' => ['nullable', 'string'],
            'set_icon' => ['nullable', 'string', 'max:16'],
            // The colourless pool. Its cards fill slots without a colour.
            'is_neutral' => ['boolean'],
            'notes' => ['array'],
            'notes.*' => ['string'],
            'is_placeholder' => ['boolean'],
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);

        return $data;
    }
}
