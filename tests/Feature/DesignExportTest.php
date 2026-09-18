<?php

namespace Tests\Feature;

use App\Models\EntityCard;
use App\Models\RulesConfig;
use App\Models\Scenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class DesignExportTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/design-'.uniqid());
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function exported(string $file): array
    {
        return json_decode(file_get_contents("{$this->path}/data/{$file}"), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_it_writes_the_database_back_out(): void
    {
        $this->artisan('design:import');
        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $this->assertFileExists("{$this->path}/data/kraken.json");
        $this->assertFileExists("{$this->path}/data/rules-config.json");
        $this->assertFileExists("{$this->path}/rules/01-core-rules.md");

        $kraken = $this->exported('kraken.json');

        $this->assertSame('The Kraken', $kraken['name']);
        $this->assertCount(16, $kraken['entityDeck']);
        $this->assertCount(4, $kraken['storyBeats']);
        $this->assertCount(3, $kraken['boardSetup']);
        $this->assertCount(1, $kraken['boardAddedByBeats']);

        // Health stays a number when it is one, and text when it is not.
        $this->assertSame(3, collect($kraken['boardSetup'])->firstWhere('name', 'Tentacle')['health']);
        $this->assertSame('12 per player', collect($kraken['boardSetup'])->firstWhere('name', 'The Kraken')['health']);
    }

    public function test_it_reads_the_position_key_the_design_files_use(): void
    {
        $this->artisan('design:import');
        $this->artisan('design:export', ['--path' => $this->path]);

        // The design folder writes split faces as {position: top}. Round-tripping
        // it must not renumber or reorder the halves.
        $this->artisan('design:import', ['--path' => $this->path]);

        $faces = \App\Models\EntityCard::where('name', 'Whispers Below')->firstOrFail()->faces;

        $this->assertSame(['top', 'bottom'], $faces->pluck('half')->all());
        $this->assertSame('The first player discards a random card.', $faces->firstWhere('half', 'top')->text);
    }

    public function test_an_edit_survives_the_round_trip(): void
    {
        $this->artisan('design:import');

        EntityCard::where('name', 'Crushing Coil')->firstOrFail()->update(['omen_cost' => 5, 'arrow' => 'bottom']);
        RulesConfig::where('key', 'startingOmen')->firstOrFail()->update(['value' => ['v' => 6]]);

        $this->artisan('design:export', ['--path' => $this->path]);

        $card = collect($this->exported('kraken.json')['entityDeck'])->firstWhere('name', 'Crushing Coil');

        $this->assertSame(5, $card['omenCost']);
        $this->assertSame(6, $this->exported('rules-config.json')['startingOmen']);

        // And reading it back gives the same thing.
        $this->artisan('design:import', ['--path' => $this->path]);

        $card = EntityCard::where('name', 'Crushing Coil')->firstOrFail();
        $this->assertSame(5, $card->omen_cost);
        $this->assertSame('bottom', $card->arrow);
        $this->assertSame(6, RulesConfig::where('key', 'startingOmen')->firstOrFail()->raw_value);
    }

    public function test_modules_round_trip(): void
    {
        $this->artisan('design:import');
        $this->artisan('design:export', ['--path' => $this->path]);

        $module = json_decode(file_get_contents("{$this->path}/data/modules/what-lurks-below.json"), true, 512, JSON_THROW_ON_ERROR);

        $this->assertSame('WLB', $module['setIcon']);
        $this->assertSame(['kraken'], $module['compatibleScenarios']);
        $this->assertCount(5, $module['entityCards']);
        $this->assertCount(1, $module['boardCards']);
        $this->assertSame('top', $module['entityCards'][0]['arrow']);

        \App\Models\Module::query()->delete();
        $this->artisan('design:import', ['--path' => $this->path]);

        $this->assertSame(8, \App\Models\Module::where('slug', 'what-lurks-below')->firstOrFail()->deckSize());
    }

    public function test_the_scenarios_module_rules_round_trip(): void
    {
        $this->artisan('design:import');
        $this->artisan('design:export', ['--path' => $this->path]);

        $rules = $this->exported('kraken.json')['moduleRules'];

        $this->assertSame(2, $rules['required']);
        $this->assertSame(['what-lurks-below'], $rules['recommended']);
    }

    public function test_every_exported_card_carries_an_arrow(): void
    {
        $this->artisan('design:import');
        $this->artisan('design:export', ['--path' => $this->path]);

        foreach ($this->exported('kraken.json')['entityDeck'] as $card) {
            $this->assertContains($card['arrow'], ['top', 'bottom'], "{$card['name']} has no arrow");
        }
    }

    public function test_x_cost_and_split_cards_round_trip(): void
    {
        $this->artisan('design:import');
        $this->artisan('design:export', ['--path' => $this->path]);

        $deck = collect($this->exported('kraken.json')['entityDeck']);

        $this->assertSame('X', $deck->firstWhere('name', 'Grasping Depths')['omenCost']);

        $split = $deck->firstWhere('name', 'Storm Surge');
        $this->assertCount(2, $split['faces']);
        $this->assertSame('top', $split['faces'][0]['position']);
        $this->assertSame('hazard', $split['faces'][1]['type']);

        Scenario::query()->delete();
        $this->artisan('design:import', ['--path' => $this->path]);

        $this->assertSame(34, Scenario::where('slug', 'kraken')->firstOrFail()->deckSize());
    }
}
