<?php

namespace App\Http\Controllers;

use App\Support\CardArt;
use App\Models\Character;
use App\Models\Domain;
use App\Models\PlayerCard;
use App\Models\Tale;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One editor for every player card, whether it belongs to a character, to a
 * domain or to a Tale. The owner decides which lists the card can be filed in and which of
 * its siblings an upgrade may pair with; everything else is the same card.
 */
class PlayerCardController extends Controller
{
    public function create(Request $request, Character $character): Response
    {
        return $this->form($character, null, $request->string('role', PlayerCard::ROLE_SIGNATURE)->toString());
    }

    public function createForDomain(Request $request, Domain $domain): Response
    {
        return $this->form($domain, null, $request->string('role', PlayerCard::ROLE_DOMAIN)->toString());
    }

    public function store(Request $request, Character $character): RedirectResponse
    {
        return $this->storeFor($request, $character);
    }

    public function storeForDomain(Request $request, Domain $domain): RedirectResponse
    {
        return $this->storeFor($request, $domain);
    }

    public function createForTale(Request $request, Tale $tale): Response
    {
        return $this->form($tale, null, $request->string('role', PlayerCard::ROLE_REWARD)->toString());
    }

    public function storeForTale(Request $request, Tale $tale): RedirectResponse
    {
        return $this->storeFor($request, $tale);
    }

    public function edit(PlayerCard $playerCard): Response
    {
        return $this->form($playerCard->owner(), $playerCard, $playerCard->role);
    }

    public function update(Request $request, PlayerCard $playerCard): RedirectResponse
    {
        $owner = $playerCard->owner();

        $playerCard->update($this->validated($request, $owner, $playerCard));
        $playerCard->syncUpgradeLinks();

        return $this->backTo($owner)->with('success', 'Card saved.');
    }

    public function duplicate(PlayerCard $playerCard): RedirectResponse
    {
        $owner = $playerCard->owner();

        $copy = $playerCard->replicate();
        $copy->name = "{$playerCard->name} (copy)";
        $copy->slug = $this->uniqueSlug($owner, Str::slug($copy->name));
        $copy->sort = (int) $this->cardsOf($owner)->max('sort') + 1;
        // A copy is a draft until the designer says otherwise.
        $copy->is_placeholder = true;
        // Upgrade links are one to one, so the copy starts unlinked.
        $copy->upgrades_to = null;
        $copy->upgrade_of = null;
        $copy->save();

        return $this->backTo($owner)->with('success', "Duplicated {$playerCard->name}.");
    }

    public function destroy(PlayerCard $playerCard): RedirectResponse
    {
        $owner = $playerCard->owner();

        // Leave no upgrade pointing at a card that is gone.
        $this->cardsOf($owner)
            ->where(fn ($q) => $q->where('upgrades_to', $playerCard->slug)->orWhere('upgrade_of', $playerCard->slug))
            ->get()
            ->each(fn (PlayerCard $c) => $c->update([
                'upgrades_to' => $c->upgrades_to === $playerCard->slug ? null : $c->upgrades_to,
                'upgrade_of' => $c->upgrade_of === $playerCard->slug ? null : $c->upgrade_of,
            ]));

        $playerCard->delete();

        return $this->backTo($owner)->with('success', 'Card deleted.');
    }

    /** Store, for either owner. */
    private function storeFor(Request $request, Character|Domain|Tale $owner): RedirectResponse
    {
        $data = $this->validated($request, $owner);
        $data['sort'] = (int) $this->cardsOf($owner)->max('sort') + 1;

        $card = $this->cardsOf($owner)->create($data);
        // The other end of an upgrade pair has to learn about this card.
        $card->syncUpgradeLinks();

        return $this->backTo($owner)->with('success', 'Card added.');
    }

    private function form(Character|Domain|Tale $owner, ?PlayerCard $card, string $role): Response
    {
        $isDomain = $owner instanceof Domain;
        $isTale = $owner instanceof Tale;

        return Inertia::render('PlayerCards/Form', [
            'owner' => [
                'kind' => $this->kindOf($owner),
                'slug' => $owner->slug,
                'name' => $owner->name,
                'is_neutral' => $isDomain ? $owner->is_neutral : false,
                // The owner's two colours, so the editor's preview shows the
                // head this card will really print. A Tale has none, so its
                // rewards print the dark head and the Tale's set icon.
                'colour' => $isTale ? null : $owner->colour,
                'colour_secondary' => $isTale ? null : $owner->colour_secondary,
                'set_icon' => $isTale ? $owner->set_icon : null,
            ],
            'card' => $card === null ? null : [
                'id' => $card->id,
                'slug' => $card->slug,
                'name' => $card->name,
                'qty' => $card->qty,
                'role' => $card->role,
                'origin' => $card->origin,
                'type' => $card->type,
                'gold_cost' => $card->gold_cost,
                'omen_icons' => $card->omen_icons,
                'uses' => $card->uses,
                'sacrifice_value' => $card->sacrifice_value,
                'shop_cost' => $card->shop_cost,
                'start_zone' => $card->start_zone,
                'text' => $card->text,
                'flavour' => $card->flavour,
                // The picture as saved, or the form would start without it and
                // save it away the next time anything else changed.
                ...CardArt::present($card, false),
                'traits' => $card->traits ?? [],
                'keywords' => $card->keywords ?? [],
                'upgrades_to' => $card->upgrades_to,
                'upgrade_of' => $card->upgrade_of,
                'is_placeholder' => $card->is_placeholder,
            ],
            'role' => $role,
            'siblings' => $this->siblings($owner, $card),
            'options' => [
                // A character files a card in three lists, a domain and a Tale in two.
                'roles' => $this->rolesOf($owner),
                // A card in a domain fills a domain slot; the colourless pool
                // makes it neutral. A character's own cards are signature, and
                // a Tale's are rewards, which fill no slot at all.
                'origins' => match (true) {
                    $isTale => ['reward'],
                    $isDomain => ['domain', 'neutral'],
                    default => ['signature'],
                },
                'types' => PlayerCard::TYPES,
                'hirelingType' => PlayerCard::TYPE_HIRELING,
                'startZones' => PlayerCard::START_ZONES,
                'defaultOrigin' => match (true) {
                    $isTale => 'reward',
                    $isDomain => $owner->defaultOrigin(),
                    default => 'signature',
                },
            ],
        ]);
    }

