<?php

namespace Tests\Unit;

use App\Support\PrintOptions;
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
}
