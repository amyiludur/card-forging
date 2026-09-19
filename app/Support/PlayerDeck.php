<?php

namespace App\Support;

use App\Models\Character;
use App\Models\PlayerCard;
use App\Models\RulesConfig;
use Illuminate\Support\Collection;

/**
 * What a character's cards add up to, checked against the deck rules in the
 * tunable numbers. The design says 20 signature cards plus 20 domain cards;
 * kit and upgrades sit outside that, so they are counted but never totalled in.
 *
 * Nothing here decides anything: it reports what the cards say and points at
 * what does not line up, which is the designer's to settle.
 */
class PlayerDeck
{
    public function __construct(public Character $character, private array $config = [])
    {
    }

    public static function for(Character $character): self
    {
        $character->loadMissing('cards');

        return new self($character, RulesConfig::map());
    }

    /** One entry per printed card, so a quantity of 3 appears three times. */
    public function expand(Collection $cards): Collection
    {
        return $cards->flatMap(fn (PlayerCard $c) => array_fill(0, max(1, $c->qty), $c))->values();
    }

    public function byRole(string $role): Collection
    {
        return $this->character->cards->where('role', $role)->values();
    }

    /** How many signature and domain cards a deck is meant to hold. */
    public function deckSizeRule(): array
    {
        $size = $this->config['deckSize'] ?? [];

        return [
            'signature' => (int) ($size['signature'] ?? 0),
            'domain' => (int) ($size['domain'] ?? 0),
        ];
    }

    public function stats(): array
    {
        $signature = $this->expand($this->byRole(PlayerCard::ROLE_SIGNATURE));
        $kit = $this->expand($this->byRole(PlayerCard::ROLE_KIT));
        $upgrades = $this->expand($this->byRole(PlayerCard::ROLE_UPGRADE));
        $rule = $this->deckSizeRule();

        return [
            'rule' => $rule,
            'signature_total' => $signature->count(),
            // The other half of the 40 is picked at deck build time and belongs
            // to no one character, so it is reported as slots and what exists to
            // fill them. Domains are not designed yet, so that is nothing.
            'domain_slots' => $rule['domain'],
            'domain_cards_available' => $this->domainPool(),
            'kit_total' => $kit->count(),
            'upgrade_total' => $upgrades->count(),
            'deck_total' => $rule['signature'] + $rule['domain'],
            'start_zones' => $this->countBy($signature, fn (PlayerCard $c) => $c->start_zone),
            'types' => $this->countBy($signature, fn (PlayerCard $c) => $c->type),
            'omen_curve' => $this->curve($signature, fn (PlayerCard $c) => $c->omen_icons),
            'gold_curve' => $this->curve($signature, fn (PlayerCard $c) => $c->gold_cost),
            'shop_costs' => $this->curve(
                $signature->filter(fn (PlayerCard $c) => $c->shop_cost !== null),
                fn (PlayerCard $c) => $c->shop_cost
            ),
            'buyable' => $signature->filter(fn (PlayerCard $c) => $c->shop_cost !== null)->count(),
            'traits' => $this->countBy($signature, fn (PlayerCard $c) => $c->traits ?? [])
                + $this->countBy($kit, fn (PlayerCard $c) => $c->traits ?? []),
            'keywords' => $this->countBy(
                $signature->concat($kit)->concat($upgrades),
                fn (PlayerCard $c) => $c->keywords ?? []
            ),
        ];
    }

    /**
     * Cards that can fill a domain slot: domain cards, plus neutral ones when
     * the rules let them. Both are counted by quantity, as printed.
     */
    private function domainPool(): int
    {
        $origins = ($this->config['neutralFillsDomainSlots'] ?? false)
            ? ['domain', 'neutral']
            : ['domain'];

        return (int) PlayerCard::whereIn('origin', $origins)->sum('qty');
    }

    /** Which card each upgrade replaces, and which upgrade each card leads to. */
    public function upgradePairs(): array
    {
        return $this->byRole(PlayerCard::ROLE_UPGRADE)->map(fn (PlayerCard $u) => [
            'upgrade' => $u->name,
            'upgrade_slug' => $u->slug,
            'replaces' => $u->replaces()?->name,
            'replaces_slug' => $u->upgrade_of,
        ])->all();
    }

    /**
     * Everything that does not line up. These are reports, not corrections: the
     * designer decides whether the cards are wrong or the rule is.
     */
    public function warnings(): array
    {
        $warnings = [];
        $stats = $this->stats();
        $rule = $stats['rule'];
        $bySlug = $this->character->cards->keyBy('slug');

        if ($rule['signature'] > 0 && $stats['signature_total'] !== $rule['signature']) {
            $warnings[] = sprintf(
                '%d signature cards, but a deck is meant to hold %d.',
                $stats['signature_total'],
                $rule['signature'],
            );
        }

        foreach ($this->character->cards as $card) {
            if ($card->upgrades_to !== null && ! $bySlug->has($card->upgrades_to)) {
                $warnings[] = "{$card->name} upgrades to \"{$card->upgrades_to}\", which is not one of this character's cards.";
            }

            if ($card->upgrade_of !== null && ! $bySlug->has($card->upgrade_of)) {
                $warnings[] = "{$card->name} replaces \"{$card->upgrade_of}\", which is not one of this character's cards.";
            }

            // The two ends of the pair have to agree, or the Smithy has nothing to swap.
            if ($card->upgrades_to !== null && ($bySlug[$card->upgrades_to]->upgrade_of ?? null) !== $card->slug) {
                $warnings[] = "{$card->name} points at an upgrade that does not point back at it.";
            }
        }

        $range = $this->config['omenPerCardPlayedRange'] ?? null;

        if (is_array($range) && count($range) === 2) {
            foreach ($this->character->cards as $card) {
                if ($card->omen_icons < $range[0] || $card->omen_icons > $range[1]) {
                    $warnings[] = sprintf(
                        '%s carries %d omen, outside the %d to %d a card is meant to.',
                        $card->name,
                        $card->omen_icons,
                        $range[0],
                        $range[1],
                    );
                }
            }
        }

        return $warnings;
    }

    /** Count printed cards by a key, or by each entry when the key is a list. */
    private function countBy(Collection $printed, callable $key): array
    {
        $counts = [];

        foreach ($printed as $card) {
            foreach ((array) $key($card) as $value) {
                $counts[$value] = ($counts[$value] ?? 0) + 1;
            }
        }

        arsort($counts);

        return $counts;
    }

    /** A numeric curve, in ascending order with no gaps left implicit. */
    private function curve(Collection $printed, callable $key): array
    {
        $buckets = [];

        foreach ($printed as $card) {
            $value = (int) $key($card);
            $buckets[$value] = ($buckets[$value] ?? 0) + 1;
        }

        ksort($buckets);

        return array_map(fn ($k, $v) => ['value' => $k, 'count' => $v], array_keys($buckets), $buckets);
    }
}
