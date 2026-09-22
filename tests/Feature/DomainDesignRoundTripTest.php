<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Domain;
use App\Models\PlayerCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A domain has to come out of the editor in the shape the design folder writes
 * it in. A character file names no domain at all: which domain a deck uses is
 * chosen when the deck is built, so it is not a fact about the character.
 */
class DomainDesignRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/domains-'.uniqid());
        $this->importDesignWithoutDomains();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function read(string $file): array
    {
        return json_decode(file_get_contents("{$this->path}/{$file}"), true, 512, JSON_THROW_ON_ERROR);
    }

    private function tide(): Domain
    {
        $domain = Domain::create([
            'slug' => 'tide', 'name' => 'Tide', 'status' => 'draft',
            'identity' => 'Draws deep and pays later.', 'set_icon' => 'TD', 'sort' => 0,
        ]);

        $domain->cards()->create([
            'slug' => 'undertow', 'name' => 'Undertow', 'qty' => 2,
            'role' => PlayerCard::ROLE_DOMAIN, 'origin' => 'domain', 'type' => 'action',
            'gold_cost' => 1, 'omen_icons' => 1, 'start_zone' => 'deck',
            'text' => 'Draw 2, then discard 1.', 'traits' => ['Deep'], 'sort' => 0,
        ]);

        // A pair is one fact written from either end, the way the editor does it.
        $domain->cards()->create([
            'slug' => 'riptide', 'name' => 'Riptide', 'qty' => 1,
            'role' => PlayerCard::ROLE_UPGRADE, 'origin' => 'domain', 'type' => 'action',
            'gold_cost' => 1, 'omen_icons' => 1, 'start_zone' => 'upgrade',
            'upgrade_of' => 'undertow', 'sort' => 1,
        ])->syncUpgradeLinks();

        return $domain;
    }

    public function test_a_domain_exports_in_the_two_lists_it_is_written_in(): void
    {
        $this->tide();

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $tide = $this->read('players/domains/tide.json');

        $this->assertSame(
            ['id', 'name', 'title', 'status', 'identity', 'setIcon', 'neutral', 'cards', 'upgrades', 'notes'],
            array_keys($tide),
        );

        $this->assertFalse($tide['neutral']);
        $this->assertSame('undertow', $tide['cards'][0]['id']);
        $this->assertSame(2, $tide['cards'][0]['qty']);
        $this->assertSame(['Deep'], $tide['cards'][0]['traits']);
        $this->assertSame('riptide', $tide['cards'][0]['upgradesTo']);
        $this->assertSame('riptide', $tide['upgrades'][0]['id']);
        $this->assertSame('undertow', $tide['upgrades'][0]['upgradeOf']);
    }

    public function test_a_domain_survives_the_round_trip(): void
    {
        $this->tide();

        $this->artisan('design:export', ['--path' => $this->path]);

        Domain::query()->get()->each->delete();
        $this->assertSame(0, Domain::count());

        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $tide = Domain::where('slug', 'tide')->firstOrFail();

        $this->assertSame('TD', $tide->set_icon);
        $this->assertSame('Draws deep and pays later.', $tide->identity);
        $this->assertSame(2, $tide->poolSize());
        $this->assertSame(1, $tide->upgrades()->count());
        $this->assertSame('Draw 2, then discard 1.', $tide->cards()->where('slug', 'undertow')->firstOrFail()->text);
    }

    public function test_the_colourless_pool_keeps_its_flag_and_its_neutral_cards(): void
    {
        $basic = Domain::create(['slug' => 'basic', 'name' => 'Basic', 'is_neutral' => true]);
        // No origin given: the pool decides what its cards are.
        $basic->cards()->create([
            'slug' => 'coin', 'name' => 'Coin', 'qty' => 3, 'role' => PlayerCard::ROLE_DOMAIN,
            'origin' => 'neutral', 'type' => 'item', 'gold_cost' => 0, 'omen_icons' => 0, 'start_zone' => 'deck',
        ]);

        $this->artisan('design:export', ['--path' => $this->path]);

        $this->assertTrue($this->read('players/domains/basic.json')['neutral']);

        Domain::query()->get()->each->delete();
        $this->artisan('design:import', ['--path' => $this->path]);

        $this->assertTrue(Domain::where('slug', 'basic')->firstOrFail()->is_neutral);
        $this->assertSame('neutral', PlayerCard::where('slug', 'coin')->firstOrFail()->origin);
    }

    public function test_a_domain_file_without_an_origin_takes_the_pool_s(): void
    {
        File::ensureDirectoryExists("{$this->path}/players/domains");
        File::copyDirectory(base_path('design'), $this->path);
        file_put_contents("{$this->path}/players/domains/basic.json", json_encode([
            'id' => 'basic', 'name' => 'Basic', 'neutral' => true,
            'cards' => [['id' => 'coin', 'name' => 'Coin', 'type' => 'item']],
        ]));

        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $this->assertSame('neutral', PlayerCard::where('slug', 'coin')->firstOrFail()->origin);
        // And a card with no startZone lands in the deck, like a signature card.
        $this->assertSame('deck', PlayerCard::where('slug', 'coin')->firstOrFail()->start_zone);
    }

    public function test_a_character_file_is_untouched_by_the_domains_beside_it(): void
    {
        $this->tide();

        $this->artisan('design:export', ['--path' => $this->path]);

        foreach ($this->characterSlugs() as $slug) {
            // A character names no domain: that is a deck's choice, not its own.
            $this->assertArrayNotHasKey('domain', $this->read("players/{$slug}.json"));
            $this->assertArrayNotHasKey('domains', $this->read("players/{$slug}.json"));

            $this->assertSame(
                rtrim(file_get_contents(base_path("design/players/{$slug}.json"))),
                rtrim(file_get_contents("{$this->path}/players/{$slug}.json")),
                "design/players/{$slug}.json did not survive the round trip",
            );
        }
    }

    public function test_a_card_the_domain_file_dropped_does_not_linger(): void
    {
        $this->tide();

        $this->artisan('design:export', ['--path' => $this->path]);

        $file = "{$this->path}/players/domains/tide.json";
        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $data['upgrades'] = [];
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->artisan('design:import', ['--path' => $this->path]);

        $this->assertNull(PlayerCard::where('slug', 'riptide')->first());
        $this->assertSame(2, Domain::where('slug', 'tide')->firstOrFail()->poolSize());
    }

    public function test_a_design_folder_with_no_domains_grows_no_empty_directory(): void
    {
        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $this->assertDirectoryDoesNotExist("{$this->path}/players/domains");
    }
}
