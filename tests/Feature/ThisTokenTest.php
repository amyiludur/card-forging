<?php

namespace Tests\Feature;

use App\Models\EntityCard;
use App\Models\PlayerCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * {this} writes the name of the card it is on, so text can refer to its own
 * card and keep doing so when the card is renamed.
 */
class ThisTokenTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    public function test_a_printed_entity_card_names_itself(): void
    {
        $card = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();
        $card->faces()->first()->update(['text' => 'Shuffle {this} into the deck.']);

        $html = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        $this->assertStringContainsString('Shuffle Tentacle Lash into the deck.', $html);
        $this->assertStringNotContainsString('{this}', $html);
    }

    public function test_renaming_the_card_renames_it_in_the_text(): void
    {
        $card = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();
        $card->faces()->first()->update(['text' => 'Shuffle {this} into the deck.']);
        $card->update(['name' => 'Kraken Grip']);

        $html = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        $this->assertStringContainsString('Shuffle Kraken Grip into the deck.', $html);
    }

    public function test_a_printed_player_card_names_itself(): void
    {
        $card = PlayerCard::where('name', 'Standard Round')->firstOrFail();
        $card->update(['text' => 'Return {this} to your hand.']);

        $html = $this->get('/print/character/gunslinger/sheet?deck=player')->assertOk()->getContent();

        $this->assertStringContainsString('Return Standard Round to your hand.', $html);
    }

    public function test_the_design_folder_keeps_the_token_as_typed(): void
    {
        $card = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();
        $card->faces()->first()->update(['text' => 'Shuffle {this} into the deck.']);

        $target = storage_path('framework/testing/design-export');
        $this->artisan("design:export --path={$target}")->assertExitCode(0);

        $this->assertStringContainsString(
            'Shuffle {this} into the deck.',
            file_get_contents($target.'/data/kraken.json')
        );
    }
}
