<?php

namespace App\Support;

use App\Models\PlayerCard;
use Illuminate\Support\Collection;

/**
 * The card maths a character's deck and a domain's pool both need. Both are
 * piles of player cards with upgrades attached, so the counting, the curves and
 * the pair checks live here rather than twice.
 *
 * Like everything on the player side, this reports. It never corrects.
 */
class CardStats
{
    /** One entry per printed card, so a quantity of 3 appears three times. */
    public static function expand(Collection $cards): Collection
    {
        return $cards->flatMap(fn (PlayerCard $c) => array_fill(0, max(1, $c->qty), $c))->values();
    }

    /** Count printed cards by a key, or by each entry when the key is a list. */
    public static function countBy(Collection $printed, callable $key): array
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
    public static function curve(Collection $printed, callable $key): array
    {
        $buckets = [];

        foreach ($printed as $card) {
            $value = (int) $key($card);
            $buckets[$value] = ($buckets[$value] ?? 0) + 1;
        }

        ksort($buckets);

        return array_map(fn ($k, $v) => ['value' => $k, 'count' => $v], array_keys($buckets), $buckets);
    }

    /** What a pile of printed cards is made of: where it starts, and its curves. */
    public static function profile(Collection $printed, array $config = []): array
    {
        return [
            'start_zones' => self::countBy($printed, fn (PlayerCard $c) => $c->start_zone),
            'types' => self::countBy($printed, fn (PlayerCard $c) => $c->type),
            'hirelings' => self::hirelings($printed, $config),
            'omen_curve' => self::curve($printed, fn (PlayerCard $c) => $c->omen_icons),
            'gold_curve' => self::curve($printed, fn (PlayerCard $c) => $c->gold_cost),
            'shop_costs' => self::curve(
                $printed->filter(fn (PlayerCard $c) => $c->shop_cost !== null),
                fn (PlayerCard $c) => $c->shop_cost
            ),
            'buyable' => $printed->filter(fn (PlayerCard $c) => $c->shop_cost !== null)->count(),
        ];
    }

    /**
     * The Hireling side of a pile: how many there are, and the two curves only
     * they have. The in-play limit rides along because it is what the count
     * wants reading against — but it is a limit on the table, not on the deck,
     * so a deck holding more Hirelings than can be in play is not wrong and is
     * never warned about.
     */
    public static function hirelings(Collection $printed, array $config = []): array
    {
        $hirelings = $printed->filter(fn (PlayerCard $c) => $c->isHireling())->values();
        $max = $config['maxHirelingsInPlay'] ?? null;

        return [
            'total' => $hirelings->count(),
            'max_in_play' => is_numeric($max) ? (int) $max : null,
            'uses' => self::curve(
                $hirelings->filter(fn (PlayerCard $c) => $c->uses !== null),
                fn (PlayerCard $c) => $c->uses
            ),
            'sacrifice' => self::curve(
                $hirelings->filter(fn (PlayerCard $c) => $c->sacrifice_value !== null),
                fn (PlayerCard $c) => $c->sacrifice_value
            ),
        ];
    }

    /** Which card each upgrade replaces, and which upgrade each card leads to. */
    public static function upgradePairs(Collection $cards): array
    {
        return $cards->where('role', PlayerCard::ROLE_UPGRADE)->values()
            ->map(fn (PlayerCard $u) => [
                'upgrade' => $u->name,
                'upgrade_slug' => $u->slug,
                'replaces' => $u->replaces()?->name,
                'replaces_slug' => $u->upgrade_of,
            ])->all();
    }

    /**
     * The checks that hold for any pile: an upgrade pair has to agree at both
     * ends, and a card's omen icons have to sit in the range the rules give.
     *
     * @param  string  $owner  whose cards these are, for the message
     */
    public static function warnings(Collection $cards, string $owner, array $config = []): array
    {
        $warnings = [];
        $bySlug = $cards->keyBy('slug');

        foreach ($cards as $card) {
            if ($card->upgrades_to !== null && ! $bySlug->has($card->upgrades_to)) {
                $warnings[] = "{$card->name} upgrades to \"{$card->upgrades_to}\", which is not one of {$owner}'s cards.";
            }

            if ($card->upgrade_of !== null && ! $bySlug->has($card->upgrade_of)) {
                $warnings[] = "{$card->name} replaces \"{$card->upgrade_of}\", which is not one of {$owner}'s cards.";
            }

            // The two ends of the pair have to agree, or the Smithy has nothing to swap.
            if ($card->upgrades_to !== null && ($bySlug[$card->upgrades_to]->upgrade_of ?? null) !== $card->slug) {
                $warnings[] = "{$card->name} points at an upgrade that does not point back at it.";
            }
        }

        // Uses and a sacrifice value are a Hireling's. A card that carries one
        // without being a Hireling, or a Hireling with no term at all, is
        // reported here and left exactly as the design folder wrote it.
        foreach ($cards as $card) {
            if (! $card->isHireling()) {
                $stray = array_keys(array_filter([
                    'uses' => $card->uses !== null,
                    'a sacrifice value' => $card->sacrifice_value !== null,
                ]));

                if ($stray !== []) {
                    $warnings[] = sprintf(
                        '%s carries %s, which only a Hireling has. It is typed %s.',
                        $card->name,
                        implode(' and ', $stray),
                        $card->type,
                    );
                }

                continue;
            }

            if ($card->uses === null) {
                $warnings[] = "{$card->name} is a Hireling with no uses, so nothing says how long its term runs.";
            }

            if ($card->sacrifice_value === null) {
                $warnings[] = "{$card->name} is a Hireling with no sacrifice value, so nothing says what sending it away prevents.";
            }
        }

        $range = $config['omenPerCardPlayedRange'] ?? null;

        if (is_array($range) && count($range) === 2) {
            foreach ($cards as $card) {
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
}
