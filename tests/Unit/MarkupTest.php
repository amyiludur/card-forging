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

    public function test_a_keyword_may_carry_a_number(): void
    {
        // Fast Play 2: the number is part of the keyword, printed after its name.
        $html = $this->markup()->toHtml('{unique:2}, then draw.');

        $this->assertStringContainsString('markup-keyword-unique', $html);
        $this->assertStringContainsString('Unique&nbsp;2</span>', $html);
        $this->assertStringNotContainsString('{unique:2}', $html);
    }

    public function test_a_keyword_printing_its_icon_alone_still_prints_its_number(): void
    {
        $html = $this->markup()->toHtml('{omen-mark:3}');

        $this->assertStringContainsString('</svg>&nbsp;3</span>', $html);
    }

    public function test_plain_text_writes_a_keyword_with_its_number(): void
    {
        $this->assertSame('Unique 2, Bottom draw.', $this->markup()->toPlain('{unique:2}, {bottom-draw}.'));
    }

    public function test_a_keyword_with_a_number_is_still_a_reference_to_it(): void
    {
        $this->assertSame(['bottom-draw', 'unique'], $this->markup()->keywordReferences('{bottom-draw:2} and {unique}'));
    }

    public function test_an_icon_or_unknown_token_with_a_number_is_left_as_typed(): void
    {
        // Only a keyword takes a number; anything else is reported, not guessed at.
        $this->assertStringContainsString('{omen:2}', $this->markup()->toHtml('Add {omen:2}.'));
        $this->assertStringContainsString('{sausage:2}', $this->markup()->toHtml('A {sausage:2}.'));
        $this->assertSame('{omen:2}', $this->markup()->toPlain('{omen:2}'));
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

    public function test_card_text_takes_bold_and_italic(): void
    {
        $this->assertSame(
            '<strong>Fired</strong>: while <em>The Ocean</em> is in play',
            $this->markup()->toHtml('**Fired**: while *The Ocean* is in play')
        );
    }

    public function test_an_asterisk_that_is_not_emphasis_is_left_alone(): void
    {
        // An equation's multiplication, spaced or not, and a stray pair inside a word.
        foreach (['2 * 3 * 4', '2*3*4', 'un**b**c', '** spaced **'] as $text) {
            $this->assertSame($text, $this->markup()->toHtml($text));
        }
    }

    public function test_lines_starting_with_a_bullet_or_a_number_are_a_list(): void
    {
        $this->assertSame(
            'Choose one:<ul class="markup-list"><li>Gain 2</li><li><strong>Heal</strong> 1</li></ul>Then draw.',
            $this->markup()->toHtml("Choose one:\n- Gain 2\n* **Heal** 1\nThen draw.")
        );
        $this->assertSame(
            '<ol class="markup-list"><li>Reveal</li><li>Resolve</li></ol>',
            $this->markup()->toHtml("1. Reveal\n2) Resolve")
        );
        // A hyphen with no space after it is a word, not a bullet.
        $this->assertSame('-1 health', $this->markup()->toHtml('-1 health'));
    }

    public function test_plain_text_keeps_the_markdown_as_typed(): void
    {
        $this->assertSame("- **one**\n- *two*", $this->markup()->toPlain("- **one**\n- *two*"));
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
            ->withDread('Create a Tentacle and add 5 omen to the pool.', null)
            ->toHtml('On reveal: {dreadRule}');

        $this->assertStringContainsString('On reveal: Create a Tentacle and add 5 omen to the pool.', $html);
        $this->assertStringNotContainsString('{dreadRule}', $html);
    }

    public function test_the_dread_rules_own_markup_renders_on_the_card(): void
    {
        // The rule goes in as the designer wrote it, so a card quoting it draws
        // the same icons, keywords and numbers the scenario page does.
        $html = $this->markup()
            ->withDread('Add {config:startingOmen} {omen}. This is {unique}.', null)
            ->toHtml('{dreadRule}');

        $this->assertStringContainsString('markup-config', $html);
        $this->assertStringContainsString('markup-icon-omen', $html);
        $this->assertStringContainsString('markup-keyword-unique', $html);
        $this->assertStringContainsString('4', $html);
    }

    public function test_auto_icons_reach_inside_the_dread_rule(): void
    {
        $html = $this->markup()
            ->withDread('Add 5 omen to the pool.', null)
            ->toHtml('{dreadRule}', autoIcons: true);

        $this->assertStringContainsString('5 <span class="markup-icon markup-icon-omen"', $html);
    }

    public function test_a_line_break_in_the_dread_rule_is_a_line_break_on_the_card(): void
    {
        $html = $this->markup()->withDread("one\ntwo", null)->toHtml('{dreadRule}');

        $this->assertSame('one<br>two', $html);
    }

    public function test_the_dread_rule_is_written_out_as_typed(): void
    {
        // Substituted through a callback, so $1 and \0 in a rule are just text.
        $html = $this->markup()->withDread('Pay $1 <b>now</b>', null)->toHtml('{dreadRule}');

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
            $this->markup()->withDread('   ', null)->toHtml('{dreadRule}')
        );
    }

    public function test_a_dread_rule_naming_itself_does_not_loop(): void
    {
        $html = $this->markup()->withDread('See {dreadRule}.', null)->toHtml('{dreadRule}');

        $this->assertStringContainsString('See <span class="markup-missing">?dreadRule</span>.', $html);
    }

    public function test_plain_rendering_writes_the_dread_rule_and_leaves_an_unfilled_one_as_typed(): void
    {
        $this->assertSame(
            'Add 4 ◆.',
            $this->markup()->withDread('Add {config:startingOmen} {omen}.', null)->toPlain('{dreadRule}')
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

    public function test_it_writes_the_scenarios_starting_dread_onto_the_card(): void
    {
        $html = $this->markup()->withDread(null, '2')->toHtml('Set the dial to {dreadAmount}.');

        $this->assertSame('Set the dial to 2.', $html);
    }

    public function test_a_starting_dread_that_counts_the_players_prints_the_equation(): void
    {
        // A printed card cannot know how many people are at the table, so the
        // equation is what it carries, with the count drawn as the icon.
        $html = $this->markup()->withDread(null, '1 + 1{perPlayer}')->toHtml('Start on {dreadAmount}.');

        $this->assertStringContainsString('Start on 1 + 1<span class="markup-icon markup-icon-perPlayer"', $html);
        $this->assertStringNotContainsString('{perPlayer}', $html);
    }

    public function test_a_card_with_no_scenario_reports_the_unfilled_dread_amount(): void
    {
        // A module card: it is played with whichever scenario the table chose,
        // so there is no one number. Reported rather than guessed at.
        $html = $this->markup()->toHtml('Start on {dreadAmount}.');

        $this->assertStringContainsString('<span class="markup-missing">?dreadAmount</span>', $html);
    }

    public function test_a_dread_rule_may_quote_the_starting_dread(): void
    {
        // The rule goes in first, so a scenario writes the number once and the
        // sentence carries it onto every card that quotes the rule.
        $html = $this->markup()
            ->withDread('Reset the dial to {dreadAmount}.', '3')
            ->toHtml('{dreadRule}');

        $this->assertSame('Reset the dial to 3.', $html);
    }

    public function test_plain_rendering_writes_the_starting_dread_and_leaves_an_unfilled_one_as_typed(): void
    {
        // The count falls back to the words, the way it does in any other
        // equation a plain-text export writes out.
        $this->assertSame(
            'Start on 1 + 1per player.',
            $this->markup()->withDread(null, '1 + 1{perPlayer}')->toPlain('Start on {dreadAmount}.')
        );

        // No red span in a design-folder diff, the same as every other token.
        $this->assertSame('{dreadAmount}', $this->markup()->toPlain('{dreadAmount}'));
    }

    public function test_the_starting_dread_is_not_a_keyword_token(): void
    {
        // camelCase, like {dreadRule} and for the same reason.
        $this->assertSame([], $this->markup()->keywordReferences('{dreadAmount}'));
    }

    public function test_this_writes_the_cards_own_name(): void
    {
        $html = $this->markup()->withName('Tentacle Lash')->toHtml('Discard {this}.');

        $this->assertSame('Discard Tentacle Lash.', $html);
    }

    public function test_the_name_is_escaped_like_the_rest_of_the_text(): void
    {
        $html = $this->markup()->withName('Fish & <Chips>')->toHtml('{this}');

        $this->assertSame('Fish &amp; &lt;Chips&gt;', $html);
    }

    public function test_text_on_no_card_reports_this(): void
    {
        $this->assertSame(
            'Discard <span class="markup-missing">?this</span>.',
            $this->markup()->toHtml('Discard {this}.')
        );

        // A card not named yet reports it the same way.
        $this->assertStringContainsString('?this', $this->markup()->withName('  ')->toHtml('{this}'));
    }

    public function test_a_dread_rule_saying_this_names_the_card_it_is_quoted_on(): void
    {
        $html = $this->markup()
            ->withDread('Shuffle {this} into the deck.', null)
            ->withName('Tentacle Lash')
            ->toHtml('{dreadRule}');

        $this->assertSame('Shuffle Tentacle Lash into the deck.', $html);
    }

    public function test_plain_rendering_writes_the_name_and_leaves_an_unfilled_one_as_typed(): void
    {
        $this->assertSame('Discard Tentacle Lash.', $this->markup()->withName('Tentacle Lash')->toPlain('Discard {this}.'));
        $this->assertSame('Discard {this}.', $this->markup()->toPlain('Discard {this}.'));
    }

    public function test_this_is_reserved_from_the_keyword_library(): void
    {
        $this->assertContains('this', Markup::reservedTokens());
        $this->assertContains('omen', Markup::reservedTokens());
    }

    /** Keywords with and without a short reminder, for the print sheet's reminders. */
    private function reminding(): Markup
    {
        $keyword = fn (string $name, ?string $description, ?string $reminder) => [
            'name' => $name, 'icon' => null, 'show_name' => true, 'plain' => null,
            'description' => $description, 'reminder' => $reminder, 'is_placeholder' => true,
        ];

        return new Markup([], [
            'carve' => $keyword('Carve', 'Put a Rune into play, at length.', 'Put a Rune into play.'),
            'break' => $keyword('Break', 'Discard a carved Rune for its effect.', null),
            'fastplay' => $keyword('Fast Play', 'Long.', 'Pay X {omen} instead of its cost.'),
            'unique' => $keyword('Unique', 'One per deck.', null),
            'echo' => $keyword('Echo', null, 'As {unique}, but louder.'),
            'blank' => $keyword('Blank', null, null),
        ]);
    }

    public function test_reminders_come_in_order_of_first_use_once_each(): void
    {
        $reminders = $this->reminding()->reminders('{carve} then {break} then {carve}');

        $this->assertSame(['carve', 'break'], array_column($reminders, 'token'));
        $this->assertStringContainsString('<b>Carve</b>: Put a Rune into play.', $reminders[0]['html']);
    }

    public function test_a_reminder_falls_back_to_the_description(): void
    {
        $reminders = $this->reminding()->reminders('{break}');

        $this->assertStringContainsString('<b>Break</b>: Discard a carved Rune for its effect.', $reminders[0]['html']);
    }

    public function test_a_keyword_with_neither_is_not_reminded(): void
    {
        $this->assertSame([], $this->reminding()->reminders('{blank} and {nothing}'));
    }

    public function test_a_numbered_keyword_is_reminded_once(): void
    {
        $reminders = $this->reminding()->reminders('{fastplay:2} and again {fastplay:2}');

        $this->assertSame(['fastplay'], array_column($reminders, 'token'));
        // Its own icons draw, the way any card text's do.
        $this->assertStringContainsString('<svg', $reminders[0]['html']);
    }

    public function test_reminder_text_is_not_scanned_for_more_reminders(): void
    {
        $reminders = $this->reminding()->reminders('{echo}');

        $this->assertSame(['echo'], array_column($reminders, 'token'));
        $this->assertStringContainsString('Unique', $reminders[0]['html']);
    }

    public function test_seen_carries_across_blocks(): void
    {
        $markup = $this->reminding();
        $seen = [];

        $first = $markup->reminders('{carve}', $seen);
        $second = $markup->reminders('{carve} and {break}', $seen);

        $this->assertSame(['carve'], array_column($first, 'token'));
        $this->assertSame(['break'], array_column($second, 'token'));
    }
}
