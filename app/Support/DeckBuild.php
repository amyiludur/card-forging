<?php

namespace App\Support;

use App\Models\Character;
use App\Models\Domain;
use App\Models\PlayerCard;
use App\Models\RulesConfig;
use Illuminate\Support\Collection;

/**
 * A deck being built: a character, a domain, and the cards taken out of that
 * domain, plus whatever the colourless pool adds. Characters and domains are
 * separate things — this is where they meet.
 *
 * The colourless pool is additive: whichever domain a deck is built with, any
 * other domain marked `is_neutral` joins its pool rather than competing with
 * it as a second domain choice. Picking a neutral domain itself needs no
 * addition, since it is already its own pool.
 *
 * Nothing is stored. The choice lives in the URL, the way a scenario's chosen
 * modules do on the deck assembly page, so a deck can be linked to and printed
 * without inventing a record the designer did not ask for.
 *
 * It reports and never corrects: a deck of 19 stays a deck of 19 with a note.
 */
class DeckBuild
{
    /**
     * @param  array<string, int>  $take  domain card slug => copies taken
     */
    public function __construct(
        public ?Character $character,
        public ?Domain $domain,
        private array $take = [],
        private array $config = [],
        private Collection $neutralDomains = new Collection,
    ) {
    }

    public static function for(?Character $character, ?Domain $domain, array $take): self
    {
        $character?->loadMissing('cards');
        $domain?->loadMissing('cards');

        // So a card can find its upgrade partner without a query each.
        $character?->cards->each->setRelation('character', $character);
        $domain?->cards->each->setRelation('domain', $domain);

        $neutralDomains = new Collection;

        if ($domain !== null) {
            $neutralDomains = Domain::where('is_neutral', true)
                ->whereKeyNot($domain->id)
                ->with('cards')
                ->get();

            $neutralDomains->each(fn (Domain $d) => $d->cards->each->setRelation('domain', $d));
        }

        return new self($character, $domain, $take, RulesConfig::map(), $neutralDomains);
    }

    /** How many signature and domain cards a deck is meant to hold. */
    public function rule(): array
    {
        $size = $this->config['deckSize'] ?? [];

        return [
            'signature' => (int) ($size['signature'] ?? 0),
            'domain' => (int) ($size['domain'] ?? 0),
        ];
    }

    /**
     * The most copies of one domain card a deck may take, or null for no cap
     * of its own. Unset is the shipped state: how many copies of one card a
     * deck should carry is the designer's to settle, and until it is settled
     * the pool's own print run is the only limit.
     */
    public function maxCopies(): ?int
    {
        $max = $this->config['maxCopiesPerDomainCard'] ?? null;

        return is_numeric($max) && (int) $max > 0 ? (int) $max : null;
    }

    /**
     * The most copies of this card this deck can take: the pool, then the cap,
     * then whether the card can fill a slot at all. A neutral card takes zero
     * when the rules say neutral cards do not fill domain slots — the card's
     * own origin decides this, not the domain's `is_neutral` flag.
     */
    public function limitFor(PlayerCard $card): int
    {
        if (! CardStats::fillsDomainSlot($card, $this->config)) {
            return 0;
        }

        $printed = max(1, $card->qty);
        $max = $this->maxCopies();

        return $max === null ? $printed : min($printed, $max);
    }

    /** The character's own half, expanded by copy. */
    public function signature(): Collection
    {
        return CardStats::expand($this->byRole($this->character, PlayerCard::ROLE_SIGNATURE));
    }

    /** Starts in play, outside the 40. */
    public function kit(): Collection
    {
        return CardStats::expand($this->byRole($this->character, PlayerCard::ROLE_KIT));
    }

    /** Set aside for the Smithy, from either side, outside the 40. */
    public function upgrades(): Collection
    {
        return CardStats::expand(
            $this->byRole($this->character, PlayerCard::ROLE_UPGRADE)
                ->concat($this->byRole($this->domain, PlayerCard::ROLE_UPGRADE))
                ->concat($this->neutralCards(PlayerCard::ROLE_UPGRADE))
        );
    }

    /** Everything the chosen domain offers, plus the colourless pool, one row per card. */
    public function pool(): Collection
    {
        return $this->byRole($this->domain, PlayerCard::ROLE_DOMAIN)
            ->concat($this->neutralCards(PlayerCard::ROLE_DOMAIN))
            ->values();
    }

    /** Whether a separate colourless pool is adding cards to the chosen domain. */
    public function hasNeutralPool(): bool
    {
        return $this->neutralDomains->isNotEmpty();
    }

    /** The colourless pool's own cards of one role, from every domain marked neutral but this one. */
    private function neutralCards(string $role): Collection
    {
        return $this->neutralDomains
            ->flatMap(fn (Domain $d) => $d->cards->where('role', $role))
            ->values();
    }

    /**
     * How many copies of each pool card this deck takes. Asking for more than
     * the pool prints — or more than the copy cap allows — is capped here and
     * reported in the warnings, so the deck shown is always one that could
     * actually be built.
     */
    public function taking(): array
    {
        $taken = [];

        foreach ($this->pool() as $card) {
            $want = (int) ($this->take[$card->slug] ?? 0);
            $limit = min($want, $this->limitFor($card));

            if ($limit > 0) {
                $taken[$card->slug] = $limit;
            }
        }

        return $taken;
    }

