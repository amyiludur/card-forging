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

    public function test_it_writes_the_scenarios_dread_rule_onto_the_card(): void
    {
        $html = $this->markup()
            ->withDreadRule('Create a Tentacle and add 5 omen to the pool.')
            ->toHtml('On reveal: {dreadRule}');

        $this->assertStringContainsString('On reveal: Create a Tentacle and add 5 omen to the pool.', $html);
        $this->assertStringNotContainsString('{dreadRule}', $html);
    }

    public function test_the_dread_rules_own_markup_renders_on_the_card(): void
    {
        // The rule goes in as the designer wrote it, so a card quoting it draws
        // the same icons, keywords and numbers the scenario page does.
        $html = $this->markup()
            ->withDreadRule('Add {config:startingOmen} {omen}. This is {unique}.')
            ->toHtml('{dreadRule}');

        $this->assertStringContainsString('markup-config', $html);
        $this->assertStringContainsString('markup-icon-omen', $html);
        $this->assertStringContainsString('markup-keyword-unique', $html);
        $this->assertStringContainsString('4', $html);
    }

    public function test_auto_icons_reach_inside_the_dread_rule(): void
    {
        $html = $this->markup()
            ->withDreadRule('Add 5 omen to the pool.')
            ->toHtml('{dreadRule}', autoIcons: true);

        $this->assertStringContainsString('5 <span class="markup-icon markup-icon-omen"', $html);
    }

    public function test_a_line_break_in_the_dread_rule_is_a_line_break_on_the_card(): void
    {
        $html = $this->markup()->withDreadRule("one\ntwo")->toHtml('{dreadRule}');

        $this->assertSame('one<br>two', $html);
    }

    public function test_the_dread_rule_is_written_out_as_typed(): void
    {
        // Substituted through a callback, so $1 and \0 in a rule are just text.
        $html = $this->markup()->withDreadRule('Pay $1 <b>now</b>')->toHtml('{dreadRule}');

        $this->assertSame('Pay $1 &lt;b&gt;now&lt;/b&gt;', $html);
    }

    public function test_a_card_with_no_scenario_reports_the_unfilled_token(): void
    {
        // A module card, or a player card: there is no one scenario behind it,
        // so there is no one rule. Reported rather than quietly dropped.
        $html = $this->markup()->toHtml('On reveal: {dreadRule}');

        $this->assertStringContainsString('<span class="markup-missing">?dreadRule</span>', $html);
    }

    public function test_a_scenario_with_no_dread_effect_written_reports_it_too(): void
    {
        $this->assertStringContainsString(
            'markup-missing',
            $this->markup()->withDreadRule('   ')->toHtml('{dreadRule}')
        );
    }

    public function test_a_dread_rule_naming_itself_does_not_loop(): void
    {
        $html = $this->markup()->withDreadRule('See {dreadRule}.')->toHtml('{dreadRule}');

        $this->assertStringContainsString('See <span class="markup-missing">?dreadRule</span>.', $html);
    }

    public function test_plain_rendering_writes_the_dread_rule_and_leaves_an_unfilled_one_as_typed(): void
    {
        $this->assertSame(
            'Add 4 ◆.',
            $this->markup()->withDreadRule('Add {config:startingOmen} {omen}.')->toPlain('{dreadRule}')
        );

        // No red span in a design-folder diff: the token stays as the designer
        // typed it, the way an unknown token does.
        $this->assertSame('{dreadRule}', $this->markup()->toPlain('{dreadRule}'));
    }

    public function test_the_dread_rule_is_not_a_keyword_token(): void
    {
        // camelCase, so it can never collide with a keyword the designer names,
        // and a keyword library that has no idea about it changes nothing.
        $this->assertSame([], $this->markup()->keywordReferences('{dreadRule}'));
    }
}
