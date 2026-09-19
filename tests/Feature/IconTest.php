<?php

namespace Tests\Feature;

use App\Support\Icons;
use App\Support\Markup;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IconTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    public function test_every_markup_token_has_an_icon(): void
    {
        foreach (array_keys(Markup::ICONS) as $name) {
            $this->assertTrue(Icons::has($name), "{omen}-style token '{$name}' has no icon");
        }
    }

    public function test_every_card_type_has_an_icon(): void
    {
        foreach (\App\Models\CardType::pluck('slug') as $slug) {
            $this->assertTrue(Icons::has($slug), "card type '{$slug}' has no icon");
        }

        foreach (\App\Models\PlayerCard::TYPES as $type) {
            $this->assertTrue(Icons::has($type), "player card type '{$type}' has no icon");
        }

        foreach (\App\Models\PlayerCard::START_ZONES as $zone) {
            $this->assertTrue(Icons::has("zone-{$zone}"), "start zone '{$zone}' has no icon");
        }
    }

    public function test_a_sheet_carries_its_icons_inline(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=all')->assertOk()->getContent();

        // The PDF is rendered from file:// by headless Chromium, so a webfont
        // or a stylesheet link would arrive as an empty box. Everything the
        // sheet draws has to already be in the file.
        $this->assertStringContainsString('<svg class="icon"', $html);
        $this->assertStringNotContainsString('<link', $html);
        $this->assertStringNotContainsString('@font-face', $html);
        $this->assertStringNotContainsString('fontawesome', strtolower($html));
    }

    public function test_the_browser_gets_the_same_paths_the_server_draws(): void
    {
        $this->get('/characters/gunslinger')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('markup.paths.omen.d', Icons::PATHS['omen']['d'])
                ->where('markup.paths.gold.fa', 'coins')
                ->has('markup.paths', count(Icons::PATHS))
            );
    }

    public function test_an_unknown_icon_draws_nothing_rather_than_breaking(): void
    {
        $this->assertSame('', Icons::svg('not-an-icon'));
    }

    public function test_the_class_name_is_escaped(): void
    {
        $this->assertStringContainsString('class="icon &quot;evil"', Icons::svg('omen', 'icon "evil'));
    }
}