    /** The domain half, expanded by copy. */
    public function taken(): Collection
    {
        $taking = $this->taking();

        return $this->pool()
            ->filter(fn (PlayerCard $c) => isset($taking[$c->slug]))
            ->flatMap(fn (PlayerCard $c) => array_fill(0, $taking[$c->slug], $c))
            ->values();
    }

    /** The whole 40, the character's half first. */
    public function deck(): Collection
    {
        return $this->signature()->concat($this->taken())->values();
    }

    public function stats(): array
    {
        $rule = $this->rule();
        $signature = $this->signature();
        $taken = $this->taken();
        $deck = $signature->concat($taken);
        $poolSize = CardStats::expand($this->pool())->count();

        return [
            'rule' => $rule,
            'signature_total' => $signature->count(),
            'domain_total' => $taken->count(),
            'deck_total' => $deck->count(),
            'deck_rule' => $rule['signature'] + $rule['domain'],
            'pool_total' => $poolSize,
            // Null when the designer has not set a cap: the pool is the limit.
            'max_copies' => $this->maxCopies(),
            // What is left in the pool after this deck takes what it takes.
            'pool_left' => max(0, $poolSize - $taken->count()),
            'kit_total' => $this->kit()->count(),
            'upgrade_total' => $this->upgrades()->count(),
            // The curves that matter are the ones across the whole 40: that is
            // what a player actually draws from.
            ...CardStats::profile($deck, $this->config),
            'traits' => CardStats::countBy($deck, fn (PlayerCard $c) => $c->traits ?? []),
            'keywords' => CardStats::countBy($deck, fn (PlayerCard $c) => $c->keywords ?? []),
            // And the same two curves split by half, so it is possible to see
            // which side is carrying the omen.
            'omen_by_half' => [
                'signature' => CardStats::curve($signature, fn (PlayerCard $c) => $c->omen_icons),
                'domain' => CardStats::curve($taken, fn (PlayerCard $c) => $c->omen_icons),
            ],
        ];
    }

    /** What does not line up. Reported, never corrected. */
    public function warnings(): array
    {
        $warnings = [];
        $rule = $this->rule();
        $stats = $this->stats();

        if ($this->character !== null && $rule['signature'] > 0 && $stats['signature_total'] !== $rule['signature']) {
            $warnings[] = sprintf(
                '%s brings %d signature cards, but a deck holds %d.',
                $this->character->name,
                $stats['signature_total'],
                $rule['signature'],
            );
        }

        if ($this->domain !== null && $rule['domain'] > 0 && $stats['domain_total'] !== $rule['domain']) {
            $warnings[] = sprintf(
                '%d domain cards taken, but a deck holds %d. %s',
                $stats['domain_total'],
                $rule['domain'],
                $stats['domain_total'] < $rule['domain']
                    ? sprintf('%d still to pick.', $rule['domain'] - $stats['domain_total'])
                    : sprintf('%d too many.', $stats['domain_total'] - $rule['domain']),
            );
        }

        if ($this->domain !== null && $rule['domain'] > 0 && $stats['pool_total'] < $rule['domain']) {
            $warnings[] = sprintf(
                '%s%s holds %d cards in total, %d short of the %d a deck takes.',
                $this->domain->name,
                $this->neutralDomains->isNotEmpty() ? ' plus the colourless pool' : '',
                $stats['pool_total'],
                $rule['domain'] - $stats['pool_total'],
                $rule['domain'],
            );
        }

        // Asking for more copies than can be taken: capped above, said here.
        // Which bound bit is named, so the number in the message is actionable.
        $max = $this->maxCopies();

        foreach ($this->pool() as $card) {
            $want = (int) ($this->take[$card->slug] ?? 0);

            if ($want <= 0) {
                continue;
            }

            if (! CardStats::fillsDomainSlot($card, $this->config)) {
                $warnings[] = sprintf(
                    '%s is neutral, and neutral cards do not fill domain slots under the current rules, so it cannot be taken.',
                    $card->name,
                );

                continue;
            }

            $printed = max(1, $card->qty);

            if ($want <= $this->limitFor($card)) {
                continue;
            }

            $warnings[] = $max !== null && $max < $printed
                ? sprintf(
                    '%d copies of %s asked for, but a deck takes at most %d of one domain card.',
                    $want,
                    $card->name,
                    $max,
                )
                : sprintf(
                    '%d copies of %s asked for, but the pool holds %d.',
                    $want,
                    $card->name,
                    $printed,
                );
        }

        $pool = $this->pool();

        if ($pool->isNotEmpty() && $pool->every(fn (PlayerCard $c) => ! CardStats::fillsDomainSlot($c, $this->config))) {
            $warnings[] = 'Neutral cards do not fill domain slots under the current rules, so nothing in this pool can be taken.';
        }

        return $warnings;
    }

    private function byRole(Character|Domain|null $owner, string $role): Collection
    {
        return $owner === null
            ? new Collection
            : $owner->cards->where('role', $role)->values();
    }
}
