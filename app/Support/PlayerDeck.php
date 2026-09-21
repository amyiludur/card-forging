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
 * The domain half is not the character's at all. A character and a domain are
 * two separate things, paired when a deck is built, so this reports the 20 the
 * character brings and says where the other 20 come from. What a built deck
 * adds up to is DeckBuild's job, at /decks.
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

        return [
            'rule' => $rule,
            'signature_total' => $signature->count(),
            // The other half of the 40 is taken from whichever domain a deck is
            // built with, so from here it is a number of slots and nothing more.
            'domain_slots' => $rule['domain'],
            // Everything in the library those slots could be filled from.
            'domain_cards_available' => $this->domainPool(),
            'kit_total' => $kit->count(),
            'upgrade_total' => $upgrades->count(),
            'deck_total' => $rule['signature'] + $rule['domain'],
            ...CardStats::profile($signature, $this->config),
            'traits' => CardStats::countBy($signature, fn (PlayerCard $c) => $c->traits ?? [])
                + CardStats::countBy($kit, fn (PlayerCard $c) => $c->traits ?? []),
            'keywords' => CardStats::countBy(
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
}
