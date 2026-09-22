<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\PlayerCard;
use App\Models\RulesConfig;
use App\Rules\PerPlayerEquation;
use App\Support\CardPresenter;
use App\Support\Colour;
use App\Support\PlayerDeck;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class CharacterController extends Controller
{
    public function index(): Response
    {
        // Read once and hand to every deck, rather than per character.
        $config = RulesConfig::map();

        return Inertia::render('Characters/Index', [
            'characters' => Character::with('cards')->orderBy('sort')->orderBy('name')->get()
                ->map(fn (Character $c) => [
                    'slug' => $c->slug,
                    'name' => $c->name,
                    'title' => $c->title,
                    'status' => $c->status,
                    'identity' => $c->identity,
                    'health' => $c->health,
                    'health_equation' => $c->health_equation,
                    'hand_size' => $c->hand_size,
                    'hand_size_equation' => $c->hand_size_equation,
                    'gold_per_round' => $c->gold_per_round,
                    'gold_per_round_equation' => $c->gold_per_round_equation,
                    'ability_name' => $c->ability_name,
                    'signature_count' => $c->signatureCount(),
                    'kit_count' => $c->cards->where('role', PlayerCard::ROLE_KIT)->sum('qty'),
                    'upgrade_count' => $c->cards->where('role', PlayerCard::ROLE_UPGRADE)->sum('qty'),
                    'warnings' => count((new PlayerDeck($c, $config))->warnings()),
                ]),
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Characters/Form', ['character' => null]);
    }

    public function store(Request $request): RedirectResponse
    {
        $character = Character::create($this->validated($request));

        return to_route('characters.show', $character)->with('success', "Created {$character->name}.");
    }

    public function show(Character $character): Response
    {
        $character->load('cards');
        // So a card can find its upgrade partner without a query each.
        $character->cards->each->setRelation('character', $character);

        $presenter = CardPresenter::make();
        $deck = PlayerDeck::for($character);

        return Inertia::render('Characters/Show', [
            'character' => $presenter->character($character),
            'cards' => $character->cards->map(fn (PlayerCard $c) => $presenter->playerCard($c))->values(),
            'stats' => $deck->stats(),
            'upgradePairs' => $deck->upgradePairs(),
            // Reported, never corrected: the designer decides what is wrong.
            'warnings' => $deck->warnings(),
            'options' => [
                'roles' => PlayerCard::ROLES,
                'origins' => PlayerCard::ORIGINS,
                'types' => PlayerCard::TYPES,
                'startZones' => PlayerCard::START_ZONES,
            ],
        ]);
    }

    public function edit(Character $character): Response
    {
        return Inertia::render('Characters/Form', [
            'character' => [
                'slug' => $character->slug,
                'name' => $character->name,
                'title' => $character->title,
                'story' => $character->story,
                'status' => $character->status,
                'identity' => $character->identity,
                // The character card's head band, as picked.
                'colour' => $character->colour,
                'colour_secondary' => $character->colour_secondary,
                'health' => $character->health,
                'health_equation' => $character->health_equation,
                'hand_size' => $character->hand_size,
                'hand_size_equation' => $character->hand_size_equation,
                'gold_per_round' => $character->gold_per_round,
                'gold_per_round_equation' => $character->gold_per_round_equation,
                'ability_name' => $character->ability_name,
                'ability_text' => $character->ability_text,
                'notes' => $character->notes ?? [],
                'is_placeholder' => $character->is_placeholder,
            ],
        ]);
    }

    public function update(Request $request, Character $character): RedirectResponse
    {
        $character->update($this->validated($request, $character));

        return to_route('characters.show', $character)->with('success', 'Character saved.');
    }

    public function destroy(Character $character): RedirectResponse
    {
        $character->delete();

        return to_route('characters.index')->with('success', 'Character deleted.');
    }

    private function validated(Request $request, ?Character $character = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => ['nullable', 'string', 'max:120', Rule::unique('characters', 'slug')->ignore($character?->id)],
            // The designer has not written these yet; both stay empty until they do.
            'title' => ['nullable', 'string', 'max:255'],
            'story' => ['nullable', 'string'],
            'status' => ['nullable', 'string', 'max:255'],
            'identity' => ['nullable', 'string'],
            // The character card's two colours. Either may be left empty: one
            // alone is a flat head band, neither is the dark head it printed
            // before a colour could be picked.
            'colour' => ['nullable', 'string', 'max:7', 'regex:/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'colour_secondary' => ['nullable', 'string', 'max:7', 'regex:/^#?([0-9a-fA-F]{3}|[0-9a-fA-F]{6})$/'],
            'health' => ['required', 'integer', 'min:1', 'max:99'],
            // Empty is the whole of "this is a plain number". When one is
            // written it is the value, and the number above is what the editor
            // puts back if the designer turns it off again.
            'health_equation' => ['nullable', 'string', 'max:120', new PerPlayerEquation],
            'hand_size' => ['required', 'integer', 'min:1', 'max:20'],
            'hand_size_equation' => ['nullable', 'string', 'max:120', new PerPlayerEquation],
            // Gold generation is per character since it left the tunable numbers.
            'gold_per_round' => ['required', 'integer', 'min:0', 'max:20'],
            'gold_per_round_equation' => ['nullable', 'string', 'max:120', new PerPlayerEquation],
            'ability_name' => ['nullable', 'string', 'max:120'],
            'ability_text' => ['nullable', 'string'],
            'notes' => ['array'],
            'notes.*' => ['string'],
            'is_placeholder' => ['boolean'],
        ], [
            'colour.regex' => 'A colour is a hex code: #3f2b56 or #abc.',
            'colour_secondary.regex' => 'A colour is a hex code: #3f2b56 or #abc.',
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: Str::slug($data['name']);
        $data['colour'] = Colour::normalise($data['colour'] ?? null);
        $data['colour_secondary'] = Colour::normalise($data['colour_secondary'] ?? null);

        return $data;
    }
}
