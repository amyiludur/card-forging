<?php

namespace Tests\Unit;

use App\Support\Colour;
use PHPUnit\Framework\TestCase;

/**
 * The colour a card type or a character carries, turned into a printable head
 * band. resources/js/colour.js is the browser's copy of all of this; the two
 * have to agree, so anything asserted here is worth asserting there.
 */
class ColourTest extends TestCase
{
    public function test_it_reads_a_colour_the_way_a_person_writes_one(): void
    {
        $this->assertSame('#aabbcc', Colour::normalise('#ABC'));
        $this->assertSame('#7f1d1d', Colour::normalise('7f1d1d'));
        $this->assertSame('#7f1d1d', Colour::normalise('  #7F1D1D '));
    }

    public function test_anything_that_is_not_a_colour_is_no_colour_at_all(): void
    {
        // Not an exception and not black: a card with no colour prints the
        // dark head it always printed.
        foreach ([null, '', 'red', '#12345', 'rgb(1,2,3)'] as $value) {
            $this->assertNull(Colour::normalise($value), var_export($value, true).' should not be a colour');
        }
    }

    public function test_the_ink_is_whichever_reads_over_the_colour(): void
    {
        $this->assertSame(Colour::LIGHT_INK, Colour::ink('#7f1d1d'));
        $this->assertSame(Colour::DARK_INK, Colour::ink('#fde68a'));
        // No colour is the dark default head, which takes the light ink.
        $this->assertSame(Colour::LIGHT_INK, Colour::ink());
    }

    public function test_a_two_stop_band_is_inked_against_both_stops(): void
    {
        // Not against the first alone: a band is only as readable as its worst
        // stop, and the halo is what covers the rest.
        $this->assertSame(Colour::ink('#7f1d1d', '#fde68a'), Colour::ink('#fde68a', '#7f1d1d'));
        $this->assertNotSame(Colour::ink('#7f1d1d', '#fde68a'), Colour::halo('#7f1d1d', '#fde68a'));
    }

    public function test_a_type_label_is_darkened_only_as_far_as_it_has_to_be(): void
    {
        $paper = Colour::luminance(Colour::PAPER);

        foreach (['#fde68a', '#7f1d1d', '#22c55e', '#ffffff'] as $colour) {
            $label = Colour::onPaper($colour);

            $this->assertGreaterThanOrEqual(
                4.5,
                Colour::contrast($paper, Colour::luminance($label)),
                "{$colour} was not darkened enough to read on the card body",
            );
        }

        // A colour already dark enough is left exactly as the designer picked it.
        $this->assertSame('#7f1d1d', Colour::onPaper('#7f1d1d'));
    }

    public function test_one_colour_is_a_flat_band_and_two_are_a_gradient(): void
    {
        $this->assertSame('#7f1d1d', Colour::band('#7f1d1d'));
        // The same colour twice is still flat: a split card whose halves share
        // a type should not print a gradient of one colour.
        $this->assertSame('#7f1d1d', Colour::band('#7f1d1d', '#7f1d1d'));
        $this->assertSame('linear-gradient(135deg, #7f1d1d, #fde68a)', Colour::band('#7f1d1d', '#fde68a', '135deg'));
        // One colour missing falls back to the other rather than to black.
        $this->assertSame('#fde68a', Colour::band(null, '#fde68a'));
        $this->assertNull(Colour::band(null, null));
    }
}
