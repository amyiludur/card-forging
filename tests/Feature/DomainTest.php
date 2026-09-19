<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Domain;
use App\Models\PlayerCard;
use App\Models\RulesConfig;
use App\Support\DomainPool;
use App\Support\PlayerDeck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Domains are the shared half of a deck. The rules the tool has to hold are
 * that a card belongs to one owner, that an upgrade pair never crosses from one
 * owner to another, and that a domain belongs to nobody: pairing one with a
 * character is deck building, and lives in DeckBuildTest.
 */
class DomainTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    private function tide(): Domain
    {
        return Domain::create([
            'slug' => 'tide', 'name' => 'Tide', 'set_icon' => 'TD',
            'identity' => 'Draws deep and pays later.',
        ]);
    }

    private function card(Domain $domain, array $attributes = []): PlayerCard
    {
        return $domain->cards()->create($attributes + [
            'slug' => 'undertow', 'name' => 'Undertow', 'qty' => 1,
            'role' => PlayerCard::ROLE_DOMAIN, 'origin' => 'domain',
            'type' => 'action', 'gold_cost' => 0, 'omen_icons' => 1, 'start_zone' => 'deck',
        ]);
    }

    public function test_it_creates_a_domain_from_the_editor(): void
    {
        $this->post('/domains', ['name' => 'Tide', 'set_icon' => 'TD'])
            ->assertRedirect('/domains/tide');

        $this->assertSame('TD', Domain::where('slug', 'tide')->firstOrFail()->set_icon);
        // A new domain is a draft until the designer says otherwise.
        $this->assertTrue(Domain::where('slug', 'tide')->firstOrFail()->is_placeholder);
    }

    public function test_a_card_added_to_a_domain_belongs_to_the_domain_and_to_no_character(): void
    {
        $domain = $this->tide();

        $this->post("/domains/{$domain->slug}/cards", [
            'name' => 'Undertow', 'qty' => 2, 'role' => PlayerCard::ROLE_DOMAIN, 'origin' => 'domain',
            'type' => 'action', 'gold_cost' => 1, 'omen_icons' => 1, 'start_zone' => 'deck',
        ])->assertRedirect("/domains/{$domain->slug}");

        $card = PlayerCard::where('slug', 'undertow')->firstOrFail();

        $this->assertSame($domain->id, $card->domain_id);
        $this->assertNull($card->character_id);
        $this->assertTrue($card->countsTowardsDeck());
        // The owner is what the editor and the deck maths both read.
        $this->assertTrue($domain->is($card->owner()));
    }

    public function test_the_colourless_pool_makes_its_cards_neutral_by_default(): void
    {
        $basic = Domain::create(['slug' => 'basic', 'name' => 'Basic', 'is_neutral' => true]);

        $this->assertSame('neutral', $basic->defaultOrigin());
        $this->assertSame('domain', $this->tide()->defaultOrigin());
    }

    public function test_two_owners_can_hold_a_card_of_the_same_name(): void
    {
        $domain = $this->tide();
        $gunslinger = Character::where('slug', 'gunslinger')->firstOrFail();

        $this->post("/domains/{$domain->slug}/cards", [
            'name' => 'Lucky Coin', 'qty' => 1, 'role' => PlayerCard::ROLE_DOMAIN, 'origin' => 'domain',
            'type' => 'item', 'gold_cost' => 0, 'omen_icons' => 0, 'start_zone' => 'deck',
        ])->assertSessionHasNoErrors();

        // The Gunslinger already has a lucky-coin; a slug is unique per owner.
        $this->assertSame(1, $gunslinger->cards()->where('slug', 'lucky-coin')->count());
        $this->assertSame(1, $domain->cards()->where('slug', 'lucky-coin')->count());
    }

    public function test_an_upgrade_pair_inside_a_domain_writes_both_ends(): void
    {
        $domain = $this->tide();
        $this->card($domain);

        $this->post("/domains/{$domain->slug}/cards", [
            'name' => 'Riptide', 'qty' => 1, 'role' => PlayerCard::ROLE_UPGRADE, 'origin' => 'domain',
            'type' => 'action', 'gold_cost' => 1, 'omen_icons' => 1, 'start_zone' => 'upgrade',
            'upgrade_of' => 'undertow',
        ])->assertSessionHasNoErrors();

        $this->assertSame('riptide', PlayerCard::where('slug', 'undertow')->firstOrFail()->upgrades_to);
        $this->assertSame('undertow', PlayerCard::where('slug', 'riptide')->firstOrFail()->upgrade_of);
    }

    public function test_an_upgrade_cannot_pair_with_another_owner_s_card(): void
    {
        $domain = $this->tide();

        // revolver belongs to the Gunslinger, not to this pool.
        $this->post("/domains/{$domain->slug}/cards", [
            'name' => 'Riptide', 'qty' => 1, 'role' => PlayerCard::ROLE_UPGRADE, 'origin' => 'domain',
            'type' => 'action', 'gold_cost' => 1, 'omen_icons' => 1, 'start_zone' => 'upgrade',
            'upgrade_of' => 'revolver',
        ])->assertSessionHasErrors('upgrade_of');
    }

    public function test_syncing_a_domain_pair_leaves_a_character_s_cards_alone(): void
    {
        $domain = $this->tide();
        $card = $this->card($domain);

        // The Gunslinger has a pair already; writing one inside the pool must
        // not reach across and release it.
        $card->update(['upgrades_to' => null]);
        $card->syncUpgradeLinks();

        $revolver = PlayerCard::where('slug', 'revolver')->firstOrFail();

        $this->assertSame('peacemaker', $revolver->upgrades_to);
        $this->assertSame('revolver', PlayerCard::where('slug', 'peacemaker')->firstOrFail()->upgrade_of);
    }

    public function test_a_pool_too_small_is_reported_on_its_own_page_too(): void
    {
        $domain = $this->tide();
        $this->card($domain, ['qty' => 12]);

        $this->assertStringContainsString(
            'This pool holds 12 cards, but a character takes 20 out of it. 8 short.',
            implode("\n", DomainPool::for($domain)->warnings()),
        );
        $this->assertSame(0, DomainPool::for($domain)->stats()['choice']);
    }

    public function test_the_neutral_pool_only_counts_while_the_rules_say_it_does(): void
    {
        $basic = Domain::create(['slug' => 'basic', 'name' => 'Basic', 'is_neutral' => true]);
        $this->card($basic, ['origin' => 'neutral', 'qty' => 5]);

        $this->assertTrue(DomainPool::for($basic)->stats()['fills_slots']);

        RulesConfig::where('key', 'neutralFillsDomainSlots')->firstOrFail()->update(['value' => ['v' => false]]);

        $pool = DomainPool::for($basic->fresh());

        $this->assertFalse($pool->stats()['fills_slots']);
        $this->assertStringContainsString(
            'Neutral cards do not fill domain slots',
            implode("\n", $pool->warnings()),
        );
    }

    public function test_a_domain_card_marked_signature_is_reported(): void
    {
        $domain = $this->tide();
        $this->card($domain, ['origin' => 'signature']);

        $this->assertStringContainsString(
            'is marked signature but sits in a domain',
            implode("\n", DomainPool::for($domain)->warnings()),
        );
    }

    public function test_a_character_card_marked_as_a_domain_card_is_reported(): void
    {
        $gunslinger = Character::where('slug', 'gunslinger')->firstOrFail();
        $gunslinger->cards()->where('slug', 'lucky-coin')->firstOrFail()->update(['origin' => 'domain']);

        $this->assertStringContainsString(
            'A card that fills a domain slot lives in a domain',
            implode("\n", PlayerDeck::for($gunslinger->fresh())->warnings()),
        );
    }

    public function test_deleting_a_domain_takes_its_cards_and_leaves_every_character(): void
    {
        $domain = $this->tide();
        $this->card($domain);

        $this->delete("/domains/{$domain->slug}")->assertRedirect('/domains');

        $this->assertNull(PlayerCard::where('slug', 'undertow')->first());
        // No character owned it, so none of them notice.
        $this->assertSame(2, Character::count());
        $this->assertSame(20, Character::where('slug', 'gunslinger')->firstOrFail()->signatureCount());
    }

    public function test_the_domain_page_offers_every_character_to_build_with(): void
    {
        $domain = $this->tide();
        $this->card($domain, ['qty' => 3]);

        $this->get("/domains/{$domain->slug}")
            ->assertInertia(fn ($page) => $page
                ->component('Domains/Show')
                ->where('stats.pool_total', 3)
                // Any character can take it, so all of them are offered.
                ->has('characters', 2)
            );
    }
}
