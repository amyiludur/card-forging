<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\Scenario;
use App\Models\StoryBeat;
use App\Models\TownAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ScenarioEditorTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    public function test_it_creates_a_scenario_with_a_slug_from_the_name(): void
    {
        $this->post('/scenarios', [
            'name' => 'The Pale Fever',
            'entity_type' => 'concept',
            'starting_dread' => 2,
            'traits' => ['Outbreak'],
            'printed_arrows' => true,
        ])->assertRedirect('/scenarios/the-pale-fever');

        $this->assertDatabaseHas('scenarios', ['slug' => 'the-pale-fever', 'entity_type' => 'concept']);
    }

    public function test_it_rejects_an_entity_type_the_rules_do_not_have(): void
    {
        $this->post('/scenarios', [
            'name' => 'Nonsense',
            'entity_type' => 'dragon',
            'starting_dread' => 2,
        ])->assertSessionHasErrors('entity_type');
    }

    public function test_it_rejects_a_duplicate_slug(): void
    {
        $this->post('/scenarios', [
            'name' => 'Another Kraken',
            'slug' => 'kraken',
            'entity_type' => 'creature',
            'starting_dread' => 2,
        ])->assertSessionHasErrors('slug');
    }

    public function test_a_scenario_can_turn_printed_arrows_off(): void
    {
        $this->put('/scenarios/kraken', [
            'name' => 'The Kraken',
            'slug' => 'kraken',
            'entity_type' => 'creature',
            'starting_dread' => 2,
            'printed_arrows' => false,
        ])->assertRedirect();

        $this->assertFalse(Scenario::where('slug', 'kraken')->firstOrFail()->printed_arrows);
    }

    public function test_it_adds_a_beat_at_the_end_of_the_story(): void
    {
        $this->post('/scenarios/kraken/beats', ['name' => 'Aftermath', 'dread_change' => 1])->assertRedirect();

        $beat = StoryBeat::where('name', 'Aftermath')->firstOrFail();

        $this->assertSame(5, $beat->order);
        $this->assertSame(1, $beat->dread_change);
    }

    public function test_it_edits_a_beat(): void
    {
        $beat = StoryBeat::where('order', 2)->firstOrFail();

        $this->put("/beats/{$beat->id}", [
            'order' => 2,
            'name' => 'Storm Front',
            'advance' => 'Destroy the Whirlpool twice.',
            'dread_change' => 2,
        ])->assertRedirect();

        $beat->refresh();

        $this->assertSame('Destroy the Whirlpool twice.', $beat->advance);
        $this->assertSame(2, $beat->dread_change);
    }

    public function test_it_adds_and_removes_board_cards(): void
    {
        $this->post('/scenarios/kraken/board-cards', [
            'name' => 'Wreckage',
            'qty' => 2,
            'health' => '4',
            'traits' => ['Deep'],
            'text' => 'Blocks the harbour.',
            'is_placeholder' => true,
        ])->assertRedirect();

        $card = BoardCard::where('name', 'Wreckage')->firstOrFail();

        $this->assertSame(['Deep'], $card->traits);

        $this->delete("/board-cards/{$card->id}")->assertRedirect();
        $this->assertDatabaseMissing('board_cards', ['id' => $card->id]);
    }

    public function test_it_adds_a_town_action(): void
    {
        $this->post('/scenarios/kraken/town-actions', [
            'name' => 'Harbourmaster',
            'effect' => 'Draw a card',
            'gold_cost' => 3,
            'omen' => 2,
        ])->assertRedirect();

        $this->assertDatabaseHas('town_actions', ['name' => 'Harbourmaster', 'omen' => 2]);
    }

    public function test_the_scenario_page_shows_the_deck_and_its_pieces(): void
    {
        $this->get('/scenarios/kraken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Scenarios/Show')
                ->where('scenario.deck_size', 34)
                ->has('beats', 4)
                ->has('boardCards', 4)
                ->has('townActions', 3)
                ->has('cards', 16)
                ->has('cardTypes', 5)
            );
    }

    public function test_the_dashboard_counts_what_is_still_a_placeholder(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('stats.config_values', 18) // five arrow and module keys added in v2
                ->where('stats.placeholder_values', 17)
                ->where('stats.placeholder_cards', 26) // 16 scenario cards plus 10 module cards
            );
    }

    public function test_town_actions_go_away_with_their_scenario(): void
    {
        $this->delete('/scenarios/kraken');

        $this->assertSame(0, TownAction::count());
    }
}
