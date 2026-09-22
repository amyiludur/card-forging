<?php

namespace Tests\Unit;

use App\Support\Markup;
use App\Support\RulesMarkdown;
use PHPUnit\Framework\TestCase;

class RulesMarkdownTest extends TestCase
{
    private function render(string $text, string $anchor = ''): string
    {
        return (new RulesMarkdown(new Markup(['startingOmen' => 4], [])))->toHtml($text, $anchor);
    }

    public function test_a_heading_carries_an_id_built_from_its_own_words(): void
    {
        $this->assertSame('<h2 id="turn-structure">Turn Structure</h2>', $this->render('## Turn Structure'));
    }

    public function test_a_heading_id_is_prefixed_per_document(): void
    {
        $this->assertSame('<h2 id="core-turn-structure">Turn Structure</h2>', $this->render('## Turn Structure', 'core'));
    }

    /** Two headings in one document that say the same thing are still two headings. */
    public function test_a_repeated_heading_is_numbered(): void
    {
        $html = $this->render("## Same\n## Same\n## Same");

        $this->assertStringContainsString('id="same"', $html);
        $this->assertStringContainsString('id="same-2"', $html);
        $this->assertStringContainsString('id="same-3"', $html);
    }

    /**
     * An id is built from the heading's own words, not from what a token
     * happens to resolve to today: changing a tunable number must not silently
     * move a heading's id and break the contents link pointing at it.
     */
    public function test_a_token_in_a_heading_does_not_reach_the_id(): void
    {
        $this->assertStringContainsString(
            'id="start-on-omens"',
            $this->render('## Start on {config:startingOmen} omens')
        );
    }

    public function test_a_heading_with_no_words_of_its_own_still_gets_an_id(): void
    {
        $this->assertStringContainsString('id="doc-section"', $this->render('## {omen}', 'doc'));
    }

    public function test_the_headings_list_matches_the_ids_in_the_html(): void
    {
        $markdown = "# Rules\n\n## Turn Structure\n\n## Turn Structure\n";
        $markup = new Markup;

        $headings = (new RulesMarkdown($markup))->headings($markdown, 'core');
        $html = (new RulesMarkdown($markup))->toHtml($markdown, 'core');

        $this->assertCount(3, $headings);

        foreach ($headings as $heading) {
            $this->assertStringContainsString('id="'.$heading['id'].'"', $html);
        }

        $this->assertSame(['core-rules', 'core-turn-structure', 'core-turn-structure-2'], array_column($headings, 'id'));
        $this->assertSame([1, 2, 2], array_column($headings, 'level'));
    }

    /** The contents lists the designer's words, not the emphasis marks. */
    public function test_a_headings_text_drops_the_markup_around_it(): void
    {
        $headings = (new RulesMarkdown(new Markup))->headings('## The **Omen** pool');

        $this->assertSame('The Omen pool', $headings[0]['text']);
    }

    public function test_bullets_become_one_list(): void
    {
        $this->assertSame('<ul><li>one</li><li>two</li></ul>', $this->render("- one\n- two"));
    }

    public function test_numbers_become_an_ordered_list(): void
    {
        $this->assertSame('<ol><li>one</li><li>two</li></ol>', $this->render("1. one\n2. two"));
    }

    /** A nested list belongs inside the item above it, not beside it. */
    public function test_an_indented_bullet_nests_inside_its_item(): void
    {
        $this->assertSame(
            '<ol><li>step<ul><li>note</li></ul></li><li>next</li></ol>',
            $this->render("1. step\n   - note\n2. next")
        );
    }

    public function test_coming_back_out_of_several_levels_closes_all_of_them(): void
    {
        $this->assertSame(
            '<ul><li>a<ul><li>b<ul><li>c</li></ul></li></ul></li><li>d</li></ul>',
            $this->render("- a\n  - b\n    - c\n- d")
        );
    }

    public function test_a_list_of_the_other_kind_at_the_same_level_is_a_new_list(): void
    {
        $this->assertSame('<ul><li>a</li></ul><ol><li>b</li></ol>', $this->render("- a\n1. b"));
    }

    public function test_a_blank_line_ends_a_list(): void
    {
        $this->assertSame('<ul><li>a</li></ul><p>after</p>', $this->render("- a\n\nafter"));
    }

    public function test_the_row_above_a_separator_is_the_table_header(): void
    {
        $this->assertSame(
            '<table><thead><tr><th>Type</th><th>Effect</th></tr></thead><tbody><tr><td>Attack</td><td>Damage</td></tr></tbody></table>',
            $this->render("| Type | Effect |\n|---|---|\n| Attack | Damage |")
        );
    }

    /** A two-column list of terms is usually typed with no header at all. */
    public function test_a_table_without_a_separator_is_all_body(): void
    {
        $this->assertSame(
            '<table><tbody><tr><td>a</td><td>b</td></tr></tbody></table>',
            $this->render('| a | b |')
        );
    }

    public function test_an_alignment_row_is_read_as_a_separator(): void
    {
        $this->assertStringContainsString('<thead>', $this->render("| a | b |\n|:--|--:|\n| 1 | 2 |"));
    }

    public function test_the_three_inline_forms_render(): void
    {
        $this->assertSame(
            '<p><strong>bold</strong>, <em>italic</em> and <code>code</code></p>',
            $this->render('**bold**, *italic* and `code`')
        );
    }

    public function test_a_tunable_number_is_written_into_the_text(): void
    {
        $this->assertSame(
            '<p>Start on <span class="markup-config">4</span>.</p>',
            $this->render('Start on {config:startingOmen}.')
        );
    }

    /** A number nothing filled in is reported, not quietly dropped. */
    public function test_an_unknown_tunable_number_is_reported(): void
    {
        $this->assertStringContainsString('<span class="markup-missing">?nothing</span>', $this->render('{config:nothing}'));
    }

    public function test_html_in_the_markdown_is_escaped_not_run(): void
    {
        $this->assertSame('<p>&lt;script&gt;alert(1)&lt;/script&gt;</p>', $this->render('<script>alert(1)</script>'));
    }

    /** An emphasis mark the designer typed inside escaped text is still theirs. */
    public function test_escaping_happens_before_the_emphasis_marks_are_read(): void
    {
        $this->assertSame('<p>a &lt;b&gt; <strong>c</strong></p>', $this->render('a <b> **c**'));
    }

    public function test_an_empty_document_renders_nothing(): void
    {
        $this->assertSame('', $this->render(''));
        $this->assertSame('', $this->render("\n\n   \n"));
    }
}
