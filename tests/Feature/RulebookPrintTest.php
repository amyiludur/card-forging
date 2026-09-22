<?php

namespace Tests\Feature;

use App\Models\Keyword;
use App\Models\RuleDocument;
use App\Models\RulesConfig;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RulebookPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    public function test_the_options_page_reports_the_page_it_will_use(): void
    {
        $this->get('/print/rules')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Rulebook')
                ->where('layout.sheet_w', 210)
                ->where('layout.sheet_h', 297)
                ->where('layout.text_w', 174)
                ->where('layout.overflows', false)
                ->where('counts.documents', RuleDocument::count())
                ->where('counts.printing', RuleDocument::count())
            );
    }

    public function test_the_sheet_prints_every_document_in_the_order_they_are_sorted(): void
    {
        $html = $this->get('/print/rules/sheet')->assertOk()->getContent();

        // A document's heading ids are prefixed with its slug, and only its own
        // section carries them — the contents links to them by href — so this
        // is where each document actually starts.
        $documents = RuleDocument::orderBy('sort')->get();

        $this->assertGreaterThan(1, $documents->count());

        $positions = $documents->map(function (RuleDocument $document) use ($html) {
            $at = strpos($html, 'id="'.$document->slug.'-');
            $this->assertNotFalse($at, "The rulebook did not print \"{$document->title}\".");

            return $at;
        });

        $this->assertSame($positions->sort()->values()->all(), $positions->all());
    }

    public function test_the_markdown_becomes_headings_lists_and_tables(): void
    {
        $html = $this->get('/print/rules/sheet')->getContent();

        $this->assertStringContainsString('<h2 id="03-card-types-types">Types</h2>', $html);
        $this->assertStringContainsString('<thead><tr><th>Type</th><th>What it does</th></tr>', $html);
        // The separator row under a header is what makes it a header, and is
        // never printed as a row of its own.
        $this->assertStringNotContainsString('<td>---</td>', $html);
        $this->assertStringContainsString('<ol><li>', $html);
        $this->assertStringContainsString('<ul><li>', $html);
    }

    public function test_a_nested_bullet_prints_inside_the_item_above_it(): void
    {
        $html = $this->get('/print/rules/sheet')->getContent();

        // The design folder indents bullets under a numbered step. A nested
        // list beside its item rather than inside it is not a list at all.
        $this->assertMatchesRegularExpression('#<li>(?:(?!</li>).)*<ul><li>#s', $html);
        $this->assertStringNotContainsString('</li><ul>', $html);
        $this->assertStringNotContainsString('<ol><ul>', $html);
    }

    /**
     * The same rule the card sheet follows: the PDF is rendered from file://,
     * where a stylesheet link or a webfont URL does not load.
     */
    public function test_the_rulebook_carries_no_stylesheet_link_and_no_webfont(): void
    {
        $html = $this->get('/print/rules/sheet')->getContent();

        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringNotContainsString('@font-face', $html);
    }

    public function test_an_icon_token_in_the_rules_prints_as_inline_svg(): void
    {
        RuleDocument::orderBy('sort')->firstOrFail()->update(['body' => "# Omens\n\nAdd 1 {omen} to the pool."]);

        $html = $this->get('/print/rules/sheet')->getContent();

        $this->assertStringContainsString('markup-icon markup-icon-omen', $html);
        $this->assertStringContainsString('<svg class="icon"', $html);
    }

    public function test_a_run_can_name_the_one_document_it_prints(): void
    {
        $first = RuleDocument::orderBy('sort')->firstOrFail();
        $other = RuleDocument::orderBy('sort')->skip(1)->firstOrFail();

        $html = $this->get('/print/rules/sheet?only=rules:'.$first->id)->getContent();

        $this->assertStringContainsString($first->title, $html);
        $this->assertStringNotContainsString($other->title, $html);
        // Left out, not missing: the sheet says how many were held back.
        $this->assertStringContainsString('documents left out of this run', $html);
    }

    public function test_a_run_can_name_what_it_holds_back_instead(): void
    {
        $first = RuleDocument::orderBy('sort')->firstOrFail();

        $html = $this->get('/print/rules/sheet?except=rules:'.$first->id)->getContent();

        $this->assertStringNotContainsString('<h1 id="'.$first->slug.'-', $html);
        $this->assertStringContainsString('1 document left out of this run', $html);
    }

    public function test_a_run_with_nothing_in_it_says_so_rather_than_looking_empty(): void
    {
        $html = $this->get('/print/rules/sheet?except='.RuleDocument::pluck('id')->map(fn ($id) => 'rules:'.$id)->implode(','))
            ->getContent();

        $this->assertStringContainsString('Every document was left out of this run', $html);
    }

    public function test_the_contents_links_to_the_headings_it_lists(): void
    {
        $html = $this->get('/print/rules/sheet')->getContent();

        $this->assertStringContainsString('<h1>Contents</h1>', $html);
        $this->assertStringContainsString('href="#03-card-types-types"', $html);
        $this->assertStringContainsString('<h2 id="03-card-types-types">', $html);
    }

    public function test_the_contents_can_be_turned_off(): void
    {
        $html = $this->get('/print/rules/sheet?contents=0')->getContent();

        $this->assertStringNotContainsString('<h1>Contents</h1>', $html);
    }

    /**
     * Two documents can each have a "Rules" heading, and an id that is not its
     * own sends the contents link to the wrong one.
     */
    public function test_two_documents_with_the_same_heading_get_their_own_ids(): void
    {
        RuleDocument::create(['slug' => 'alpha', 'title' => 'Alpha', 'body' => "# Alpha\n\n## Shared\n", 'sort' => 90]);
        RuleDocument::create(['slug' => 'beta', 'title' => 'Beta', 'body' => "# Beta\n\n## Shared\n", 'sort' => 91]);

        $html = $this->get('/print/rules/sheet')->getContent();

        $this->assertStringContainsString('id="alpha-shared"', $html);
        $this->assertStringContainsString('id="beta-shared"', $html);
    }

    public function test_a_document_that_says_the_same_thing_twice_still_gets_two_ids(): void
    {
        RuleDocument::create(['slug' => 'twice', 'title' => 'Twice', 'body' => "## Same\n\n## Same\n", 'sort' => 92]);

        $html = $this->get('/print/rules/sheet?only=rules:'.RuleDocument::where('slug', 'twice')->value('id'))->getContent();

        $this->assertStringContainsString('id="twice-same"', $html);
        $this->assertStringContainsString('id="twice-same-2"', $html);
    }

    public function test_the_tunable_numbers_appendix_flags_the_placeholders(): void
    {
        $placeholder = RulesConfig::where('is_placeholder', true)->firstOrFail();

        $html = $this->get('/print/rules/sheet?tunable_numbers=1')->getContent();

        $this->assertStringContainsString('Tunable numbers', $html);
        $this->assertStringContainsString($placeholder->key, $html);
        $this->assertStringContainsString('<span class="flag">placeholder</span>', $html);
    }

    public function test_the_appendix_prints_a_number_the_way_the_rules_text_quotes_it(): void
    {
        RulesConfig::where('key', 'startingOmen')->update(['value' => ['v' => 4]]);

        $html = $this->get('/print/rules/sheet?tunable_numbers=1')->getContent();

        // Same markup the rules text runs through, so the two cannot disagree.
        $this->assertStringContainsString('<span class="markup-config">4</span>', $html);
    }

    public function test_the_appendices_are_left_out_unless_they_are_asked_for(): void
    {
        $html = $this->get('/print/rules/sheet')->getContent();

        $this->assertStringNotContainsString('id="appendix-tunable-numbers"', $html);
        $this->assertStringNotContainsString('id="appendix-keywords"', $html);
    }

    public function test_the_keyword_glossary_draws_each_keyword_the_way_a_card_does(): void
    {
        Keyword::query()->delete();
        Keyword::create(['token' => 'unique', 'name' => 'Unique', 'description' => 'Only one in play.', 'sort' => 1]);

        $html = $this->get('/print/rules/sheet?keyword_glossary=1')->getContent();

        $this->assertStringContainsString('markup-keyword markup-keyword-unique', $html);
        $this->assertStringContainsString('Only one in play.', $html);
        $this->assertStringContainsString('{unique}', html_entity_decode($html));
    }

    public function test_an_empty_keyword_library_says_so_rather_than_printing_nothing(): void
    {
        Keyword::query()->delete();

        $html = $this->get('/print/rules/sheet?keyword_glossary=1')->getContent();

        $this->assertStringContainsString('No keywords have been written yet.', $html);
    }

    /**
     * A rulebook taken to a playtest should say which of the numbers it quotes
     * are not decided yet. Report, don't correct: nothing is left out.
     */
    public function test_the_sheet_names_the_placeholder_numbers_the_rules_quote(): void
    {
        RulesConfig::where('key', 'startingOmen')->update(['is_placeholder' => true]);
        RuleDocument::orderBy('sort')->firstOrFail()->update(['body' => "# Omens\n\nStart on {config:startingOmen}."]);

        $html = $this->get('/print/rules/sheet')->getContent();

        $this->assertStringContainsString('placeholder', $html);
        $this->assertStringContainsString('startingOmen', $html);
    }

    public function test_the_page_is_laid_out_as_asked_and_travels_in_the_query_string(): void
    {
        $html = $this->get('/print/rules/sheet?sheet_size=letter&columns=2&margin=12&margin_left=25&font_size=9')
            ->getContent();

        $this->assertStringContainsString('size: Letter;', $html);
        $this->assertStringContainsString('margin: 12mm 12mm 12mm 25mm;', $html);
        $this->assertStringContainsString('column-count: 2;', $html);
        $this->assertStringContainsString('font-size: 9pt;', $html);
    }

    /** An edge left empty is absent, not zero: it follows the shared margin. */
    public function test_an_empty_edge_follows_the_shared_margin(): void
    {
        $html = $this->get('/print/rules/sheet?margin=15&margin_top=')->getContent();

        $this->assertStringContainsString('margin: 15mm 15mm 15mm 15mm;', $html);
    }

    public function test_a_zero_edge_is_a_measurement_and_is_used_as_given(): void
    {
        $html = $this->get('/print/rules/sheet?margin=15&margin_top=0')->getContent();

        $this->assertStringContainsString('margin: 0mm 15mm 15mm 15mm;', $html);
    }

    public function test_margins_that_leave_no_room_are_reported_not_shrunk(): void
    {
        $this->get('/print/rules?margin=60&columns=3')
            ->assertInertia(fn ($page) => $page->where('layout.overflows', true));

        $html = $this->get('/print/rules/sheet?margin=60&columns=3')->getContent();

        $this->assertStringContainsString('leave no room to print in', $html);
        // Reported, and still rendered: the designer sees what they asked for.
        $this->assertStringContainsString('margin: 60mm 60mm 60mm 60mm;', $html);
    }

    public function test_a_custom_sheet_prints_at_its_own_size(): void
    {
        $html = $this->get('/print/rules/sheet?sheet_size=custom&custom_sheet_width=148&custom_sheet_height=210')
            ->getContent();

        $this->assertStringContainsString('size: 148mm 210mm;', $html);
    }

    public function test_a_custom_sheet_with_no_size_falls_back_to_a4(): void
    {
        $html = $this->get('/print/rules/sheet?sheet_size=custom')->getContent();

        $this->assertStringContainsString('size: 210mm 297mm;', $html);
    }

    public function test_each_document_starts_a_new_page_unless_that_is_turned_off(): void
    {
        $this->assertStringContainsString(
            'break-before: page;',
            $this->get('/print/rules/sheet')->getContent()
        );

        $this->assertStringNotContainsString(
            'break-before: page;',
            $this->get('/print/rules/sheet?new_page_per_document=0')->getContent()
        );
    }

    public function test_it_prints_nothing_rather_than_a_blank_book_when_there_are_no_documents(): void
    {
        RuleDocument::query()->delete();

        $html = $this->get('/print/rules/sheet')->assertOk()->getContent();

        $this->assertStringContainsString('There are no rules documents yet.', $html);
    }

    public function test_a_mangled_key_prints_the_rulebook_rather_than_nothing(): void
    {
        $html = $this->get('/print/rules/sheet?except=not-a-key')->getContent();

        $this->assertStringContainsString(RuleDocument::orderBy('sort')->value('title'), $html);
    }
}
