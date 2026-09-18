<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\EntityCard;
use App\Models\Scenario;
use App\Models\StoryBeat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EntityCardEditorTest extends TestCase
{
    use RefreshDatabase;

    private Scenario $scenario;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
        $this->scenario = Scenario::where('slug', 'kraken')->firstOrFail();
    }

    private function typeId(string $slug): int
    {
        return CardType::where('slug', $slug)->firstOrFail()->id;
    }

    public function test_it_creates_a_single_effect_card(): void
    {
        $this->post('/cards?scenario=kraken', [
            'name' => 'Undertow',
            'qty' => 2,
            'layout' => 'single',
            'omen_cost' => 2,
            'omen_is_x' => false,
            'traits' => ['Deep'],
            'arrow' => null,
            'is_placeholder' => true,
            'faces' => [['half' => 'single', 'card_type_id' => $this->typeId('attack'), 'text' => 'Deal 2 {damage}.']],
        ])->assertRedirect();

        $card = EntityCard::where('name', 'Undertow')->with('faces')->firstOrFail();

        $this->assertSame(2, $card->qty);
        $this->assertSame(['Deep'], $card->traits);
        $this->assertCount(1, $card->faces);
        $this->assertSame('Deal 2 {damage}.', $card->faces->first()->text);
    }

    public function test_switching_to_split_gives_the_card_two_halves(): void
    {
        $card = EntityCard::where('name', 'Salt Wind')->firstOrFail();

        $this->put("/cards/{$card->id}", [
            'name' => 'Salt Wind',
            'qty' => 2,
            'layout' => 'split',
            'omen_cost' => 1,
            'omen_is_x' => false,
            'traits' => ['Storm'],
            'arrow' => 'bottom',
            'is_placeholder' => true,
            'faces' => [
                ['half' => 'top', 'card_type_id' => $this->typeId('curse'), 'text' => 'Add 2 omen.'],
                ['half' => 'bottom', 'card_type_id' => $this->typeId('attack'), 'text' => 'Deal 1 damage.'],
            ],
        ])->assertRedirect();

        $card->refresh()->load('faces');

        $this->assertSame('split', $card->layout);
        $this->assertSame('bottom', $card->arrow);
        $this->assertSame(['top', 'bottom'], $card->faces->pluck('half')->all());
    }

    public function test_an_arrow_is_dropped_when_a_card_stops_being_split(): void
    {
        $card = EntityCard::where('name', 'Storm Surge')->firstOrFail();
        $card->update(['arrow' => 'top']);

        $this->put("/cards/{$card->id}", [
            'name' => 'Storm Surge',
            'qty' => 2,
            'layout' => 'single',
            'omen_cost' => 2,
            'omen_is_x' => false,
            'traits' => [],
            'arrow' => 'top',
            'is_placeholder' => true,
            'faces' => [['half' => 'single', 'card_type_id' => $this->typeId('attack'), 'text' => 'Deal 2 damage.']],
        ]);

        $card->refresh()->load('faces');

        $this->assertNull($card->arrow);
        $this->assertCount(1, $card->faces);
        $this->assertSame('single', $card->faces->first()->half);
    }

    public function test_an_x_cost_card_loses_its_printed_number(): void
    {
        $card = EntityCard::where('name', 'Choking Ink')->firstOrFail();

        $this->put("/cards/{$card->id}", [
            'name' => 'Choking Ink',
            'qty' => 2,
            'layout' => 'x-cost',
            'omen_cost' => 2,
            'omen_is_x' => false,
            'traits' => [],
            'arrow' => null,
            'is_placeholder' => true,
            'faces' => [['half' => 'single', 'card_type_id' => $this->typeId('curse'), 'text' => 'Drain the pool.']],
        ]);

        $card->refresh();

        $this->assertTrue($card->omen_is_x);
        $this->assertNull($card->omen_cost);
        $this->assertSame('X', $card->omen_label);
    }

    public function test_it_rejects_an_unknown_layout(): void
    {
        $this->post('/cards?scenario=kraken', [
            'name' => 'Nonsense',
            'qty' => 1,
            'layout' => 'hexagonal',
            'omen_cost' => 1,
            'omen_is_x' => false,
            'faces' => [['half' => 'single', 'card_type_id' => null, 'text' => '']],
        ])->assertSessionHasErrors('layout');

        $this->assertDatabaseMissing('entity_cards', ['name' => 'Nonsense']);
    }

    public function test_it_duplicates_a_card_with_its_faces(): void
    {
        $card = EntityCard::where('name', 'Rising Tide')->firstOrFail();

        $this->post("/cards/{$card->id}/duplicate")->assertRedirect();

        $copy = EntityCard::where('name', 'Rising Tide (copy)')->with('faces')->firstOrFail();

        $this->assertCount(2, $copy->faces);
        $this->assertSame($card->omen_cost, $copy->omen_cost);
    }

    public function test_it_deletes_a_card_and_its_faces(): void
    {
        $card = EntityCard::where('name', 'Salt Wind')->firstOrFail();
        $faceId = $card->faces()->firstOrFail()->id;

        $this->delete("/cards/{$card->id}")->assertRedirect();

        $this->assertDatabaseMissing('entity_cards', ['id' => $card->id]);
        $this->assertDatabaseMissing('entity_card_faces', ['id' => $faceId]);
    }

    public function test_deleting_a_beat_leaves_its_cards_in_the_base_deck(): void
    {
        $beat = StoryBeat::where('scenario_id', $this->scenario->id)->where('order', 1)->firstOrFail();
        $card = EntityCard::where('name', 'Howling Gale')->firstOrFail();

        $this->delete("/beats/{$beat->id}")->assertRedirect();

        $this->assertNull($card->refresh()->added_by_beat_id);
    }

    public function test_the_card_list_filters(): void
    {
        $this->get('/cards?scenario=kraken&layout=split')
            ->assertInertia(fn ($page) => $page
                ->component('Cards/Index')
                ->where('cards', fn ($cards) => collect($cards)->every(fn ($c) => $c['layout'] === 'split'))
            );

        $this->get('/cards?scenario=kraken&trait=Storm')
            ->assertInertia(fn ($page) => $page
                ->where('cards', fn ($cards) => collect($cards)->every(fn ($c) => in_array('Storm', $c['traits'], true)))
            );

        $this->get('/cards?scenario=kraken&q=Tentacle')
            ->assertInertia(fn ($page) => $page->where('cards', fn ($cards) => count($cards) > 0));
    }

    public function test_deleting_a_scenario_takes_its_cards_with_it(): void
    {
        $this->delete('/scenarios/kraken')->assertRedirect('/scenarios');

        $this->assertSame(0, EntityCard::count());
        $this->assertDatabaseCount('story_beats', 0);
        $this->assertDatabaseCount('board_cards', 0);
    }
}
