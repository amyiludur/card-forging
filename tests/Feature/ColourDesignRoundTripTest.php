<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Character;
use App\Models\Domain;
use App\Models\Scenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * Colours and a scenario's own card types, through the design folder and back.
 *
 * The rule everywhere else holds here too: a key is written only when there is
 * something to write, so a design folder nobody has coloured comes back out
 * exactly as it went in.
 */
class ColourDesignRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/colours-'.uniqid());
        $this->artisan('design:import');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function export(): void
    {
        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();
    }

    private function readJson(string $file): array
    {
        return json_decode(file_get_contents("{$this->path}/{$file}"), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_a_folder_nobody_has_coloured_grows_no_colour_keys(): void
    {
        // The design folder has drafted domains in it now, and a coloured one
        // writes its colours back exactly as it should. What is under test is
        // the exporter's rule — a key is written only when there is something
        // to write — so the colours are cleared first and the folder really is
        // one nobody has coloured, whatever happens to be drafted this week.
        // Same reason a test that builds its own domains does not import them.
        CardType::query()->update(['colour' => null, 'icon' => null]);
        Character::query()->update(['colour' => null, 'colour_secondary' => null]);
        Domain::query()->update(['colour' => null, 'colour_secondary' => null]);

        $this->export();

        foreach ($this->readJson('data/card-types.json')['types'] as $type) {
            $this->assertArrayNotHasKey('colour', $type);
            $this->assertArrayNotHasKey('icon', $type);
        }

        $this->assertArrayNotHasKey('colours', $this->readJson('players/gunslinger.json'));
        $this->assertArrayNotHasKey('colours', $this->readJson('players/domains/hunt.json'));
        $this->assertArrayNotHasKey('cardTypes', $this->readJson('data/kraken.json'));
    }

    public function test_a_colour_is_written_and_read_back(): void
    {
        CardType::where('slug', 'attack')->update(['colour' => '#7f1d1d', 'icon' => 'damage']);

        $this->export();

        $attack = collect($this->readJson('data/card-types.json')['types'])->firstWhere('id', 'attack');

        $this->assertSame('#7f1d1d', $attack['colour']);
        $this->assertSame('damage', $attack['icon']);

        CardType::where('slug', 'attack')->update(['colour' => null, 'icon' => null]);
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $this->assertSame('#7f1d1d', CardType::where('slug', 'attack')->firstOrFail()->colour);
    }

    public function test_a_scenarios_own_types_live_in_its_own_file(): void
    {
        $kraken = Scenario::where('slug', 'kraken')->firstOrFail();

        CardType::create([
            'scenario_id' => $kraken->id,
            'slug' => 'tide',
            'name' => 'Tide',
            'description' => 'Moves the water.',
            'colour' => '#0f766e',
        ]);

        $this->export();

        // The scenario's own, in the scenario's own file...
        $this->assertSame(
            [['id' => 'tide', 'name' => 'Tide', 'description' => 'Moves the water.', 'colour' => '#0f766e']],
            $this->readJson('data/kraken.json')['cardTypes'],
        );

        // ...and nowhere near the shared library.
        $this->assertNotContains('tide', collect($this->readJson('data/card-types.json')['types'])->pluck('id'));
    }

    public function test_a_scenarios_own_types_come_back_owned(): void
    {
        $kraken = Scenario::where('slug', 'kraken')->firstOrFail();
        CardType::create(['scenario_id' => $kraken->id, 'slug' => 'tide', 'name' => 'Tide', 'colour' => '#0f766e']);

        $this->export();

        CardType::where('slug', 'tide')->delete();
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $tide = CardType::where('slug', 'tide')->firstOrFail();

        $this->assertSame(Scenario::where('slug', 'kraken')->firstOrFail()->id, $tide->scenario_id);
        $this->assertSame('#0f766e', $tide->colour);
    }

    public function test_a_card_can_be_typed_with_its_scenarios_own_type_through_the_folder(): void
    {
        $kraken = Scenario::where('slug', 'kraken')->firstOrFail();
        $tide = CardType::create(['scenario_id' => $kraken->id, 'slug' => 'tide', 'name' => 'Tide']);

        $card = $kraken->entityCards()->first();
        $card->faces()->first()->update(['card_type_id' => $tide->id]);

        $this->export();
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $this->assertSame(
            'tide',
            $card->fresh()->faces()->first()->cardType->slug,
            'the card lost the type its own scenario owns',
        );
    }

    public function test_a_characters_two_colours_survive_the_round_trip(): void
    {
        Character::where('slug', 'gunslinger')->update(['colour' => '#3f2b56', 'colour_secondary' => '#7c3aed']);

        $this->export();

        $this->assertSame(
            ['from' => '#3f2b56', 'to' => '#7c3aed'],
            $this->readJson('players/gunslinger.json')['colours'],
        );

        Character::where('slug', 'gunslinger')->update(['colour' => null, 'colour_secondary' => null]);
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $gunslinger = Character::where('slug', 'gunslinger')->firstOrFail();

        $this->assertSame('#3f2b56', $gunslinger->colour);
        $this->assertSame('#7c3aed', $gunslinger->colour_secondary);
    }

    public function test_one_colour_alone_writes_one_key(): void
    {
        // The other colour explicitly cleared, for the reason the domain's
        // twin below does it: this passes today only because nobody has
        // coloured the Gunslinger yet, and that is not a thing to depend on.
        Character::where('slug', 'gunslinger')->update(['colour' => '#3f2b56', 'colour_secondary' => null]);

        $this->export();

        $this->assertSame(['from' => '#3f2b56'], $this->readJson('players/gunslinger.json')['colours']);
    }

    public function test_a_domains_two_colours_survive_the_round_trip(): void
    {
        Domain::where('slug', 'hunt')->update(['colour' => '#1e3a5f', 'colour_secondary' => '#0f766e']);

        $this->export();

        $this->assertSame(
            ['from' => '#1e3a5f', 'to' => '#0f766e'],
            $this->readJson('players/domains/hunt.json')['colours'],
        );

        Domain::where('slug', 'hunt')->update(['colour' => null, 'colour_secondary' => null]);
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $hunt = Domain::where('slug', 'hunt')->firstOrFail();

        $this->assertSame('#1e3a5f', $hunt->colour);
        $this->assertSame('#0f766e', $hunt->colour_secondary);
    }

    public function test_a_domain_colour_alone_writes_one_key(): void
    {
        // Explicitly the one colour and not the other: a domain the designer
        // has since given both would otherwise write both keys and this would
        // be testing the design folder rather than the exporter.
        Domain::where('slug', 'hunt')->update(['colour' => '#1e3a5f', 'colour_secondary' => null]);

        $this->export();

        $this->assertSame(['from' => '#1e3a5f'], $this->readJson('players/domains/hunt.json')['colours']);
    }
}
