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
        ], [
            'unique' => [
                'name' => 'Unique',
                'icon' => null,
                'show_name' => true,
                'plain' => 'Unique',
                'description' => 'Only one copy in a deck.',
                'is_placeholder' => true,
            ],
            'bottom-draw' => [
                'name' => 'Bottom draw',
                'icon' => 'zone-deck',
                'show_name' => true,
                'plain' => 'Bottom draw',
                'description' => null,
                'is_placeholder' => false,
            ],
            'omen-mark' => [
                'name' => 'Omen mark',
                'icon' => 'omen',
                'show_name' => false,
                'plain' => '◆',
                'description' => null,
                'is_placeholder' => false,
            ],
        ]);
    }

    public function test_it_renders_a_keyword_the_designer_defined(): void
    {
        $html = $this->markup()->toHtml('This card is {unique}.');

        $this->assertStringContainsString('markup-keyword-unique', $html);
        $this->assertStringContainsString('Unique', $html);
        // A keyword still being decided reads as a draft, like a placeholder number.
        $this->assertStringContainsString('markup-keyword-placeholder', $html);
        // Its definition travels with it, so nobody has to remember it.
        $this->assertStringContainsString('Only one copy in a deck.', $html);
        $this->assertStringNotContainsString('{unique}', $html);
    }

    public function test_a_keyword_token_may_carry_a_hyphen(): void
    {
        $html = $this->markup()->toHtml('Use {bottom-draw} twice.');

        $this->assertStringContainsString('markup-keyword-bottom-draw', $html);
        // Its icon is inline SVG, the same as an icon token's.
        $this->assertStringContainsString('<svg class="icon"', $html);
        $this->assertStringNotContainsString('markup-keyword-placeholder', $html);
    }

    public function test_a_keyword_can_print_its_icon_alone(): void
    {
        $html = $this->markup()->toHtml('Take an {omen-mark}.');

        // The icon is the whole of it: nothing follows the svg inside the span.
        $this->assertStringContainsString('<svg class="icon"', $html);
        $this->assertStringContainsString('</svg></span>', $html);
        $this->assertStringNotContainsString('&nbsp;', $html);
    }

    public function test_an_icon_token_wins_over_a_keyword_of_the_same_name(): void
    {
        // The game's own symbols are in code, so a keyword cannot shadow one.
        $markup = new Markup([], ['omen' => ['name' => 'Not the omen', 'plain' => 'nope']]);

        $this->assertStringContainsString('markup-icon-omen', $markup->toHtml('{omen}'));
        $this->assertSame('◆', $markup->toPlain('{omen}'));
    }

    public function test_plain_text_writes_a_keyword_as_words(): void
    {
        // A design-folder diff reads as text, so a keyword writes its name.
        $this->assertSame(
            'This card is Unique, with Bottom draw.',
            $this->markup()->toPlain('This card is {unique}, with {bottom-draw}.')
        );
    }

    public function test_it_lists_the_keywords_a_piece_of_text_uses(): void
    {
        $this->assertSame(
            ['unique', 'bottom-draw'],
            $this->markup()->keywordReferences('{unique} and {bottom-draw} and {unique} and {sausage}')
        );
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
        // Including one that was a keyword until the designer deleted it.
        $this->assertSame('A {was-a-keyword}.', $this->markup()->toPlain('A {was-a-keyword}.'));
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
