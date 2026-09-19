<?php

namespace App\Http\Controllers;

use App\Models\Character;
use App\Models\PlayerCard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

class PlayerCardController extends Controller
{
    public function create(Request $request, Character $character): Response
    {
        return Inertia::render('PlayerCards/Form', [
            'character' => ['slug' => $character->slug, 'name' => $character->name],
            'card' => null,
            'role' => $request->string('role', PlayerCard::ROLE_SIGNATURE)->toString(),
            'siblings' => $this->siblings($character),
            'options' => $this->options(),
        ]);
    }

    public function store(Request $request, Character $character): RedirectResponse
    {
        $data = $this->validated($request, $character);
        $data['sort'] = (int) $character->cards()->max('sort') + 1;

        $character->cards()->create($data);

        return to_route('characters.show', $character)->with('success', 'Card added.');
    }

    public function edit(PlayerCard $playerCard): Response
    {
        $character = $playerCard->character;

        return Inertia::render('PlayerCards/Form', [
            'character' => ['slug' => $character->slug, 'name' => $character->name],
            'card' => [
                'id' => $playerCard->id,
                'slug' => $playerCard->slug,
                'name' => $playerCard->name,
                'qty' => $playerCard->qty,
                'role' => $playerCard->role,
                'origin' => $playerCard->origin,
                'domain' => $playerCard->domain,
                'type' => $playerCard->type,
                'gold_cost' => $playerCard->gold_cost,
                'omen_icons' => $playerCard->omen_icons,
                'shop_cost' => $playerCard->shop_cost,
                'start_zone' => $playerCard->start_zone,
                'text' => $playerCard->text,
                'traits' => $playerCard->traits ?? [],
                'keywords' => $playerCard->keywords ?? [],
                'upgrades_to' => $playerCard->upgrades_to,
                'upgrade_of' => $playerCard->upgrade_of,
                'is_placeholder' => $playerCard->is_placeholder,
            ],
            'role' => $playerCard->role,
            'siblings' => $this->siblings($character, $playerCard),
            'options' => $this->options(),
        ]);
    }

    public function update(Request $request, PlayerCard $playerCard): RedirectResponse
    {
        $playerCard->update($this->validated($request, $playerCard->character, $playerCard));

        return to_route('characters.show', $playerCard->character)->with('success', 'Card saved.');
    }

    public function duplicate(PlayerCard $playerCard): RedirectResponse
    {
        $copy = $playerCard->replicate();
        $copy->name = "{$playerCard->name} (copy)";
        $copy->slug = $this->uniqueSlug($playerCard->character, Str::slug($copy->name));
        $copy->sort = (int) $playerCard->character->cards()->max('sort') + 1;
        // A copy is a draft until the designer says otherwise.
        $copy->is_placeholder = true;
        // Upgrade links are one to one, so the copy starts unlinked.
        $copy->upgrades_to = null;
        $copy->upgrade_of = null;
        $copy->save();

        return to_route('characters.show', $playerCard->character)->with('success', "Duplicated {$playerCard->name}.");
    }

    public function destroy(PlayerCard $playerCard): RedirectResponse
    {
        $character = $playerCard->character;

        // Leave no upgrade pointing at a card that is gone.
        PlayerCard::where('character_id', $character->id)
            ->where(fn ($q) => $q->where('upgrades_to', $playerCard->slug)->orWhere('upgrade_of', $playerCard->slug))
            ->get()
            ->each(fn (PlayerCard $c) => $c->update([
                'upgrades_to' => $c->upgrades_to === $playerCard->slug ? null : $c->upgrades_to,
                'upgrade_of' => $c->upgrade_of === $playerCard->slug ? null : $c->upgrade_of,
            ]));

        $playerCard->delete();

        return to_route('characters.show', $character)->with('success', 'Card deleted.');
    }

    /** The character's other cards, for the upgrade pickers. */
    private function siblings(Character $character, ?PlayerCard $except = null): array
    {
        return $character->cards()
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->get(['slug', 'name', 'role'])
            ->map(fn (PlayerCard $c) => ['slug' => $c->slug, 'name' => $c->name, 'role' => $c->role])
            ->all();
    }

    private function options(): array
    {
        return [
            'roles' => PlayerCard::ROLES,
            'origins' => PlayerCard::ORIGINS,
            'types' => PlayerCard::TYPES,
            'startZones' => PlayerCard::START_ZONES,
        ];
    }

    private function validated(Request $request, Character $character, ?PlayerCard $card = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable', 'string', 'max:120',
                Rule::unique('player_cards', 'slug')
                    ->where('character_id', $character->id)
                    ->ignore($card?->id),
            ],
            'qty' => ['required', 'integer', 'min:1', 'max:20'],
            'role' => ['required', Rule::in(PlayerCard::ROLES)],
            'origin' => ['required', Rule::in(PlayerCard::ORIGINS)],
            'domain' => ['nullable', 'string', 'max:60'],
            'type' => ['required', Rule::in(PlayerCard::TYPES)],
            'gold_cost' => ['required', 'integer', 'min:0', 'max:20'],
            'omen_icons' => ['required', 'integer', 'min:0', 'max:9'],
            'shop_cost' => ['nullable', 'integer', 'min:0', 'max:20'],
            'start_zone' => ['required', Rule::in(PlayerCard::START_ZONES)],
            'text' => ['nullable', 'string'],
            'traits' => ['array'],
            'traits.*' => ['string', 'max:60'],
            'keywords' => ['array'],
            'keywords.*' => ['string', 'max:60'],
            'upgrades_to' => ['nullable', 'string', 'max:120'],
            'upgrade_of' => ['nullable', 'string', 'max:120'],
            'is_placeholder' => ['boolean'],
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug($character, Str::slug($data['name']), $card);

        return $data;
    }

    private function uniqueSlug(Character $character, string $base, ?PlayerCard $except = null): string
    {
        $slug = $base ?: 'card';
        $n = 1;

        while (PlayerCard::where('character_id', $character->id)
            ->where('slug', $slug)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
