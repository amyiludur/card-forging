<?php

namespace Tests\Feature;

use App\Models\Domain;
use App\Models\PlayerCard;
use App\Support\Icons;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DomainPrintTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importDesignWithoutDomains();
        $this->tide();
    }

    private function tide(): Domain
    {
        $domain = Domain::create([
            'slug' => 'tide', 'name' => 'Tide', 'set_icon' => 'TD', 'sort' => 0,
        ]);

        $domain->cards()->create([
            'slug' => 'undertow', 'name' => 'Undertow', 'qty' => 3,
            'role' => PlayerCard::ROLE_DOMAIN, 'origin' => 'domain', 'type' => 'action',
            'gold_cost' => 1, 'omen_icons' => 2, 'shop_cost' => 2, 'start_zone' => 'shop',
            'text' => 'Draw 2, then discard 1.',
        ]);

        $domain->cards()->create([
            'slug' => 'riptide', 'name' => 'Riptide', 'qty' => 1,
            'role' => PlayerCard::ROLE_UPGRADE, 'origin' => 'domain', 'type' => 'action',
            'gold_cost' => 1, 'omen_icons' => 1, 'start_zone' => 'upgrade',
            'upgrade_of' => 'undertow',
        ])->syncUpgradeLinks();

        return $domain;
    }

    public function test_the_print_page_offers_a_domains_own_lists(): void
    {
        $this->get('/print/domain/tide')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Options')
                ->where('kind', 'domain')
                ->where('options.deck', 'player')
                // 3 copies of Undertow plus the upgrade, counted by copy and
                // reported under the deck each one prints with.
                ->where('counts.player', 3)
                ->where('counts.upgrade', 1)
                ->where('counts.all', 4)
                ->has('decks', 3)
            );
    }

    public function test_it_prints_every_copy_of_every_card_in_the_pool(): void
    {
        $html = $this->get('/print/domain/tide/sheet?deck=all')->assertOk()->getContent();

        $this->assertSame(4, substr_count($html, 'class="card player-card"'));
        $this->assertSame(3, substr_count($html, '>Undertow</div>'));
        $this->assertSame(1, substr_count($html, '>Riptide</div>'));
    }

    public function test_the_pool_and_the_upgrades_print_separately(): void
    {
        $pool = $this->get('/print/domain/tide/sheet?deck=player')->assertOk()->getContent();
        $upgrades = $this->get('/print/domain/tide/sheet?deck=upgrade')->assertOk()->getContent();

        $this->assertSame(3, substr_count($pool, 'class="card player-card"'));
        $this->assertStringNotContainsString('>Riptide</div>', $pool);

        $this->assertSame(1, substr_count($upgrades, 'class="card player-card"'));
        $this->assertStringNotContainsString('>Undertow</div>', $upgrades);
    }

    public function test_a_domain_card_prints_the_pool_it_came_out_of(): void
    {
        $html = $this->get('/print/domain/tide/sheet?deck=player')->assertOk()->getContent();

        // The badge says which pool, the way a module card carries its set icon.
        $this->assertStringContainsString('<span class="set-icon">TD</span>', $html);
        $this->assertStringContainsString('starts in shop', $html);
        $this->assertStringContainsString('shop 2'.Icons::svg('gold'), $html);
    }

    public function test_a_domain_without_a_set_icon_falls_back_to_its_name(): void
    {
        Domain::where('slug', 'tide')->firstOrFail()->update(['set_icon' => null]);

        $html = $this->get('/print/domain/tide/sheet?deck=player')->assertOk()->getContent();

        $this->assertStringContainsString('<span class="set-icon">Tide</span>', $html);
    }

    public function test_a_characters_own_card_carries_no_pool_badge(): void
    {
        $html = $this->get('/print/character/gunslinger/sheet?deck=player')->assertOk()->getContent();

        $this->assertStringNotContainsString('class="set-icon"', $html);
    }

    public function test_the_domain_sheet_loads_no_external_stylesheet(): void
    {
        $html = $this->get('/print/domain/tide/sheet?deck=all')->assertOk()->getContent();

        // It is rendered from file:// for the PDF, so every icon is inline SVG.
        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringNotContainsString('@font-face', $html);
        $this->assertStringContainsString('<svg', $html);
    }
}
