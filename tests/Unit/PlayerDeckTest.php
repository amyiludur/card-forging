<?php

namespace Tests\Unit;

use App\Models\Character;
use App\Models\Domain;
use App\Models\PlayerCard;
use App\Support\PlayerDeck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PlayerDeckTest extends TestCase
{
    use RefreshDatabase;

    private function character(array $cards, array $config = []): PlayerDeck
    {
        $slug = 'test-'.Character::count();
        $character = Character::create(['slug' => $slug, 'name' => 'Test', 'health' => 10, 'hand_size' => 5]);

        foreach ($cards as $i => $card) {
            $character->cards()->create($card + [
                'slug' => $slug.'-'.$i,
                'name' => 'Card '.$i,
                'qty' => 1,
                'role' => PlayerCard::ROLE_SIGNATURE,
                'type' => 'action',
                'gold_cost' => 0,
                'omen_icons' => 0,
                'start_zone' => 'deck',
                'sort' => $i,
            ]);
        }

        return new PlayerDeck($character->fresh(), $config + ['deckSize' => ['signature' => 3, 'domain' => 3]]);
    }

    public function test_it_counts_by_copy_not_by_row(): void
    {
        $deck = $this->character([['qty' => 3]]);

        $this->assertSame(3, $deck->stats()['signature_total']);
        $this->assertSame([], $deck->warnings());
    }

    public function test_kit_and_upgrades_stay_outside_the_count(): void
    {
        $deck = $this->character([
            ['qty' => 3],
            ['role' => PlayerCard::ROLE_KIT, 'start_zone' => 'play'],
            ['role' => PlayerCard::ROLE_UPGRADE, 'start_zone' => 'upgrade'],
        ]);

        $stats = $deck->stats();

        $this->assertSame(3, $stats['signature_total']);
        $this->assertSame(1, $stats['kit_total']);
        $this->assertSame(1, $stats['upgrade_total']);
        $this->assertSame([], $deck->warnings());
    }

    public function test_it_reports_a_deck_that_is_the_wrong_size(): void
    {
        $warnings = $this->character([['qty' => 5]])->warnings();

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('5 signature cards', $warnings[0]);
        $this->assertStringContainsString('meant to hold 3', $warnings[0]);
    }

    public function test_it_reports_an_upgrade_pointing_at_a_card_that_is_not_there(): void
    {
        $warnings = $this->character([
            ['qty' => 3, 'upgrades_to' => 'nowhere'],
        ])->warnings();

        $this->assertStringContainsString('which is not one of this character\'s cards', implode("\n", $warnings));
    }

    public function test_it_reports_omen_icons_outside_the_configured_range(): void
    {
        $warnings = $this->character(
            [['qty' => 3, 'omen_icons' => 5]],
            ['omenPerCardPlayedRange' => [0, 2]],
        )->warnings();

        $this->assertStringContainsString('carries 5 omen, outside the 0 to 2', implode("\n", $warnings));
    }

    public function test_neutral_cards_count_towards_domain_slots_only_when_the_rules_say_so(): void
    {
        $deck = $this->character([['qty' => 3]], ['neutralFillsDomainSlots' => true]);

        // A neutral card belongs to no character: it lives in the colourless pool.
        Domain::create(['slug' => 'basic', 'name' => 'Basic', 'is_neutral' => true])
            ->cards()->create([
                'slug' => 'coin', 'name' => 'Coin', 'qty' => 2, 'origin' => 'neutral',
                'role' => PlayerCard::ROLE_DOMAIN, 'type' => 'item', 'start_zone' => 'deck',
            ]);

        $this->assertSame(2, $deck->stats()['domain_cards_available']);

        $strict = $this->character([['qty' => 3]], ['neutralFillsDomainSlots' => false]);

        $this->assertSame(0, $strict->stats()['domain_cards_available']);
    }

    public function test_the_curves_are_ordered_and_counted_by_copy(): void
    {
        $stats = $this->character([
            ['qty' => 2, 'omen_icons' => 2, 'gold_cost' => 1],
            ['omen_icons' => 0, 'gold_cost' => 0],
        ])->stats();

        $this->assertSame(
            [['value' => 0, 'count' => 1], ['value' => 2, 'count' => 2]],
            $stats['omen_curve'],
        );
        $this->assertSame(
            [['value' => 0, 'count' => 1], ['value' => 1, 'count' => 2]],
            $stats['gold_curve'],
        );
    }
}
