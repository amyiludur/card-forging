<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Scenario;
use App\Models\TownAction;
use App\Support\PrintCatalogue;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A module can bring districts of its own — the Revolt of the Peasants' Farm —
 * and they join the town when the module is chosen. A district belongs to a
 * scenario or to a module, never both, the same as an entity or board card.
 */
class ModuleTownTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/module-town-'.uniqid());
        $this->artisan('design:import');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function addFarm(string $module = 'what-lurks-below'): TownAction
    {
        $this->post("/modules/{$module}/town-actions", [
            'name' => 'Farm',
            'effect' => 'Gain 2 {gold}',
            'gold_cost' => 1,
            'omen' => 1,
        ])->assertRedirect();

        return TownAction::where('name', 'Farm')->firstOrFail();
    }

    public function test_a_module_can_add_a_district(): void
    {
        $farm = $this->addFarm();

        $this->assertNull($farm->scenario_id);
        $this->assertSame(Module::where('slug', 'what-lurks-below')->value('id'), $farm->module_id);

        $this->get('/modules/what-lurks-below')->assertInertia(fn (Assert $page) => $page
            ->component('Modules/Show')
            ->has('townActions', 1)
            ->where('townActions.0.name', 'Farm')
            ->where('townActions.0.set_icon', 'WLB')
        );
    }

    public function test_a_module_district_has_no_dread_rule_to_quote(): void
    {
        $this->post('/modules/what-lurks-below/town-actions', [
            'name' => 'Farm', 'effect' => '{dreadRule}', 'omen' => 1,
        ]);

        $this->get('/modules/what-lurks-below')->assertInertia(fn (Assert $page) => $page
            ->where('townActions.0.html', fn ($html) => str_contains($html, '?dreadRule'))
        );
    }

    public function test_deleting_a_module_takes_its_districts_and_a_scenario_does_not(): void
    {
        $farm = $this->addFarm();

        $kraken = Scenario::where('slug', 'kraken')->firstOrFail();
        $this->assertGreaterThan(0, TownAction::where('scenario_id', $kraken->id)->count());

        $kraken->delete();
        // The scenario still takes its own districts with it.
        $this->assertSame(0, TownAction::where('scenario_id', $kraken->id)->count());
        $this->assertTrue(TownAction::whereKey($farm->id)->exists());

        $this->delete('/modules/what-lurks-below');
        $this->assertFalse(TownAction::whereKey($farm->id)->exists());
    }

    public function test_a_module_prints_its_districts(): void
    {
        $this->addFarm();

        $this->get('/print/module/what-lurks-below/sheet?deck=town')
            ->assertOk()
            ->assertSee('Farm')
            ->assertSee('WLB');
    }

    public function test_the_print_pool_names_the_module_a_district_came_from(): void
    {
        $farm = $this->addFarm();

        $this->assertSame(
            'What Lurks Below (module)',
            app(PrintCatalogue::class)->describe('town', $farm->load('scenario', 'module'))['source'],
        );
    }

    public function test_the_table_gets_a_module_district_only_when_chosen(): void
    {
        $this->addFarm('that-which-comes-from-the-sky');

        $names = fn (array $props) => collect($props['town'])->pluck('name')->all();

        $this->get('/scenarios/kraken/play?modules[]=what-lurks-below')
            ->assertInertia(fn (Assert $page) => $this->assertNotContains('Farm', $names($page->toArray()['props'])));

        $this->get('/scenarios/kraken/play?modules[]=what-lurks-below&modules[]=that-which-comes-from-the-sky')
            ->assertInertia(function (Assert $page) use ($names) {
                $town = $names($page->toArray()['props']);
                // After the scenario's own districts.
                $this->assertSame('Farm', end($town));
                $this->assertGreaterThan(1, count($town));
            });
    }

    public function test_a_module_district_round_trips_through_the_design_folder(): void
    {
        $this->addFarm();

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();
        $file = json_decode(file_get_contents("{$this->path}/data/modules/what-lurks-below.json"), true);

        $this->assertSame([['name' => 'Farm', 'effect' => 'Gain 2 {gold}', 'goldCost' => 1, 'omen' => 1]], $file['town']);

        // Gone from the database, so only the file can bring it back.
        TownAction::where('name', 'Farm')->delete();

        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $this->assertSame(1, TownAction::where('name', 'Farm')->whereNotNull('module_id')->count());
    }

    public function test_a_module_without_districts_writes_no_town_key(): void
    {
        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        foreach (['what-lurks-below', 'that-which-comes-from-the-sky'] as $slug) {
            $this->assertSame(
                file_get_contents(base_path("design/data/modules/{$slug}.json")),
                file_get_contents("{$this->path}/data/modules/{$slug}.json"),
            );
        }
    }
}
