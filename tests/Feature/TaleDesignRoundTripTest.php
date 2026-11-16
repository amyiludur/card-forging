<?php

namespace Tests\Feature;

use App\Models\EntityCard;
use App\Models\Module;
use App\Models\PlayerCard;
use App\Models\Scenario;
use App\Models\Tale;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A Tale is written to design/data/tales/<slug>.json, and a Tale's module
 * keeps its own file in data/modules with a "tale" key — written only when it
 * has one, so every existing module file exports byte for byte.
 */
class TaleDesignRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/tale-'.uniqid());
        $this->artisan('design:import');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);
        File::deleteDirectory($this->path.'-again');

        parent::tearDown();
    }

    private function buildTale(): Tale
    {
        $tale = Tale::create([
            'slug' => 'drowned-coast', 'name' => 'The Drowned Coast', 'set_icon' => 'DC',
            'overview' => "Storms.\n\nAnd worse.", 'status' => 'draft',
        ]);

        $module = Module::create(['slug' => 'tide-of-bones', 'name' => 'Tide of Bones', 'tale_id' => $tale->id, 'set_icon' => 'TB']);
        EntityCard::create(['module_id' => $module->id, 'name' => 'Bone Surf', 'qty' => 1, 'layout' => 'single', 'omen_cost' => 1, 'arrow' => 'top']);

        $kraken = $tale->steps()->create([
            'scenario_id' => Scenario::where('slug', 'kraken')->value('id'),
            'intro' => 'The fleet sails.', 'outro' => 'Quiet water.', 'notes' => 'Skip beat 1.', 'sort' => 0,
        ]);
        $kraken->modules()->attach([$module->id => ['sort' => 0], Module::where('slug', 'what-lurks-below')->value('id') => ['sort' => 1]]);
        $tale->steps()->create(['scenario_id' => Scenario::where('slug', 'march-of-dead-legion')->value('id'), 'sort' => 1]);

        $tale->modes()->create(['name' => 'Story', 'text' => 'Heal between scenarios.', 'sort' => 0]);
        $tale->modes()->create(['name' => 'Hard', 'text' => 'Damage carries over.', 'sort' => 1, 'is_placeholder' => false]);
        $tale->logEntries()->create(['name' => 'Ship damage', 'kind' => 'number', 'scope' => 'party', 'sort' => 0]);
        $tale->logEntries()->create(['name' => 'Damage kept', 'kind' => 'number', 'scope' => 'character', 'sort' => 1]);
        $tale->cards()->create(['slug' => 'the-lighthouse', 'name' => 'The Lighthouse', 'type_line' => 'Story', 'text' => '{this} stands.', 'traits' => ['Coast'], 'sort' => 0]);
        $tale->cards()->create(['slug' => 'the-ship', 'name' => 'The Ship', 'sort' => 1]);

        PlayerCard::create([
            'tale_id' => $tale->id, 'slug' => 'sea-glass-charm', 'name' => 'Sea-glass Charm', 'role' => 'reward',
            'origin' => 'reward', 'type' => 'item', 'start_zone' => 'deck', 'upgrades_to' => 'polished-charm', 'sort' => 0,
        ]);
        PlayerCard::create([
            'tale_id' => $tale->id, 'slug' => 'polished-charm', 'name' => 'Polished Charm', 'role' => 'upgrade',
            'origin' => 'reward', 'type' => 'item', 'start_zone' => 'upgrade', 'upgrade_of' => 'sea-glass-charm', 'sort' => 1,
        ]);

        return $tale;
    }

    private function read(string $path, string $file): array
    {
        return json_decode(File::get("{$path}/{$file}"), true);
    }

    public function test_a_folder_with_no_tales_grows_no_directory_and_module_files_are_unchanged(): void
    {
        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $this->assertDirectoryDoesNotExist("{$this->path}/data/tales");

        foreach (glob(base_path('design/data/modules/*.json')) as $file) {
            $this->assertSame(
                File::get($file),
                File::get("{$this->path}/data/modules/".basename($file)),
                basename($file).' changed shape on the way out',
            );
        }
    }

    public function test_a_tale_is_written_in_the_shape_of_its_file(): void
    {
        $this->buildTale();

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();
        $data = $this->read($this->path, 'data/tales/drowned-coast.json');

        $this->assertSame('drowned-coast', $data['id']);
        $this->assertSame('DC', $data['setIcon']);
        $this->assertSame(['kraken', 'march-of-dead-legion'], array_column($data['steps'], 'scenario'));
        $this->assertSame(['tide-of-bones', 'what-lurks-below'], $data['steps'][0]['modules']);
        $this->assertSame('Skip beat 1.', $data['steps'][0]['notes']);
        $this->assertSame(['Story', 'Hard'], array_column($data['modes'], 'name'));
        $this->assertSame([true, false], array_column($data['modes'], 'isPlaceholder'));
        $this->assertSame(['number', 'number'], array_column($data['logEntries'], 'kind'));
        $this->assertSame(['party', 'character'], array_column($data['logEntries'], 'scope'));
        $this->assertSame(['the-lighthouse', 'the-ship'], array_column($data['cards'], 'id'));
        $this->assertSame('Story', $data['cards'][0]['typeLine']);
        $this->assertSame(['sea-glass-charm'], array_column($data['rewards'], 'id'));
        $this->assertSame(['polished-charm'], array_column($data['upgrades'], 'id'));
    }

    public function test_a_tale_module_file_names_its_tale(): void
    {
        $this->buildTale();

        $this->artisan('design:export', ['--path' => $this->path]);

        $this->assertSame('drowned-coast', $this->read($this->path, 'data/modules/tide-of-bones.json')['tale']);
        $this->assertArrayNotHasKey('tale', $this->read($this->path, 'data/modules/what-lurks-below.json'));
    }

    public function test_a_tale_round_trips_byte_for_byte(): void
    {
        $this->buildTale();

        $this->artisan('design:export', ['--path' => $this->path]);
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();
        $this->artisan('design:export', ['--path' => $this->path.'-again']);

        foreach (['data/tales/drowned-coast.json', 'data/modules/tide-of-bones.json'] as $file) {
            $this->assertSame(File::get("{$this->path}/{$file}"), File::get("{$this->path}-again/{$file}"), "{$file} drifted");
        }

        $tale = Tale::where('slug', 'drowned-coast')->firstOrFail();
        $this->assertSame($tale->id, Module::where('slug', 'tide-of-bones')->value('tale_id'));
        $this->assertSame(2, $tale->steps()->count());
        $this->assertSame(2, $tale->rewardCards()->count());
    }

    public function test_import_prunes_what_the_tale_file_dropped(): void
    {
        $this->buildTale();
        $this->artisan('design:export', ['--path' => $this->path]);

        $file = "{$this->path}/data/tales/drowned-coast.json";
        $data = json_decode(File::get($file), true);
        array_pop($data['modes']);
        array_pop($data['cards']);
        array_pop($data['steps']);
        $data['upgrades'] = [];
        File::put($file, json_encode($data));

        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $tale = Tale::where('slug', 'drowned-coast')->firstOrFail();
        $this->assertSame(['Story'], $tale->modes()->pluck('name')->all());
        $this->assertSame(['the-lighthouse'], $tale->cards()->pluck('slug')->all());
        $this->assertSame(1, $tale->steps()->count());
        $this->assertSame(['sea-glass-charm'], $tale->rewardCards()->pluck('slug')->all());
    }

    public function test_a_tale_whose_file_is_gone_is_kept_and_reported_like_a_module(): void
    {
        // Import never deletes a scenario, a module, a character or a domain
        // the folder does not have, and a Tale takes a whole box with it — its
        // modules and every card in them. So it is kept and named instead.
        $this->buildTale();
        $this->artisan('design:export', ['--path' => $this->path]);
        File::delete("{$this->path}/data/tales/drowned-coast.json");
        File::delete("{$this->path}/data/modules/tide-of-bones.json");

        $this->artisan('design:import', ['--path' => $this->path])
            ->expectsOutputToContain('The Drowned Coast')
            ->assertSuccessful();

        $tale = Tale::where('slug', 'drowned-coast')->firstOrFail();
        $this->assertSame($tale->id, Module::where('slug', 'tide-of-bones')->value('tale_id'));
        $this->assertTrue(EntityCard::where('name', 'Bone Surf')->exists());
        $this->assertSame(2, $tale->steps()->count(), 'a Tale with no file keeps its steps too');
    }

    public function test_a_step_naming_a_missing_scenario_is_skipped_and_reported(): void
    {
        $this->buildTale();
        $this->artisan('design:export', ['--path' => $this->path]);

        $file = "{$this->path}/data/tales/drowned-coast.json";
        $data = json_decode(File::get($file), true);
        $data['steps'][1]['scenario'] = 'nowhere';
        $data['steps'][0]['modules'][] = 'no-such-module';
        File::put($file, json_encode($data));

        $this->artisan('design:import', ['--path' => $this->path])
            ->expectsOutputToContain('nowhere')
            ->expectsOutputToContain('no-such-module')
            ->assertSuccessful();

        $tale = Tale::where('slug', 'drowned-coast')->firstOrFail();
        $this->assertSame(1, $tale->steps()->count());
        $this->assertSame(['tide-of-bones', 'what-lurks-below'], $tale->steps()->first()->modules->pluck('slug')->all());
    }
}
