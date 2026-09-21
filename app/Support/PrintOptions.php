<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;

/**
 * Everything that decides how a sheet of cards is laid out. All measurements
 * are millimetres, because that is what card stock and printers are sold in.
 *
 * A sheet of die-cut stickers is the reason half of these exist: the labels sit
 * at fixed positions the tool cannot infer, so every part of the grid — the four
 * margins, the two pitches, the column and row counts, where the first card
 * lands and how far the printer is out — can be passed in and is honoured as
 * given. Nothing here is guessed on the designer's behalf.
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

    /**
     * Paper sizes, width and height in mm. 'custom' carries no size of its own:
     * it is the sheet whose width and height are passed in, so a label sheet
     * that is not A4 or Letter needs no entry here.
     */
    public const SHEET_SIZES = [
        'a4' => ['label' => 'A4 (210 × 297 mm)', 'w' => 210.0, 'h' => 297.0, 'css' => 'A4'],
        'letter' => ['label' => 'Letter (216 × 279 mm)', 'w' => 215.9, 'h' => 279.4, 'css' => 'Letter'],
        'custom' => ['label' => 'Custom (set the size below)', 'w' => 0.0, 'h' => 0.0, 'css' => ''],
    ];

    /** Printed names for every deck any page can offer. */
    public const DECKS = [
        'setup' => 'Setup card',
        'entity' => 'Entity deck',
        'board' => 'Board cards',
        'beats' => 'Story beats',
        'town' => 'Town cards',
        'player' => 'Player deck',
        'character' => 'Character card',
        'upgrade' => 'Upgrades',
        'extras' => 'Kit and upgrades',
        'all' => 'Everything',
    ];

    /**
     * What a scenario's print page offers. A scenario is played off five piles
     * of cards, not one, so every one of them is printable on its own and
     * "Everything" is really everything. The setup card comes first because
     * that is the order they reach the table in.
     */
    public const SCENARIO_DECKS = [
        'setup' => 'Setup card',
        'entity' => 'Entity deck',
        'board' => 'Board cards',
        'beats' => 'Story beats',
        'town' => 'Town cards',
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
        public float $customSheetWidth = 0,
        public float $customSheetHeight = 0,
        public float $bleed = 0,
        public float $margin = 8,
        // Null, not 0: an edge that has not been given a measurement of its own
        // follows $margin, and 0 is a margin a label sheet may really want.
        public ?float $marginTop = null,
        public ?float $marginRight = null,
        public ?float $marginBottom = null,
        public ?float $marginLeft = null,
        public float $gutter = 0,
        public ?float $gutterX = null,
        public ?float $gutterY = null,
        // 0 means "fit as many as the sheet holds"; anything else is the grid
        // the sheet has, whether or not the tool would have chosen it.
        public int $gridColumns = 0,
        public int $gridRows = 0,
        public int $skip = 0,
        public float $offsetX = 0,
        public float $offsetY = 0,
        public float $cornerRadius = 2.5,
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
            customSheetWidth: max(0, min(2000, (float) $request->input('custom_sheet_width', 0))),
            customSheetHeight: max(0, min(2000, (float) $request->input('custom_sheet_height', 0))),
            bleed: max(0, min(10, (float) $request->input('bleed', 0))),
            margin: max(0, min(60, (float) $request->input('margin', 8))),
            marginTop: self::optional($request, 'margin_top', 0, 60),
            marginRight: self::optional($request, 'margin_right', 0, 60),
            marginBottom: self::optional($request, 'margin_bottom', 0, 60),
            marginLeft: self::optional($request, 'margin_left', 0, 60),
            gutter: max(0, min(40, (float) $request->input('gutter', 0))),
            gutterX: self::optional($request, 'gutter_x', 0, 40),
            gutterY: self::optional($request, 'gutter_y', 0, 40),
            gridColumns: max(0, min(20, (int) $request->input('columns', 0))),
            gridRows: max(0, min(20, (int) $request->input('rows', 0))),
            skip: max(0, min(999, (int) $request->input('skip', 0))),
            offsetX: max(-20, min(20, (float) $request->input('offset_x', 0))),
            offsetY: max(-20, min(20, (float) $request->input('offset_y', 0))),
            cornerRadius: max(0, min(20, (float) $request->input('corner_radius', 2.5))),
            cropMarks: $request->boolean('crop_marks', true),
            backs: $request->boolean('backs'),
            autoIcons: $request->boolean('auto_icons', true),
            showPlaceholders: $request->boolean('show_placeholders'),
        );
    }

    /**
     * A measurement that may not have been given. An empty field is not zero —
     * it means "whatever the shared setting says" — so only a real number here
     * overrides one.
     */
    private static function optional(Request $request, string $key, float $min, float $max): ?float
    {
        $value = $request->input($key);

        if ($value === null || $value === '' || ! is_numeric($value)) {
            return null;
        }

        return max($min, min($max, (float) $value));
    }

    /**
     * Does the chosen deck take in cards of this group? Every print page names
     * its groups after the decks it offers, so this one rule covers all of
     * them and "Everything" needs no list of its own.
     */
    public function wants(string $group): bool
    {
        return $this->deck === 'all' || $this->deck === $group;
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

    /**
     * The paper. A custom width or height wins over the stock size the same way
     * a custom card size does, and a custom sheet with no size falls back to A4
     * rather than to a sheet of nothing.
     */
    public function sheet(): array
    {
        $base = self::SHEET_SIZES[$this->sheetSize] ?? self::SHEET_SIZES['a4'];
        $fallback = self::SHEET_SIZES['a4'];

        $width = $this->customSheetWidth > 0 ? $this->customSheetWidth : ($base['w'] > 0 ? $base['w'] : $fallback['w']);
        $height = $this->customSheetHeight > 0 ? $this->customSheetHeight : ($base['h'] > 0 ? $base['h'] : $fallback['h']);

        $isStock = $base['css'] !== ''
            && abs($width - $base['w']) < 0.001
            && abs($height - $base['h']) < 0.001;

        return [
            'label' => $base['label'],
            'w' => $width,
            'h' => $height,
            // @page takes a named size or two lengths, so a custom sheet still
            // prints at its own size rather than being scaled onto A4.
            'css' => $isStock ? $base['css'] : $width.'mm '.$height.'mm',
        ];
    }

    public function topMargin(): float
    {
        return $this->marginTop ?? $this->margin;
    }

    public function rightMargin(): float
    {
        return $this->marginRight ?? $this->margin;
    }

    public function bottomMargin(): float
    {
        return $this->marginBottom ?? $this->margin;
    }

    public function leftMargin(): float
    {
        return $this->marginLeft ?? $this->margin;
    }

    /** Gap between columns — the horizontal pitch, less the card. */
    public function columnGutter(): float
    {
        return $this->gutterX ?? $this->gutter;
    }

    public function rowGutter(): float
    {
        return $this->gutterY ?? $this->gutter;
    }

    public function columns(): int
    {
        if ($this->gridColumns > 0) {
            return $this->gridColumns;
        }

        $gutter = $this->columnGutter();

        return max(1, (int) floor(
            ($this->sheet()['w'] - $this->leftMargin() - $this->rightMargin() + $gutter) / ($this->cellWidth() + $gutter)
        ));
    }

    public function rows(): int
    {
        if ($this->gridRows > 0) {
            return $this->gridRows;
        }

        $gutter = $this->rowGutter();

        return max(1, (int) floor(
            ($this->sheet()['h'] - $this->topMargin() - $this->bottomMargin() + $gutter) / ($this->cellHeight() + $gutter)
        ));
    }

    public function perPage(): int
    {
        return $this->columns() * $this->rows();
    }

    /**
     * How many cells the first sheet has left. A sticker sheet that has already
     * had labels peeled off starts part way in, and the skipped cells are still
     * cells: they cost the first sheet its capacity, not the run.
     */
    public function skipped(): int
    {
        return min($this->skip, max(0, $this->perPage() - 1));
    }

    public function firstPageCapacity(): int
    {
        return $this->perPage() - $this->skipped();
    }

    /**
     * The cards, page by page, with a null wherever a cell is deliberately
     * blank. The blanks have to be laid out rather than dropped, or everything
     * after them prints one label out of place.
     */
    public function paginate(Collection $cards): Collection
    {
        $skipped = $this->skipped();

        $cells = $skipped > 0
            ? collect(array_fill(0, $skipped, null))->concat($cards)
            : $cards;

        return $cells->chunk($this->perPage())->map->values()->values();
    }

    /**
     * True when the cards will not actually fit inside the sheet. The layout
     * still renders, so the designer can see the overflow rather than guess.
     */
    public function overflows(): bool
    {
        $columns = $this->columns();
        $rows = $this->rows();

        $usedWidth = $columns * $this->cellWidth() + ($columns - 1) * $this->columnGutter();
        $usedHeight = $rows * $this->cellHeight() + ($rows - 1) * $this->rowGutter();

        return $usedWidth > $this->sheet()['w'] - $this->leftMargin() - $this->rightMargin() + 0.01
            || $usedHeight > $this->sheet()['h'] - $this->topMargin() - $this->bottomMargin() + 0.01;
    }

    public function toArray(): array
    {
        return [
            'deck' => $this->deck,
            'card_size' => $this->cardSize,
            'sheet_size' => $this->sheetSize,
            'custom_width' => $this->customWidth,
            'custom_height' => $this->customHeight,
            'custom_sheet_width' => $this->customSheetWidth,
            'custom_sheet_height' => $this->customSheetHeight,
            'bleed' => $this->bleed,
            'margin' => $this->margin,
            'margin_top' => $this->marginTop,
            'margin_right' => $this->marginRight,
            'margin_bottom' => $this->marginBottom,
            'margin_left' => $this->marginLeft,
            'gutter' => $this->gutter,
            'gutter_x' => $this->gutterX,
            'gutter_y' => $this->gutterY,
            'columns' => $this->gridColumns,
            'rows' => $this->gridRows,
            'skip' => $this->skip,
            'offset_x' => $this->offsetX,
            'offset_y' => $this->offsetY,
            'corner_radius' => $this->cornerRadius,
            'crop_marks' => $this->cropMarks,
            'backs' => $this->backs,
            'auto_icons' => $this->autoIcons,
            'show_placeholders' => $this->showPlaceholders,
        ];
    }

    /** What the options page reports back: the grid these settings really give. */
    public function layout(): array
    {
        return [
            'columns' => $this->columns(),
            'rows' => $this->rows(),
            'per_page' => $this->perPage(),
            'first_page' => $this->firstPageCapacity(),
            'skipped' => $this->skipped(),
            'overflows' => $this->overflows(),
            // The pitch is what a label sheet's spec quotes: corner to corner,
            // not the gap between two labels. Reported so the two can be matched.
            'cell_w' => round($this->cellWidth(), 2),
            'cell_h' => round($this->cellHeight(), 2),
            'pitch_x' => round($this->cellWidth() + $this->columnGutter(), 2),
            'pitch_y' => round($this->cellHeight() + $this->rowGutter(), 2),
        ];
    }
}
