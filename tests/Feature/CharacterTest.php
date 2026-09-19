<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\PlayerCard;
use App\Models\RulesConfig;
use App\Support\PlayerDeck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CharacterTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    private function gunslinger(): Character
    {
        return Character::where('slug', 'gunslinger')->firstOrFail();
    }

    public function test_it_imports_both_drafted_characters(): void
    {
        $this->assertSame(2, Character::count());

        $gunslinger = $this->gunslinger();

        $this->assertSame(10, $gunslinger->health);
        $this->assertSame(5, $gunslinger->hand_size);
        $this->assertSame(2, $gunslinger->gold_per_round);
        $this->assertSame('Deadeye', $gunslinger->ability_name);
        // The designer has not written these yet; they must stay empty.
        $this->assertNull($gunslinger->title);
        $this->assertNull($gunslinger->story);
    }

    public function test_the_three_lists_keep_their_roles(): void
    {
        $gunslinger = $this->gunslinger();

        $this->assertSame(1, $gunslinger->kit()->count());
        $this->assertSame(11, $gunslinger->signatureCards()->count());
        $this->assertSame(5, $gunslinger->upgrades()->count());

        // Twenty by copy, not by row: that is what a deck is built from.
        $this->assertSame(20, $gunslinger->signatureCount());

        $revolver = $gunslinger->cards()->where('slug', 'revolver')->firstOrFail();
        $this->assertSame('play', $revolver->start_zone);
        $this->assertSame('peacemaker', $revolver->upgrades_to);
    }

    public function test_hand_size_moved_off_the_rules_config_onto_the_character(): void
    {
        // v3 removed handSize from rules-config.json, so importing must drop it
        // rather than leave a key the next export would write back. Gold
        // generation followed it onto the character card.
        $this->assertNull(RulesConfig::where('key', 'handSize')->first());
        $this->assertNull(RulesConfig::where('key', 'baseGoldPerRound')->first());

        $this->assertSame(
            ['signature' => 20, 'domain' => 20],
            RulesConfig::where('key', 'deckSize')->firstOrFail()->raw_value,
        );
    }

    public function test_the_shop_destination_is_flagged_as_unsure(): void
    {
        // The designer likes deck-bottom but is not certain, so the UI has to
        // keep saying so rather than presenting it as settled.
        $config = RulesConfig::where('key', 'shopPurchaseDestination')->firstOrFail();

        $this->assertSame('deck-bottom', $config->raw_value);
        $this->assertTrue($config->is_placeholder);
    }

    public function test_gold_generation_is_per_character_and_editable(): void
    {
        $this->put('/characters/gunslinger', [
            'name' => 'Gunslinger',
            'slug' => 'gunslinger',
            'health' => 10,
            'hand_size' => 5,
            'gold_per_round' => 1,
            'ability_name' => 'Deadeye',
            'ability_text' => 'Once per round, draw the bottom card of your deck.',
            'is_placeholder' => true,
        ])->assertRedirect('/characters/gunslinger');

        $this->assertSame(1, $this->gunslinger()->gold_per_round);
        // Changing one character leaves the other alone: it is not a global any more.
        $this->assertSame(2, Character::where('slug', 'soothsayer')->firstOrFail()->gold_per_round);
    }

    public function test_the_character_page_reports_gold_generation(): void
    {
        $this->get('/characters/gunslinger')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('character.gold_per_round', 2));
    }

    public function test_it_lists_characters(): void
    {
        $this->get('/characters')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Characters/Index')->has('characters', 2));
    }

    public function test_it_shows_a_character_with_its_deck(): void
    {
        $this->get('/characters/gunslinger')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Characters/Show')
                ->where('character.name', 'Gunslinger')
                ->has('cards', 17)
                ->where('stats.signature_total', 20)
                ->where('stats.rule.signature', 20)
                ->where('stats.start_zones.deck', 12)
                ->where('stats.start_zones.shop', 8)
                ->has('upgradePairs', 5)
                // The drafted deck is consistent, so nothing to report.
                ->has('warnings', 0)
            );
    }

    public function test_the_deck_maths_matches_the_designers_own_counts(): void
    {
        // These are the numbers written in design/players/gunslinger.md. If the
        // tool disagrees with the designer's table, one of them is wrong.
        $stats = PlayerDeck::for($this->gunslinger())->stats();

        $this->assertSame(['action' => 14, 'response' => 4, 'item' => 2], $stats['types']);
        $this->assertSame(
            [['value' => 0, 'count' => 7], ['value' => 1, 'count' => 12], ['value' => 2, 'count' => 1]],
            $stats['omen_curve'],
        );

        $soothsayer = PlayerDeck::for(Character::where('slug', 'soothsayer')->firstOrFail())->stats();

        $this->assertSame(['action' => 13, 'response' => 5, 'item' => 2], $soothsayer['types']);
        $this->assertSame(15, $soothsayer['start_zones']['deck']);
        $this->assertSame(5, $soothsayer['start_zones']['shop']);
    }

    public function test_a_deck_that_does_not_match_the_rule_is_reported_not_corrected(): void
    {
        $gunslinger = $this->gunslinger();
        $gunslinger->cards()->where('slug', 'standard-round')->firstOrFail()->delete();

        $warnings = PlayerDeck::for($gunslinger->fresh())->warnings();

        $this->assertCount(1, $warnings);
        $this->assertStringContainsString('17 signature cards', $warnings[0]);

        // Nothing was changed to make the numbers agree.
        $this->assertSame(17, $gunslinger->fresh()->signatureCount());
    }

    public function test_a_half_linked_upgrade_is_reported(): void
    {
        $this->gunslinger()->cards()->where('slug', 'peacemaker')->firstOrFail()
            ->update(['upgrade_of' => null]);

        $warnings = PlayerDeck::for($this->gunslinger())->warnings();

        $this->assertStringContainsString('does not point back at it', implode("\n", $warnings));
    }

    public function test_a_card_can_be_added_to_a_character(): void
    {
        $gunslinger = $this->gunslinger();

        $this->post("/characters/{$gunslinger->slug}/cards", [
            'name' => 'Ricochet',
            'qty' => 2,
            'role' => 'signature',
            'origin' => 'signature',
            'type' => 'action',
            'gold_cost' => 1,
            'omen_icons' => 1,
            'shop_cost' => null,
            'start_zone' => 'deck',
            'text' => 'Deal 1 {damage} to two board cards.',
            'traits' => ['Bullet'],
            'keywords' => ['Fired'],
            'is_placeholder' => true,
        ])->assertRedirect("/characters/{$gunslinger->slug}");

        $card = PlayerCard::where('slug', 'ricochet')->firstOrFail();

        $this->assertSame($gunslinger->id, $card->character_id);
        $this->assertSame(2, $card->qty);
        $this->assertSame(['Fired'], $card->keywords);

        // And the deck now reports 22, rather than being quietly trimmed to 20.
        $this->assertSame(22, $gunslinger->fresh()->signatureCount());
    }

    public function test_a_card_can_be_edited(): void
    {
        $card = $this->gunslinger()->cards()->where('slug', 'silver-round')->firstOrFail();

        $this->put("/player-cards/{$card->id}", [
            'name' => 'Silver Round',
            'slug' => 'silver-round',
            'qty' => 1,
            'role' => 'signature',
            'origin' => 'signature',
            'type' => 'action',
            'gold_cost' => 1,
            'omen_icons' => 2,
            'shop_cost' => 3,
            'start_zone' => 'shop',
            'text' => 'Deal 6 damage to a board card.',
            'traits' => ['Bullet'],
            'keywords' => ['Fired'],
            'is_placeholder' => true,
        ])->assertRedirect('/characters/gunslinger');

        $card->refresh();

        $this->assertSame(1, $card->gold_cost);
        $this->assertSame(3, $card->shop_cost);
        $this->assertSame('Deal 6 damage to a board card.', $card->text);
    }

    public function test_deleting_a_card_unlinks_the_upgrade_that_pointed_at_it(): void
    {
        $gunslinger = $this->gunslinger();
        $hollowPoint = $gunslinger->cards()->where('slug', 'hollow-point')->firstOrFail();

        $this->delete("/player-cards/{$hollowPoint->id}")->assertRedirect('/characters/gunslinger');

        $this->assertNull(
            $gunslinger->cards()->where('slug', 'explosive-round')->firstOrFail()->upgrade_of,
        );
    }

    public function test_a_duplicate_starts_unlinked_and_flagged(): void
    {
        $revolver = $this->gunslinger()->cards()->where('slug', 'revolver')->firstOrFail();

        $this->post("/player-cards/{$revolver->id}/duplicate")->assertRedirect('/characters/gunslinger');

        $copy = PlayerCard::where('name', 'Revolver (copy)')->firstOrFail();

        $this->assertNull($copy->upgrades_to);
        $this->assertTrue($copy->is_placeholder);
        $this->assertSame('revolver-copy', $copy->slug);
    }

    public function test_deleting_a_character_takes_only_its_own_cards(): void
    {
        $before = PlayerCard::count();
        $gunslinger = $this->gunslinger();
        $its = $gunslinger->cards()->count();

        $this->delete("/characters/{$gunslinger->slug}")->assertRedirect('/characters');

        $this->assertSame($before - $its, PlayerCard::count());
        $this->assertSame(1, Character::count());
    }
}
