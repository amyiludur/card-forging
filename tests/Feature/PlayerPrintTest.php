<?php

namespace Tests\Feature;

use App\Models\PlayerCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    public function test_the_print_page_offers_a_characters_own_decks(): void
    {
        $this->get('/print/character/gunslinger')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Options')
                ->where('kind', 'character')
                ->where('options.deck', 'player')
                // 20 signature + 1 kit + 5 upgrades, counted by copy.
                ->where('counts.entity', 26)
                ->has('decks', 3)
            );
    }

    public function test_it_prints_every_copy_of_every_card_plus_the_character(): void
    {
        $html = $this->get('/print/character/gunslinger/sheet?deck=all')->assertOk()->getContent();

        $this->assertSame(26, substr_count($html, 'class="card player-card"'));
        $this->assertSame(1, substr_count($html, 'class="card character-card"'));

        // Three copies of Standard Round, one row in the editor.
        $this->assertSame(3, substr_count($html, '>Standard Round</div>'));
    }

    public function test_the_character_card_prints_health_hand_size_and_the_ability(): void
    {
        $html = $this->get('/print/character/soothsayer/sheet?deck=character')->assertOk()->getContent();

        $this->assertStringContainsString('Thread Reader', $html);
        $this->assertStringContainsString('hand 6', $html);
        $this->assertStringContainsString('2<span class="pip-mark">●</span> a round', $html);
        $this->assertStringContainsString('8<span class="pip-mark">♥</span>', $html);
        // Not written yet, and the card has to keep saying so.
        $this->assertStringContainsString('name and story not written', $html);
    }

    public function test_a_player_card_prints_its_costs_and_where_it_starts(): void
    {
        $html = $this->get('/print/character/gunslinger/sheet?deck=player')->assertOk()->getContent();

        // Gold to play on the left, omen icons beside it, shop price in the foot.
        $this->assertStringContainsString('<div class="omen">0<span class="pip-mark">●</span></div>', $html);
        $this->assertStringContainsString('<div class="omen-pips">◆◆</div>', $html);
        $this->assertStringContainsString('shop 2●', $html);
        $this->assertStringContainsString('starts in shop', $html);
    }

    public function test_an_upgrade_prints_the_card_it_replaces(): void
    {
        $html = $this->get('/print/character/gunslinger/sheet?deck=player')->assertOk()->getContent();

        // Someone at the Smithy has to be able to read the swap off the card.
        $this->assertStringContainsString('replaces Hollow Point', $html);
        $this->assertStringContainsString('replaces Revolver', $html);
    }

    public function test_markup_renders_on_a_player_card(): void
    {
        PlayerCard::where('slug', 'lucky-coin')->firstOrFail()
            ->update(['text' => 'Gain {gold}. A deck is {config:deckSize}.']);

        $html = $this->get('/print/character/gunslinger/sheet?deck=player')->assertOk()->getContent();

        $this->assertStringContainsString('markup-icon-gold', $html);
        // A map-valued number prints its parts rather than "20 to 20".
        $this->assertStringContainsString('20 signature, 20 domain', $html);
    }

    public function test_the_scenario_print_page_does_not_offer_player_decks(): void
    {
        $this->get('/print/kraken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Options')
                ->has('decks', 4)
                ->missing('decks.player')
            );
    }
}
