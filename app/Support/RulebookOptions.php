<?php

namespace App\Support;

use Illuminate\Http\Request;

/**
 * How the rulebook is laid out on paper.
 *
 * The card sheet's problem is a grid of fixed shapes; this one's is a column of
 * text that has to break over as many pages as it takes. So the two share the
 * paper and nothing else: the sheet size and the "an edge left empty is absent,
 * not zero" rule come from {@see PrintOptions} rather than being written twice,
 * and everything below is the rulebook's own.
 *
 * Like every other print setting, all of it travels in the query string, so a
 * rulebook laid out once is a bookmark.
 */
class RulebookOptions
{
    /** The widest sensible measure before a line stops being readable. */
    public const MAX_COLUMNS = 3;

    public function __construct(
        public string $sheetSize = 'a4',
        public float $customSheetWidth = 0,
        public float $customSheetHeight = 0,
        public float $margin = 18,
        // Null, not 0: an edge with no measurement of its own follows $margin.
        // Same rule, and the same reason, as the card sheet's four margins.
        public ?float $marginTop = null,
        public ?float $marginRight = null,
        public ?float $marginBottom = null,
        public ?float $marginLeft = null,
        public int $columns = 1,
        public float $fontSize = 10.5,
        public float $columnGap = 8,
        // A rulebook is several documents, and a new one starting half way
        // down a page reads as a section of the one above it.
        public bool $newPagePerDocument = true,
        public bool $contents = true,
        // The two appendices the tool can build out of the designer's own
        // data. Off by default: a rulebook that quotes its numbers through
        // {config:...} may not want them listed again at the back.
        public bool $tunableNumbers = false,
        public bool $keywordGlossary = false,
    ) {
    }

    public static function fromRequest(Request $request): self
    {
        return new self(
            sheetSize: $request->string('sheet_size', 'a4')->toString(),
            customSheetWidth: max(0, min(2000, (float) $request->input('custom_sheet_width', 0))),
            customSheetHeight: max(0, min(2000, (float) $request->input('custom_sheet_height', 0))),
            margin: max(0, min(60, (float) $request->input('margin', 18))),
            marginTop: PrintOptions::optionalLength($request, 'margin_top', 0, 60),
            marginRight: PrintOptions::optionalLength($request, 'margin_right', 0, 60),
            marginBottom: PrintOptions::optionalLength($request, 'margin_bottom', 0, 60),
            marginLeft: PrintOptions::optionalLength($request, 'margin_left', 0, 60),
            columns: max(1, min(self::MAX_COLUMNS, (int) $request->input('columns', 1))),
            fontSize: max(6, min(18, (float) $request->input('font_size', 10.5))),
            columnGap: max(0, min(30, (float) $request->input('column_gap', 8))),
            newPagePerDocument: $request->boolean('new_page_per_document', true),
            contents: $request->boolean('contents', true),
            tunableNumbers: $request->boolean('tunable_numbers'),
            keywordGlossary: $request->boolean('keyword_glossary'),
        );
    }

    public function sheet(): array
    {
        return PrintOptions::sheetFor($this->sheetSize, $this->customSheetWidth, $this->customSheetHeight);
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

    /** The measure: how much of the paper's width the text actually gets. */
    public function textWidth(): float
    {
        return $this->sheet()['w'] - $this->leftMargin() - $this->rightMargin();
    }

    /**
     * True when the margins have left no room to print in. Reported rather
     * than corrected, the same as a card grid that runs off the paper.
     */
    public function overflows(): bool
    {
        $width = $this->textWidth() - ($this->columns - 1) * $this->columnGap;

        return $width <= 0
            || $this->sheet()['h'] - $this->topMargin() - $this->bottomMargin() <= 0
            // Under about 25mm a column holds two or three words a line.
            || $width / $this->columns < 25;
    }

    public function toArray(): array
    {
        return [
            'sheet_size' => $this->sheetSize,
            'custom_sheet_width' => $this->customSheetWidth,
            'custom_sheet_height' => $this->customSheetHeight,
            'margin' => $this->margin,
            'margin_top' => $this->marginTop,
            'margin_right' => $this->marginRight,
            'margin_bottom' => $this->marginBottom,
            'margin_left' => $this->marginLeft,
            'columns' => $this->columns,
            'font_size' => $this->fontSize,
            'column_gap' => $this->columnGap,
            'new_page_per_document' => $this->newPagePerDocument,
            'contents' => $this->contents,
            'tunable_numbers' => $this->tunableNumbers,
            'keyword_glossary' => $this->keywordGlossary,
        ];
    }

    /** What the options page reports back: the page these settings really give. */
    public function layout(): array
    {
        $sheet = $this->sheet();

        return [
            'sheet' => $sheet['label'],
            'sheet_w' => round($sheet['w'], 2),
            'sheet_h' => round($sheet['h'], 2),
            'text_w' => round($this->textWidth(), 2),
            'text_h' => round($sheet['h'] - $this->topMargin() - $this->bottomMargin(), 2),
            'column_w' => round(($this->textWidth() - ($this->columns - 1) * $this->columnGap) / $this->columns, 2),
            'overflows' => $this->overflows(),
        ];
    }
}
