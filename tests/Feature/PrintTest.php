<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\EntityCard;
use App\Models\PrintPreset;
use App\Models\TownAction;
use App\Support\Icons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    public function test_the_options_page_reports_the_grid_it_will_use(): void
    {
        $this->get('/print/kraken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Options')
                ->where('layout.columns', 3)
                ->where('layout.rows', 3)
                ->where('layout.per_page', 9)
                ->where('layout.overflows', false)
                ->where('counts.entity', 34)
                ->where('counts.beats', 4)
            );
    }

    public function test_it_prints_one_card_per_copy(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        // Tentacle Lash has a quantity of 3, so it is printed three times.
        $this->assertSame(3, substr_count($html, 'Tentacle Lash'));
        $this->assertStringContainsString('34 cards', $html);
    }

    public function test_every_card_prints_an_arrow_on_its_right_edge(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=entity')->getContent();

        // v2: one arrow per printed card, top or bottom, and no more half-rail.
        $this->assertSame(34, substr_count($html, 'class="arrow-edge'));
        $this->assertStringNotContainsString('arrow-rail', $html);
        $this->assertStringContainsString('arrow-edge arrow-top', $html);
        $this->assertStringContainsString('arrow-edge arrow-bottom', $html);
    }

    public function test_a_single_effect_card_prints_an_arrow_too(): void
    {
        EntityCard::where('name', 'Tentacle Lash')->firstOrFail()->update(['arrow' => 'bottom']);

        $html = $this->get('/print/kraken/sheet?deck=entity')->getContent();

        $this->assertMatchesRegularExpression(
            '/Tentacle Lash.*?arrow-edge arrow-bottom/s',
            $html
        );
    }

    public function test_an_x_cost_card_prints_an_x(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=entity')->getContent();

        $this->assertStringContainsString('omen omen-x', $html);
    }

    public function test_it_prints_beats_and_board_cards_as_their_own_decks(): void
    {
        $beats = $this->get('/print/kraken/sheet?deck=beats')->getContent();
        $this->assertStringContainsString('Troubled Waters', $beats);
        $this->assertStringNotContainsString('Tentacle Lash', $beats);

        // Free-text health, set here rather than read off whichever number the
        // Kraken currently carries.
        BoardCard::where('name', 'The Kraken')->firstOrFail()->update(['health' => '12 per player']);

        $board = $this->get('/print/kraken/sheet?deck=board')->getContent();
        $this->assertStringContainsString('The Ocean', $board);
        $this->assertStringContainsString('12 per player', $board);
        $this->assertStringNotContainsString('Troubled Waters', $board);
    }

    public function test_it_prints_the_town_as_its_own_deck(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=town')->assertOk()->getContent();

        $this->assertSame(3, substr_count($html, 'class="card town-card"'));
        $this->assertStringContainsString('Chapel', $html);
        // What it costs, the omen taking it adds, and the designer's own caveat.
        $this->assertStringContainsString('2'.Icons::svg('gold', 'icon pip-mark'), $html);
        $this->assertStringContainsString('+1'.Icons::svg('omen', 'icon pip-mark'), $html);
        $this->assertStringContainsString('while the Whirlpool is in play', $html);
        $this->assertStringNotContainsString('Tentacle Lash', $html);
    }

    public function test_everything_is_every_pile_the_scenario_plays_with(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=all')->assertOk()->getContent();

        // 34 entity, 6 board, 4 beats, 3 districts and 1 setup card.
        $this->assertStringContainsString('48 cards', $html);
        $this->assertStringContainsString('class="card beat-card"', $html);
        $this->assertStringContainsString('class="card board-card"', $html);
        $this->assertStringContainsString('class="card town-card"', $html);
        $this->assertStringContainsString('class="arrow-edge', $html);
    }

    public function test_the_options_page_counts_each_pile_and_lists_the_cards(): void
    {
        $this->get('/print/kraken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Options')
                ->where('counts.entity', 34)
                ->where('counts.board', 6)
                ->where('counts.beats', 4)
                ->where('counts.town', 3)
                ->where('counts.setup', 1)
                ->where('counts.all', 48)
                ->where('decks.town', 'Town cards')
                // One entry per card row, and the closure that renders the card
                // stays on the server: the picker only needs names. The setup
                // card is item 0 now that Kraken's Tide has one written.
                ->has('items.1', fn ($item) => $item
                    ->where('group', 'entity')
                    ->has('key')
                    ->has('name')
                    ->has('qty')
                    ->has('is_placeholder')
                )
                ->where('selection.only', '')
                ->where('selection.except', '')
            );
    }

    public function test_a_run_can_name_the_only_cards_it_prints(): void
    {
        $card = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();

        $html = $this->get("/print/kraken/sheet?deck=entity&only=entity:{$card->id}")->assertOk()->getContent();

        // Still three copies: picking a card prints the card, quantity and all.
        $this->assertSame(3, substr_count($html, 'Tentacle Lash'));
        $this->assertStringContainsString('3 cards · 3×3 per sheet', $html);
        $this->assertStringContainsString('31 cards left out of this run', $html);
    }

    public function test_a_run_can_leave_one_card_out_of_an_otherwise_whole_deck(): void
    {
        $card = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();

        $html = $this->get("/print/kraken/sheet?deck=entity&except=entity:{$card->id}")->assertOk()->getContent();

        $this->assertStringNotContainsString('Tentacle Lash', $html);
        $this->assertStringContainsString('31 cards · 3×3 per sheet', $html);
        $this->assertStringContainsString('3 cards left out of this run', $html);
    }

    public function test_naming_the_run_wins_over_naming_what_to_leave_out(): void
    {
        $lash = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();

        // "Only this one" is a complete answer, so the other list is not read.
        $html = $this->get(
            "/print/kraken/sheet?deck=entity&only=entity:{$lash->id}&except=entity:{$lash->id}"
        )->getContent();

        $this->assertSame(3, substr_count($html, 'Tentacle Lash'));
    }

    public function test_a_picked_card_belongs_to_its_own_pile(): void
    {
        $beat = $this->get('/print/kraken/sheet?deck=all')->getContent();
        $action = TownAction::where('name', 'Chapel')->firstOrFail();

        // A town key cannot reach an entity card, even though both are numbers.
        $html = $this->get("/print/kraken/sheet?deck=all&except=town:{$action->id}")->getContent();

        $this->assertStringContainsString('Chapel', $beat);
        $this->assertStringNotContainsString('Chapel', $html);
        $this->assertStringContainsString('Tentacle Lash', $html);
        $this->assertStringContainsString('47 cards', $html);
    }

    public function test_a_run_can_print_more_copies_of_a_card_than_its_own_quantity(): void
    {
        $card = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();

        $html = $this->get("/print/kraken/sheet?deck=entity&qty[entity:{$card->id}]=6")->assertOk()->getContent();

        // Still a card of quantity 3 in the deck, but the run asked for 6.
        $this->assertSame(6, substr_count($html, 'Tentacle Lash'));
        $this->assertStringContainsString('37 cards', $html);
        // The extra copies are not "left out"; nothing was excluded from the run.
        $this->assertStringNotContainsString('left out of this run', $html);
    }

    public function test_a_quantity_override_only_applies_to_the_card_it_names(): void
    {
        $card = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();

        $html = $this->get("/print/kraken/sheet?deck=entity&qty[entity:{$card->id}]=1")->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'Tentacle Lash'));
        // The rest of the deck still prints at its own quantity: 34 - 3 + 1 = 32.
        $this->assertStringContainsString('32 cards', $html);
    }

    public function test_a_malformed_quantity_key_is_ignored(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=entity&qty[oops]=6&qty[%3Bdrop]=9')->getContent();

        $this->assertStringContainsString('34 cards · 3×3 per sheet', $html);
    }

    public function test_the_options_page_reports_quantity_overrides_back(): void
    {
        $card = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();

        $this->get("/print/kraken?qty[entity:{$card->id}]=6")
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Options')
                ->where("selection.qty.entity:{$card->id}", 6)
            );
    }

    public function test_a_key_that_is_not_a_key_is_ignored_rather_than_printing_nothing(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=entity&only=oops&except=%3Bdrop')->getContent();

        $this->assertStringContainsString('34 cards · 3×3 per sheet', $html);
    }

    public function test_leaving_every_card_out_says_so_rather_than_looking_empty(): void
    {
        $keys = EntityCard::pluck('id')->map(fn ($id) => "entity:{$id}")->implode(',');

        $html = $this->get("/print/kraken/sheet?deck=entity&except={$keys}")->getContent();

        $this->assertStringContainsString('Every card in this deck was left out', $html);
    }

    public function test_backs_add_a_mirrored_sheet_after_each_page(): void
    {
        $without = substr_count($this->get('/print/kraken/sheet?deck=entity')->getContent(), 'class="page"');
        $with = substr_count($this->get('/print/kraken/sheet?deck=entity&backs=1')->getContent(), 'class="page"');

        $this->assertSame(4, $without);
        $this->assertSame(8, $with);
    }

    public function test_auto_icons_are_on_by_default_and_can_be_turned_off(): void
    {
        $this->assertStringContainsString('markup-icon-damage', $this->get('/print/kraken/sheet?deck=entity')->getContent());
        $this->assertStringNotContainsString('markup-icon-damage', $this->get('/print/kraken/sheet?deck=entity&auto_icons=0')->getContent());
    }

    public function test_placeholder_flags_are_off_unless_asked_for(): void
    {
        $this->assertStringNotContainsString('PLACEHOLDER', $this->get('/print/kraken/sheet?deck=entity')->getContent());
        $this->assertStringContainsString('PLACEHOLDER', $this->get('/print/kraken/sheet?deck=entity&show_placeholders=1')->getContent());
    }

    public function test_it_warns_on_the_sheet_when_the_cards_do_not_fit(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=entity&custom_width=300&custom_height=400')->getContent();

        $this->assertStringContainsString('do not fit inside this sheet', $html);
    }

    public function test_an_empty_deck_prints_a_note_rather_than_a_blank_page(): void
    {
        EntityCard::query()->delete();

        $this->assertStringContainsString(
            'Nothing to print',
            $this->get('/print/kraken/sheet?deck=entity')->getContent()
        );
    }

    public function test_a_sticker_sheet_is_laid_out_exactly_as_it_is_described(): void
    {
        // Avery-style numbers: a sheet whose labels start well down the page,
        // sit 2.5 mm apart and come three across and eight down.
        $html = $this->get(
            '/print/kraken/sheet?deck=entity&custom_width=63.5&custom_height=33.9'
            .'&margin_top=12&margin_bottom=12&margin_left=7&margin_right=7'
            .'&gutter_x=2.5&gutter_y=0&columns=3&rows=8'
        )->getContent();

        $this->assertStringContainsString('padding: 12mm 7mm 12mm 7mm;', $html);
        $this->assertStringContainsString('row-gap: 0mm;', $html);
        $this->assertStringContainsString('column-gap: 2.5mm;', $html);
        $this->assertStringContainsString('repeat(3, 63.5mm)', $html);
        $this->assertStringContainsString('3×8 per sheet', $html);
        $this->assertStringNotContainsString('do not fit inside this sheet', $html);
    }

    public function test_a_forced_grid_that_runs_off_the_paper_is_reported_not_shrunk(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=entity&columns=4&rows=4')->getContent();

        $this->assertStringContainsString('repeat(4, 63.5mm)', $html);
        $this->assertStringContainsString('do not fit inside this sheet', $html);
    }

    public function test_the_first_labels_can_be_left_blank(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=entity&skip=2')->getContent();

        // Two empty cells, and the run still ends up one sheet longer than the
        // 34 cards alone would need... which here is the same four sheets.
        $this->assertSame(2, preg_match_all('/<div class="cell">\s*<\/div>/', $html));
        $this->assertStringContainsString('first 2 cells left blank', $html);
        $this->assertSame(3, substr_count($html, 'Tentacle Lash'));

        // A skip that cannot fit inside a sheet is clamped, not obeyed blindly.
        $this->assertStringNotContainsString(
            'left blank',
            $this->get('/print/kraken/sheet?deck=entity&columns=1&rows=1&skip=5')->getContent()
        );
    }

    public function test_a_custom_sheet_size_reaches_the_page_rule(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=entity&sheet_size=custom&custom_sheet_width=200&custom_sheet_height=250')->getContent();

        $this->assertStringContainsString('size: 200mm 250mm;', $html);
        $this->assertStringContainsString('width: 200mm;', $html);
    }

    public function test_the_printer_nudge_moves_the_grid_and_the_crop_marks_with_it(): void
    {
        $plain = $this->get('/print/kraken/sheet?deck=entity')->getContent();
        $nudged = $this->get('/print/kraken/sheet?deck=entity&offset_x=1.5&offset_y=-2')->getContent();

        // .arrow-edge has a transform of its own, so look at the grid's rule.
        $this->assertStringNotContainsString('mm, 0mm)', $plain);
        $this->assertStringNotContainsString('transform: translate(0mm', $plain);
        $this->assertStringContainsString('transform: translate(1.5mm, -2mm);', $nudged);

        // The first trim line is the left margin plus the nudge.
        $this->assertStringContainsString('left: 9.5mm; top: 0.5mm;', $nudged);
    }

    public function test_the_options_page_reports_the_pitch_and_the_first_sheet(): void
    {
        $this->get('/print/kraken?gutter_x=3.25&skip=2')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Options')
                ->where('layout.pitch_x', 66.75)
                ->where('layout.pitch_y', 88.9)
                // The gutter costs a column, and the two skipped cells come out
                // of the first sheet rather than out of the run.
                ->where('layout.columns', 2)
                ->where('layout.per_page', 6)
                ->where('layout.skipped', 2)
                ->where('layout.first_page', 4)
            );
    }

    public function test_a_print_setup_can_be_saved_under_a_name(): void
    {
        $this->post('/print-presets', [
            'name' => 'Avery 63.5×33.9',
            'sheet_size' => 'custom',
            'custom_sheet_width' => '63.5',
            'custom_sheet_height' => '33.9',
            'margin_top' => '12',
            'columns' => '3',
            'rows' => '8',
        ])->assertRedirect();

        $preset = PrintPreset::firstWhere('name', 'Avery 63.5×33.9');

        $this->assertNotNull($preset);
        $this->assertSame('custom', $preset->options['sheet_size']);
        $this->assertSame(63.5, $preset->options['custom_sheet_width']);
        $this->assertSame(3, $preset->options['columns']);
        // Clamped the same way a query string is — nothing saved bypasses the bounds.
        $this->assertEquals(12.0, $preset->options['margin_top']);
    }

    public function test_saving_under_a_name_already_in_use_overwrites_it(): void
    {
        $this->post('/print-presets', ['name' => 'Avery', 'columns' => '3']);
        $this->post('/print-presets', ['name' => 'Avery', 'columns' => '4']);

        $this->assertSame(1, PrintPreset::where('name', 'Avery')->count());
        $this->assertSame(4, PrintPreset::firstWhere('name', 'Avery')->options['columns']);
    }

    public function test_every_print_options_page_offers_the_same_saved_setups(): void
    {
        PrintPreset::create(['name' => 'Avery', 'options' => ['columns' => 3]]);

        $this->get('/print/kraken')
            ->assertInertia(fn ($page) => $page
                ->has('presets', 1)
                ->where('presets.0.name', 'Avery')
            );
    }

    public function test_a_saved_setup_can_be_deleted(): void
    {
        $preset = PrintPreset::create(['name' => 'Avery', 'options' => ['columns' => 3]]);

        $this->delete("/print-presets/{$preset->id}")->assertRedirect();

        $this->assertNull(PrintPreset::find($preset->id));
    }
}
