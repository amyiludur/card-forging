<?php

namespace App\Support;

use App\Models\Domain;
use App\Models\PlayerCard;
use App\Models\RulesConfig;
use Illuminate\Support\Collection;

/**
 * A domain's pool, read against the deck rules. A character takes one domain
 * and chooses its 20 domain cards out of it, so a pool is not meant to be 20:
 * it is meant to be able to supply 20, and everything past that is the choice
 * the player gets at deck building.
 *
 * Like PlayerDeck, it reports and never corrects.
 */
class DomainPool
{
    public function __construct(public Domain $domain, private array $config = [])
    {
    }

    public static function for(Domain $domain): self
    {
        $domain->loadMissing('cards');

        return new self($domain, RulesConfig::map());
    }

    public function byRole(string $role): Collection
    {
        return $this->domain->cards->where('role', $role)->values();
    }

    /** How many cards a character takes out of a pool, from the tunable numbers. */
    public function slotRule(): int
    {
        return (int) ($this->config['deckSize']['domain'] ?? 0);
    }

    public function stats(): array
    {
        $pool = CardStats::expand($this->byRole(PlayerCard::ROLE_DOMAIN));
        $upgrades = CardStats::expand($this->byRole(PlayerCard::ROLE_UPGRADE));

        return [
            'pool_total' => $pool->count(),
            'upgrade_total' => $upgrades->count(),
            'domain_slots' => $this->slotRule(),
            // What is left after a deck takes its 20: the size of the choice.
            'choice' => max(0, $pool->count() - $this->slotRule()),
            // Whether a card from this pool can take a slot at all. The
            // colourless pool only counts while the rules say it does.
            'fills_slots' => ! $this->domain->is_neutral
                || (bool) ($this->config['neutralFillsDomainSlots'] ?? false),
            'origins' => CardStats::countBy($pool, fn (PlayerCard $c) => $c->origin),
            ...CardStats::profile($pool, $this->config),
            'traits' => CardStats::countBy($pool, fn (PlayerCard $c) => $c->traits ?? []),
            'keywords' => CardStats::countBy(
                $pool->concat($upgrades),
                fn (PlayerCard $c) => $c->keywords ?? []
            ),
        ];
    }

    public function upgradePairs(): array
    {
        return CardStats::upgradePairs($this->domain->cards);
    }

    public function warnings(): array
    {
        $warnings = [];
        $slots = $this->slotRule();
        $pool = CardStats::expand($this->byRole(PlayerCard::ROLE_DOMAIN))->count();

        // A bigger pool is the point; only one that cannot supply a deck is wrong.
        if ($slots > 0 && $pool < $slots) {
            $warnings[] = sprintf(
                'This pool holds %d cards, but a character takes %d out of it. %d short.',
                $pool,
                $slots,
                $slots - $pool,
            );
        }

        foreach ($this->byRole(PlayerCard::ROLE_DOMAIN) as $card) {
            if ($card->origin === 'signature') {
                $warnings[] = "{$card->name} is marked signature but sits in a domain, where it fills a domain slot rather than one of a character's own 20.";
            }
        }

        if ($this->domain->is_neutral && ! ($this->config['neutralFillsDomainSlots'] ?? false)) {
            $warnings[] = 'Neutral cards do not fill domain slots under the current rules, so nothing in this pool can be taken.';
        }

        return [
            ...$warnings,
            ...CardStats::warnings($this->domain->cards, "the {$this->domain->name} domain", $this->config),
        ];
    }
}
