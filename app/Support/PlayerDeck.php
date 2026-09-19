<?php

namespace App\Support;

use App\Models\Character;
use App\Models\Domain;
use App\Models\PlayerCard;
use App\Models\RulesConfig;
use Illuminate\Support\Collection;

/**
 * What a character's cards add up to, checked against the deck rules in the
 * tunable numbers. The design says 20 signature cards plus 20 domain cards;
 * kit and upgrades sit outside that, so they are counted but never totalled in.
 *
 * The domain half is not the character's to hold: it comes from the shared
 * domains the character draws from, so this reports what those bring against
 * the slots there are to fill.
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
        $character->loadMissing(['cards', 'domains.cards']);

        return new self($character, RulesConfig::map());
    }

    /** One entry per printed card, so a quantity of 3 appears three times. */
    public function expand(Collection $cards): Collection
    {
        return CardStats::expand($cards);
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
        $domains = $this->domains();

        return [
            'rule' => $rule,
            'signature_total' => $signature->count(),
            // The other half of the 40 belongs to the domains the character
            // draws from, not to the character, so it is reported as slots and
            // what the chosen domains bring to them.
            'domain_slots' => $rule['domain'],
            'domains' => $domains,
            // Only what can actually take a slot: the colourless pool counts
            // towards the 20 only while the rules say neutral cards do.
            'domain_total' => array_sum(array_column(
                array_filter($domains, fn (array $d) => $d['fills_slots']),
                'cards'
            )),
            // Everything in the library that could fill a slot, whether this
            // character draws from it or not.
            'domain_cards_available' => $this->domainPool(),
            'kit_total' => $kit->count(),
            'upgrade_total' => $upgrades->count(),
            'deck_total' => $rule['signature'] + $rule['domain'],
            ...CardStats::profile($signature),
            'traits' => CardStats::countBy($signature, fn (PlayerCard $c) => $c->traits ?? [])
                + CardStats::countBy($kit, fn (PlayerCard $c) => $c->traits ?? []),
            'keywords' => CardStats::countBy(
                $signature->concat($kit)->concat($upgrades),
                fn (PlayerCard $c) => $c->keywords ?? []
            ),
        ];
    }

    /** The domains this character draws from, and what each brings. */
    public function domains(): array
    {
        $neutralCounts = (bool) ($this->config['neutralFillsDomainSlots'] ?? false);

        return $this->character->domains->map(fn (Domain $d) => [
            'slug' => $d->slug,
            'name' => $d->name,
            'identity' => $d->identity,
            'set_icon' => $d->set_icon,
            'is_neutral' => $d->is_neutral,
            'is_placeholder' => $d->is_placeholder,
            'fills_slots' => ! $d->is_neutral || $neutralCounts,
            'cards' => $d->poolSize(),
        ])->all();
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

        return (int) PlayerCard::whereIn('origin', $origins)
            ->where('role', PlayerCard::ROLE_DOMAIN)
            ->sum('qty');
    }

    /** Which card each upgrade replaces, and which upgrade each card leads to. */
    public function upgradePairs(): array
    {
        return CardStats::upgradePairs($this->character->cards);
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

        if ($rule['signature'] > 0 && $stats['signature_total'] !== $rule['signature']) {
            $warnings[] = sprintf(
                '%d signature cards, but a deck is meant to hold %d.',
                $stats['signature_total'],
                $rule['signature'],
            );
        }

        // Only once the character draws from a domain. With none chosen the
        // stat panel already reads 0 of 20, and domains are not designed yet:
        // saying so on every character would be nagging about a known gap.
        if ($stats['domains'] !== [] && $rule['domain'] > 0 && $stats['domain_total'] !== $rule['domain']) {
            $warnings[] = sprintf(
                '%s %s %d domain cards between them, but a deck has %d domain slots.',
                $this->listNames(array_column($stats['domains'], 'name')),
                count($stats['domains']) === 1 ? 'holds' : 'hold',
                $stats['domain_total'],
                $rule['domain'],
            );
        }

        // A card marked as a domain card but filed under the character: domain
        // cards live in a domain, where every character can reach them.
        foreach ($this->character->cards as $card) {
            if (in_array($card->origin, ['domain', 'neutral'], true)) {
                $warnings[] = sprintf(
                    '%s is marked %s but belongs to this character. A card that fills a domain slot lives in a domain.',
                    $card->name,
                    $card->origin,
                );
            }
        }

        return [...$warnings, ...CardStats::warnings($this->character->cards, 'this character', $this->config)];
    }

    /** "Tide", "Tide and Ash", "Tide, Ash and Ember". */
    private function listNames(array $names): string
    {
        if (count($names) < 2) {
            return $names[0] ?? '';
        }

        $last = array_pop($names);

        return implode(', ', $names).' and '.$last;
    }
}
