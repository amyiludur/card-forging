<?php

namespace App\Support;

use App\Models\EntityCard;
use App\Models\RulesConfig;
use Illuminate\Support\Collection;

/**
 * Lays revealed cards out in a row and works out which half of each split card
 * resolves.
 *
 * The rule, as written in rules/01-core-rules.md: every card carries an arrow on
 * its right edge pointing at the top or bottom half of the card to its right, so
 * a split card resolves the half indicated by the arrow of the card immediately
 * before it. The first card in the row has nothing before it, so it falls back to
 * the arrow the config names (the last card resolved, i.e. the top of the discard
 * pile) or to the default arrow.
 *
 * Open questions 1 and 2 are still open, so both of those behaviours are driven
 * by config rather than fixed here.
 */
class Storyline
{
    public function __construct(private array $config = [])
    {
    }

    public static function make(): self
    {
        return new self(RulesConfig::map());
    }

    public function defaultArrow(): string
    {
        $value = $this->config['defaultArrow'] ?? 'top';

        return in_array($value, ['top', 'bottom'], true) ? $value : 'top';
    }

    /** Where the first card's arrow comes from, as the config describes it. */
    public function firstCardArrowSource(): string
    {
        return (string) ($this->config['firstCardArrowSource'] ?? 'discard');
    }

    /**
     * @param  Collection<int, EntityCard>  $cards  in reveal order
     * @param  string|null  $incoming  the arrow carried in from the previous storyline
     * @param  array<int, bool>  $flipped  positions where Redirect has flipped the arrow
     */
    public function resolve(Collection $cards, ?string $incoming = null, array $flipped = []): array
    {
        $incoming = in_array($incoming, ['top', 'bottom'], true) ? $incoming : $this->defaultArrow();

        $row = [];
        $previousArrow = $incoming;

        foreach ($cards->values() as $position => $card) {
            $arrow = $card->pointsAt();

            // Redirect flips the arrow this card shows, which changes the card
            // after it, not this one.
            if (! empty($flipped[$position])) {
                $arrow = $arrow === 'top' ? 'bottom' : 'top';
            }

            $isSplit = $card->isSplit();

            $row[] = [
                'position' => $position,
                'card_id' => $card->id,
                'deciding_arrow' => $previousArrow,
                'deciding_source' => $position === 0 ? $this->firstCardArrowSource() : 'previous card',
                'arrow' => $arrow,
                'flipped' => ! empty($flipped[$position]),
                // Only a split card has a half to choose; everything else just resolves.
                'resolves' => $isSplit ? $previousArrow : 'single',
                'is_split' => $isSplit,
            ];

            $previousArrow = $arrow;
        }

        return [
            'row' => $row,
            'incoming' => $incoming,
            // What the next card revealed after this row would be told to do.
            'outgoing' => $previousArrow,
        ];
    }
}
