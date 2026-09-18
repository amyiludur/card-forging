<?php

namespace Tests\Feature;

use App\Models\EntityCard;
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

        $board = $this->get('/print/kraken/sheet?deck=board')->getContent();
        $this->assertStringContainsString('The Ocean', $board);
        $this->assertStringContainsString('12 per player', $board);
        $this->assertStringNotContainsString('Troubled Waters', $board);
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
}
