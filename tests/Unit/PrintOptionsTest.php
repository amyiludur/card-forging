<?php

namespace Tests\Unit;

use App\Support\PrintOptions;
use Illuminate\Http\Request;
use PHPUnit\Framework\TestCase;

class PrintOptionsTest extends TestCase
{
    public function test_poker_cards_fit_three_by_three_on_a4(): void
    {
        $options = new PrintOptions(margin: 8);

        $this->assertSame(3, $options->columns());
        $this->assertSame(3, $options->rows());
        $this->assertSame(9, $options->perPage());
        $this->assertFalse($options->overflows());
    }

    public function test_bleed_grows_the_footprint_and_can_cost_a_column(): void
    {
        $options = new PrintOptions(bleed: 5, margin: 8);

        $this->assertSame(63.5 + 10, $options->cellWidth());
        $this->assertSame(2, $options->columns());
    }

    public function test_a_custom_size_overrides_the_stock_size(): void
    {
        $options = new PrintOptions(customWidth: 100, customHeight: 150);

        $this->assertSame(100.0, $options->cardWidth());
        $this->assertSame(150.0, $options->cardHeight());
        $this->assertSame(1, $options->columns());
    }

    public function test_it_reports_an_overflow_when_nothing_fits(): void
    {
        $options = new PrintOptions(customWidth: 300, customHeight: 400, margin: 0);

        $this->assertTrue($options->overflows());
    }

    public function test_letter_fits_a_different_grid_than_a4(): void
    {
        $a4 = new PrintOptions(sheetSize: 'a4', margin: 8);
        $letter = new PrintOptions(sheetSize: 'letter', margin: 8);

        $this->assertSame(3, $a4->rows());
        $this->assertSame(2, $letter->rows());
    }

    public function test_an_edge_margin_overrides_the_shared_one(): void
    {
        $options = new PrintOptions(margin: 8, marginTop: 20, marginLeft: 0);

        $this->assertSame(20.0, $options->topMargin());
        $this->assertSame(0.0, $options->leftMargin());
        // The edges left alone still follow the shared margin.
        $this->assertSame(8.0, $options->rightMargin());
        $this->assertSame(8.0, $options->bottomMargin());
    }

    public function test_an_asymmetric_margin_costs_a_row_the_shared_one_would_keep(): void
    {
        $this->assertSame(3, (new PrintOptions(margin: 8))->rows());
        $this->assertSame(2, (new PrintOptions(margin: 8, marginTop: 25, marginBottom: 25))->rows());
    }

    public function test_the_two_gutters_are_measured_separately(): void
    {
        $options = new PrintOptions(margin: 8, gutter: 4, gutterX: 0);

        $this->assertSame(0.0, $options->columnGutter());
        $this->assertSame(4.0, $options->rowGutter());
        $this->assertSame(3, $options->columns());
        $this->assertSame(3, $options->rows());
    }

    public function test_a_given_grid_is_used_as_given(): void
    {
        $options = new PrintOptions(margin: 8, gridColumns: 2, gridRows: 4);

        $this->assertSame(2, $options->columns());
        $this->assertSame(4, $options->rows());
        $this->assertSame(8, $options->perPage());
        // Four rows of poker cards do not fit on A4, and the tool says so
        // rather than quietly printing three.
        $this->assertTrue($options->overflows());
    }

    public function test_a_custom_sheet_size_wins_over_the_stock_one(): void
    {
        $options = new PrintOptions(sheetSize: 'custom', customSheetWidth: 100, customSheetHeight: 200);

        $this->assertSame(100.0, $options->sheet()['w']);
        $this->assertSame('100mm 200mm', $options->sheet()['css']);
    }

    public function test_a_custom_sheet_with_no_size_falls_back_to_a4(): void
    {
        $options = new PrintOptions(sheetSize: 'custom');

        $this->assertSame(210.0, $options->sheet()['w']);
        $this->assertSame(297.0, $options->sheet()['h']);
    }

    public function test_a_stock_sheet_keeps_its_named_page_size(): void
    {
        $this->assertSame('A4', (new PrintOptions)->sheet()['css']);
    }

    public function test_skipped_cells_come_out_of_the_first_sheet_only(): void
    {
        $options = new PrintOptions(margin: 8, skip: 2);

        $this->assertSame(9, $options->perPage());
        $this->assertSame(7, $options->firstPageCapacity());

        $pages = $options->paginate(collect(range(1, 10)));

        $this->assertCount(2, $pages);
        $this->assertNull($pages[0][0]);
        $this->assertNull($pages[0][1]);
        $this->assertSame(1, $pages[0][2]);
        $this->assertCount(9, $pages[0]);
        $this->assertSame([8, 9, 10], $pages[1]->all());
    }

    public function test_a_skip_cannot_swallow_a_whole_sheet(): void
    {
        $options = new PrintOptions(margin: 8, skip: 40);

        $this->assertSame(8, $options->skipped());
        $this->assertSame(1, $options->firstPageCapacity());
    }

    public function test_an_empty_override_is_absent_rather_than_zero(): void
    {
        $options = PrintOptions::fromRequest(Request::create('/print/x?margin_top=&margin_left=0&gutter_y=3.5'));

        $this->assertNull($options->marginTop);
        $this->assertSame(8.0, $options->topMargin());
        $this->assertSame(0.0, $options->leftMargin());
        $this->assertSame(3.5, $options->rowGutter());
    }

    public function test_the_printer_nudge_is_read_and_clamped(): void
    {
        $options = PrintOptions::fromRequest(Request::create('/print/x?offset_x=-1.5&offset_y=999'));

        $this->assertSame(-1.5, $options->offsetX);
        $this->assertSame(20.0, $options->offsetY);
    }
}
