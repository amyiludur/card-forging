<?php

namespace Tests\Unit;

use App\Support\Markup;
use PHPUnit\Framework\TestCase;

class MarkupTest extends TestCase
{
    private function markup(): Markup
    {
        return new Markup([
            'startingOmen' => 4,
            'goldCarriesOver' => false,
            'omenPerCardPlayedRange' => [0, 2],
            'handSize' => null,
        ]);
    }

    public function test_it_renders_icon_tokens_as_inline_svg(): void
    {
        $html = $this->markup()->toHtml('Add 2 {omen} to the pool.');

        // Inline SVG, so the printed sheet keeps its icons from file://.
        $this->assertStringContainsString('markup-icon-omen', $html);
        $this->assertStringContainsString('<svg class="icon"', $html);
        $this->assertStringNotContainsString('{omen}', $html);
        $this->assertStringNotContainsString('<link', $html);
    }

    public function test_plain_text_keeps_the_characters(): void
    {
        // A diff of the design folder has to stay readable as text.
        $this->assertSame('Add 2 ◆ to the pool.', $this->markup()->toPlain('Add 2 {omen} to the pool.'));
    }

    public function test_a_typed_line_break_is_a_line_break_on_the_card(): void
    {
        $html = $this->markup()->toHtml("Deal 1 {damage}.\nThen draw a card.");

        $this->assertStringContainsString('.<br>Then draw', $html);
        // A blank line between two lines gives two breaks, as typed.
        $this->assertStringContainsString('one<br><br>two', $this->markup()->toHtml("one\n\ntwo"));
        // Windows line endings are the same line break.
        $this->assertStringContainsString('one<br>two', $this->markup()->toHtml("one\r\ntwo"));
    }

    public function test_plain_text_keeps_the_newline_itself(): void
    {
        // The design folder stores the text as typed; only the HTML gets a tag.
        $this->assertSame("one\ntwo", $this->markup()->toPlain("one\ntwo"));
    }

    public function test_a_typed_break_tag_is_still_escaped(): void
    {
        // The break comes from the newline, never from text the designer typed.
        $html = $this->markup()->toHtml('Not <br> a break.');

        $this->assertStringContainsString('&lt;br&gt;', $html);
        $this->assertStringNotContainsString('Not <br> a', $html);
    }

    public function test_an_unknown_token_is_left_alone(): void
    {
        $this->assertStringContainsString('{sausage}', $this->markup()->toHtml('A {sausage}.'));
    }

    public function test_it_renders_config_references(): void
    {
        $html = $this->markup()->toHtml('The pool starts at {config:startingOmen}.');

        $this->assertStringContainsString('>4</span>', $html);
    }

    public function test_it_formats_non_integer_config_values(): void
    {
        $markup = $this->markup();

        $this->assertStringContainsString('>no</span>', $markup->toHtml('{config:goldCarriesOver}'));
        $this->assertStringContainsString('>0 to 2</span>', $markup->toHtml('{config:omenPerCardPlayedRange}'));
        $this->assertStringContainsString('>—</span>', $markup->toHtml('{config:handSize}'));
    }

    public function test_it_flags_an_unknown_config_key(): void
    {
        $this->assertStringContainsString('?nope', $this->markup()->toHtml('{config:nope}'));
    }

    public function test_it_escapes_html_in_card_text(): void
    {
        $html = $this->markup()->toHtml('<script>alert(1)</script>');

        $this->assertStringNotContainsString('<script>', $html);
    }

    public function test_auto_icons_only_fire_after_a_number(): void
    {
        $html = $this->markup()->toHtml('Add 2 omen. The omen pool empties.', autoIcons: true);

        $this->assertStringContainsString('2 <span', $html);
        $this->assertStringContainsString('The omen pool empties.', $html);
    }

    public function test_plain_rendering_keeps_the_values(): void
    {
        $this->assertSame('Pool starts at 4 ◆.', $this->markup()->toPlain('Pool starts at {config:startingOmen} {omen}.'));
    }

    public function test_it_lists_config_references(): void
    {
        $this->assertSame(
            ['startingOmen', 'handSize'],
            $this->markup()->references('{config:startingOmen} and {config:handSize} and {config:startingOmen}')
        );
    }
}