    private function backTo(Character|Domain|Tale $owner): RedirectResponse
    {
        return match (true) {
            $owner instanceof Tale => redirect("/tales/{$owner->slug}?tab=cards"),
            $owner instanceof Domain => to_route('domains.show', $owner),
            default => to_route('characters.show', $owner),
        };
    }

    /**
     * The relation holding the owner's player cards. A Tale's `cards` are its
     * own Tale cards, so its player cards are `rewardCards`.
     */
    private function cardsOf(Character|Domain|Tale $owner): HasMany
    {
        return $owner instanceof Tale ? $owner->rewardCards() : $owner->cards();
    }

    private function kindOf(Character|Domain|Tale $owner): string
    {
        return match (true) {
            $owner instanceof Tale => 'tale',
            $owner instanceof Domain => 'domain',
            default => 'character',
        };
    }

    /** @return array<int, string> */
    private function rolesOf(Character|Domain|Tale $owner): array
    {
        return match (true) {
            $owner instanceof Tale => PlayerCard::TALE_ROLES,
            $owner instanceof Domain => PlayerCard::DOMAIN_ROLES,
            default => PlayerCard::CHARACTER_ROLES,
        };
    }

    /** The owner's other cards, for the upgrade pickers. */
    private function siblings(Character|Domain|Tale $owner, ?PlayerCard $except = null): array
    {
        return $this->cardsOf($owner)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->get(['slug', 'name', 'role'])
            ->map(fn (PlayerCard $c) => ['slug' => $c->slug, 'name' => $c->name, 'role' => $c->role])
            ->all();
    }

    private function validated(Request $request, Character|Domain|Tale $owner, ?PlayerCard $card = null): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'slug' => [
                'nullable', 'string', 'max:120',
                Rule::unique('player_cards', 'slug')
                    ->where($this->kindOf($owner).'_id', $owner->id)
                    ->ignore($card?->id),
            ],
            'qty' => ['required', 'integer', 'min:1', 'max:20'],
            'role' => ['required', Rule::in($this->rolesOf($owner))],
            // Every origin stays valid, so editing a card whose design file
            // says something the picker does not offer never rewrites it.
            'origin' => ['required', Rule::in(PlayerCard::ORIGINS)],
            'type' => ['required', Rule::in(PlayerCard::TYPES)],
            'gold_cost' => ['required', 'integer', 'min:0', 'max:20'],
            'omen_icons' => ['required', 'integer', 'min:0', 'max:9'],
            // A Hireling's two numbers. Both are optional even on a Hireling:
            // the rules behind them are the designer's placeholders, so a card
            // may sit half-written and be reported rather than refused.
            'uses' => ['nullable', 'integer', 'min:0', 'max:20'],
            'sacrifice_value' => ['nullable', 'integer', 'min:0', 'max:20'],
            'shop_cost' => ['nullable', 'integer', 'min:0', 'max:20'],
            'start_zone' => ['required', Rule::in(PlayerCard::START_ZONES)],
            'text' => ['nullable', 'string'],
            'flavour' => ['nullable', 'string', 'max:500'],
            // A picture and its placement: App\Support\CardArt.
            ...CardArt::rules(),
            'traits' => ['array'],
            'traits.*' => ['string', 'max:60'],
            'keywords' => ['array'],
            'keywords.*' => ['string', 'max:60'],
            // Both ends name one of this owner's other cards, never a stranger
            // and never the card itself. An upgrade pair lives inside one pile.
            'upgrades_to' => ['nullable', 'string', 'max:120', $this->siblingSlug($owner, $card)],
            'upgrade_of' => ['nullable', 'string', 'max:120', $this->siblingSlug($owner, $card)],
            'is_placeholder' => ['boolean'],
        ]);

        $data['slug'] = ($data['slug'] ?? null) ?: $this->uniqueSlug($owner, Str::slug($data['name']), $card);

        return $data;
    }

    /** Validates a slug as one of this owner's other cards. */
    private function siblingSlug(Character|Domain|Tale $owner, ?PlayerCard $card): \Closure
    {
        return function (string $attribute, mixed $value, \Closure $fail) use ($owner, $card): void {
            if ($value === $card?->slug) {
                $fail('A card cannot upgrade into itself.');

                return;
            }

            $exists = $this->cardsOf($owner)
                ->where('slug', $value)
                ->when($card, fn ($q) => $q->whereKeyNot($card->id))
                ->exists();

            if (! $exists) {
                $fail("{$owner->name} has no card called \"{$value}\".");
            }
        };
    }

    private function uniqueSlug(Character|Domain|Tale $owner, string $base, ?PlayerCard $except = null): string
    {
        $slug = $base ?: 'card';
        $n = 1;

        while ($this->cardsOf($owner)
            ->where('slug', $slug)
            ->when($except, fn ($q) => $q->whereKeyNot($except->id))
            ->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
