<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\CardType;
use App\Models\EntityCard;
use App\Models\RuleDocument;
use App\Models\RulesConfig;
use App\Models\Scenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DesignImportTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_imports_the_design_folder(): void
    {
        $this->artisan('design:import')->assertSuccessful();

        $kraken = Scenario::where('slug', 'kraken')->firstOrFail();

        $this->assertSame('The Kraken', $kraken->name);
        $this->assertSame('creature', $kraken->entity_type);
        $this->assertSame(2, $kraken->starting_dread);
        $this->assertContains('Tentacle', $kraken->traits);
        $this->assertSame(34, $kraken->deckSize());
        $this->assertCount(4, $kraken->storyBeats);
        $this->assertCount(3, $kraken->townActions);
        $this->assertCount(5, CardType::all());
        $this->assertCount(5, RuleDocument::all());
    }

    public function test_it_reads_split_cards_as_two_typed_halves(): void
    {
        $this->artisan('design:import');

        $card = EntityCard::where('name', 'Storm Surge')->with('faces.cardType')->firstOrFail();

        $this->assertSame('split', $card->layout);
        $this->assertCount(2, $card->faces);
        $this->assertSame(['top', 'bottom'], $card->faces->pluck('half')->all());
        $this->assertSame(['attack', 'hazard'], $card->faces->pluck('cardType.slug')->all());
    }

    public function test_it_reads_an_x_cost_card(): void
    {
        $this->artisan('design:import');

        $card = EntityCard::where('name', 'Grasping Depths')->firstOrFail();

        $this->assertTrue($card->omen_is_x);
        $this->assertNull($card->omen_cost);
        $this->assertSame('X', $card->omen_label);
    }

    public function test_it_links_cards_and_board_pieces_to_the_beat_that_adds_them(): void
    {
        $this->artisan('design:import');

        $this->assertSame(1, EntityCard::where('name', 'Howling Gale')->firstOrFail()->addedByBeat->order);
        $this->assertSame(2, BoardCard::where('name', 'Whirlpool')->firstOrFail()->addedByBeat->order);
        $this->assertNull(EntityCard::where('name', 'Tentacle Lash')->firstOrFail()->added_by_beat_id);
    }

    public function test_it_keeps_health_that_is_not_a_number(): void
    {
        $this->artisan('design:import');

        $this->assertSame('12 per player', BoardCard::where('name', 'The Kraken')->firstOrFail()->health);
        $this->assertNull(BoardCard::where('name', 'The Ocean')->firstOrFail()->health);
    }

    public function test_it_marks_decided_values_as_not_placeholders(): void
    {
        $this->artisan('design:import');

        $this->assertTrue(RulesConfig::where('key', 'startingOmen')->firstOrFail()->is_placeholder);
        $this->assertFalse(RulesConfig::where('key', 'goldCarriesOver')->firstOrFail()->is_placeholder);
        $this->assertSame([0, 2], RulesConfig::where('key', 'omenPerCardPlayedRange')->firstOrFail()->raw_value);
    }

    public function test_it_is_safe_to_run_twice(): void
    {
        $this->artisan('design:import');
        $before = EntityCard::count();

        $this->artisan('design:import')->assertSuccessful();

        $this->assertSame($before, EntityCard::count());
        $this->assertSame(1, Scenario::count());
        $this->assertCount(1, EntityCard::where('name', 'Storm Surge')->firstOrFail()->faces()->where('half', 'top')->get());
    }

    public function test_it_snapshots_rules_before_a_reimport_overwrites_an_edit(): void
    {
        $this->artisan('design:import');

        $document = RuleDocument::where('slug', '01-core-rules')->firstOrFail();
        $document->update(['body' => 'Edited in the app.']);

        $this->artisan('design:import');

        $document->refresh();
        $this->assertStringContainsString('# Core Rules', $document->body);
        $this->assertSame('Edited in the app.', $document->versions()->first()->body);
    }
}
