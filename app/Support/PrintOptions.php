<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * Everything that decides how a sheet of cards is laid out. All measurements
 * are millimetres, because that is what card stock and printers are sold in.
 */
class PrintOptions
{
    /** Card stock sizes, width and height in mm. */
    public const CARD_SIZES = [
        'poker' => ['label' => 'Poker (63.5 × 88.9 mm)', 'w' => 63.5, 'h' => 88.9],
        'bridge' => ['label' => 'Bridge (57.2 × 88.9 mm)', 'w' => 57.2, 'h' => 88.9],
        'tarot' => ['label' => 'Tarot (70 × 120 mm)', 'w' => 70.0, 'h' => 120.0],
        'square' => ['label' => 'Square (70 × 70 mm)', 'w' => 70.0, 'h' => 70.0],
    ];

    /** Paper sizes, width and height in mm. */
    public const SHEET_SIZES = [
        'a4' => ['label' => 'A4 (210 × 297 mm)', 'w' => 210.0, 'h' => 297.0, 'css' => 'A4'],
        'letter' => ['label' => 'Letter (216 × 279 mm)', 'w' => 215.9, 'h' => 279.4, 'css' => 'Letter'],
    ];

    /** Printed names for every deck any page can offer. */
    public const DECKS = [
        'entity' => 'Entity deck',
        'board' => 'Board cards',
        'beats' => 'Story beats',
        'player' => 'Player deck',
        'character' => 'Character card',
        'upgrade' => 'Upgrades',
        'extras' => 'Kit and upgrades',
        'all' => 'Everything',
    ];

    /** What a scenario's print page offers. */
    public const SCENARIO_DECKS = [
        'entity' => 'Entity deck',
        'board' => 'Board cards',
        'beats' => 'Story beats',
        'all' => 'Everything',
    ];

    /** What a character's print page offers. */
    public const CHARACTER_DECKS = [
        'player' => 'Deck cards',
        'character' => 'Character card',
        'all' => 'Everything',
    ];

    /** What a built deck's print page offers: the 40, and what sits outside it. */
    public const DECK_DECKS = [
        'player' => 'The deck',
        'extras' => 'Kit and upgrades',
        'character' => 'Character card',
        'all' => 'Everything',
    ];

    /** What a domain's print page offers: the pool, and what upgrades it. */
    public const DOMAIN_DECKS = [
        'player' => 'Pool cards',
        'upgrade' => 'Upgrades',
        'all' => 'Everything',
    ];

    public function __construct(
        public string $deck = 'entity',
        public string $cardSize = 'poker',
        public string $sheetSize = 'a4',
        public float $customWidth = 0,
        public float $customHeight = 0,
        public float $bleed = 0,
        public float $margin = 8,
        public float $gutter = 0,
        public bool $cropMarks = true,
        public bool $backs = false,
        public bool $autoIcons = true,
        public bool $showPlaceholders = false,
    ) {
    }

    public static function fromRequest(Request $request, string $defaultDeck = 'entity'): self
    {
        return new self(
            deck: $request->string('deck', $defaultDeck)->toString(),
            cardSize: $request->string('card_size', 'poker')->toString(),
            sheetSize: $request->string('sheet_size', 'a4')->toString(),
            customWidth: (float) $request->input('custom_width', 0),
            customHeight: (float) $request->input('custom_height', 0),
            bleed: max(0, min(10, (float) $request->input('bleed', 0))),
            margin: max(0, min(30, (float) $request->input('margin', 8))),
            gutter: max(0, min(20, (float) $request->input('gutter', 0))),
            cropMarks: $request->boolean('crop_marks', true),
            backs: $request->boolean('backs'),
            autoIcons: $request->boolean('auto_icons', true),
            showPlaceholders: $request->boolean('show_placeholders'),
        );
    }

    public function cardWidth(): float
    {
        return $this->customWidth > 0 ? $this->customWidth : self::CARD_SIZES[$this->cardSize]['w'];
    }

    public function cardHeight(): float
    {
        return $this->customHeight > 0 ? $this->customHeight : self::CARD_SIZES[$this->cardSize]['h'];
    }

    /** Card footprint on the sheet, bleed included. */
    public function cellWidth(): float
    {
        return $this->cardWidth() + 2 * $this->bleed;
    }

    public function cellHeight(): float
    {
        return $this->cardHeight() + 2 * $this->bleed;
    }

    public function sheet(): array
    {
        return self::SHEET_SIZES[$this->sheetSize] ?? self::SHEET_SIZES['a4'];
    }

    public function columns(): int
    {
        return max(1, (int) floor(
            ($this->sheet()['w'] - 2 * $this->margin + $this->gutter) / ($this->cellWidth() + $this->gutter)
        ));
    }

    public function rows(): int
    {
        return max(1, (int) floor(
            ($this->sheet()['h'] - 2 * $this->margin + $this->gutter) / ($this->cellHeight() + $this->gutter)
        ));
    }

    public function perPage(): int
    {
        return $this->columns() * $this->rows();
    }

    /**
     * True when the cards will not actually fit inside the sheet. The layout
     * still renders, so the designer can see the overflow rather than guess.
     */
    public function overflows(): bool
    {
        $usedWidth = $this->columns() * $this->cellWidth() + ($this->columns() - 1) * $this->gutter;
        $usedHeight = $this->rows() * $this->cellHeight() + ($this->rows() - 1) * $this->gutter;

        return $usedWidth > $this->sheet()['w'] - 2 * $this->margin + 0.01
            || $usedHeight > $this->sheet()['h'] - 2 * $this->margin + 0.01;
    }

    public function toArray(): array
    {
        return [
            'deck' => $this->deck,
            'card_size' => $this->cardSize,
            'sheet_size' => $this->sheetSize,
            'custom_width' => $this->customWidth,
            'custom_height' => $this->customHeight,
            'bleed' => $this->bleed,
            'margin' => $this->margin,
            'gutter' => $this->gutter,
            'crop_marks' => $this->cropMarks,
            'backs' => $this->backs,
            'auto_icons' => $this->autoIcons,
            'show_placeholders' => $this->showPlaceholders,
        ];
    }
}
