<?php

namespace Tests\Feature;

use App\Models\PrintPoolItem;
use App\Models\Scenario;
use App\Models\ScenarioTownPick;
use App\Models\TownAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A common district is written once and picked by each scenario that wants
 * it, with a line of the scenario's own. It is a district with no owner, so
 * the card face, print, pocket and the table need no second kind of district.
 */
class CommonDistrictTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/common-town-'.uniqid());
        $this->artisan('design:import');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function addMarket(array $fields = []): TownAction
    {
        $this->post('/town', $fields + [
            'name' => 'Market',
            'effect' => 'Buy a potion',
            'gold_cost' => 2,
            'omen' => 1,
            'note' => '+1{gold} per town action used',
        ])->assertRedirect();

        return TownAction::common()->where('name', $fields['name'] ?? 'Market')->firstOrFail();
    }

    private function pick(string $scenario, TownAction $district, ?string $extra = null): ScenarioTownPick
    {
        $this->post("/scenarios/{$scenario}/town-picks", ['town_action_id' => $district->id])->assertRedirect();
        $pick = ScenarioTownPick::where('town_action_id', $district->id)
            ->where('scenario_id', Scenario::where('slug', $scenario)->value('id'))
            ->firstOrFail();

        if ($extra !== null) {
            $this->put("/town-picks/{$pick->id}", ['extra' => $extra])->assertRedirect();
        }

        return $pick->fresh();
    }

    public function test_a_common_district_belongs_to_no_scenario_and_no_module(): void
    {
        $market = $this->addMarket();

        $this->assertTrue($market->isCommon());

        $this->get('/town')->assertInertia(fn (Assert $page) => $page
            ->component('Town/Index')
            ->where('districts', fn ($districts) => collect($districts)->contains('name', 'Market'))
        );
    }

    public function test_a_picked_district_joins_the_end_of_the_town_with_its_line(): void
    {
        $market = $this->addMarket();
        $this->pick('kraken', $market, 'Closed while the {card:kraken/whirlpool} is in play.');

        $this->get('/scenarios/kraken')->assertInertia(fn (Assert $page) => $page
            ->where('townActions', function ($town) {
                $last = collect($town)->last();

                return $last['name'] === 'Market'
                    && $last['pick_id'] !== null
                    && $last['own_note'] === '+1{gold} per town action used'
                    && $last['note'] === "+1{gold} per town action used\nClosed while the {card:kraken/whirlpool} is in play."
                    && str_contains($last['note_html'], 'Whirlpool');
            })
        );
    }

    public function test_the_town_is_merged_by_sort(): void
    {
        $kraken = Scenario::where('slug', 'kraken')->firstOrFail();
        $pick = $this->pick('kraken', $this->addMarket());

        // Put the pick before every district of the Kraken's own.
        $pick->update(['sort' => -1]);

        $this->assertSame('Market', $kraken->fresh()->town()->first()->name);
    }

    public function test_a_district_is_in_a_town_once_and_only_a_common_one_can_be_picked(): void
    {
        $market = $this->addMarket();
        $this->pick('kraken', $market);

        $this->post('/scenarios/kraken/town-picks', ['town_action_id' => $market->id])
            ->assertSessionHasErrors('town_action_id');

        $own = TownAction::where('scenario_id', Scenario::where('slug', 'kraken')->value('id'))->firstOrFail();
        $this->post('/scenarios/march-of-dead-legion/town-picks', ['town_action_id' => $own->id])
            ->assertSessionHasErrors('town_action_id');

        $this->assertSame(1, ScenarioTownPick::where('town_action_id', $market->id)->count());
    }

    public function test_dread_tokens_are_the_picking_scenarios(): void
    {
        $market = $this->addMarket(['note' => '{dreadRule}']);
        $this->pick('kraken', $market);
        $kraken = Scenario::where('slug', 'kraken')->firstOrFail();

        $this->get('/scenarios/kraken')->assertInertia(fn (Assert $page) => $page
            ->where('townActions', fn ($town) => collect($town)->last()['dread_rule'] === $kraken->dread_effect)
        );

        // On its own page it has no scenario, like a module card.
        $this->get('/town')->assertInertia(fn (Assert $page) => $page
            ->where('districts', fn ($districts) => str_contains(collect($districts)->firstWhere('name', 'Market')['note_html'], '?dreadRule'))
        );
    }

    public function test_the_line_can_be_cleared_and_the_pick_taken_out(): void
    {
        $pick = $this->pick('kraken', $this->addMarket(), 'Only at high tide.');

        $this->put("/town-picks/{$pick->id}", ['extra' => ''])->assertRedirect();
        $this->assertNull($pick->fresh()->extra);

        $this->delete("/town-picks/{$pick->id}")->assertRedirect();
        $this->assertFalse(ScenarioTownPick::whereKey($pick->id)->exists());
        $this->assertTrue(TownAction::common()->where('name', 'Market')->exists());
    }

    public function test_deleting_a_common_district_takes_it_out_of_every_town(): void
    {
        $market = $this->addMarket();
        $this->pick('kraken', $market);
        $this->pick('march-of-dead-legion', $market);

        $this->get('/town')->assertInertia(fn (Assert $page) => $page
            ->where('districts', fn ($districts) => count(collect($districts)->firstWhere('name', 'Market')['used_by']) === 2)
        );

        $this->delete("/town-actions/{$market->id}")->assertRedirect();

        $this->assertSame(0, ScenarioTownPick::where('town_action_id', $market->id)->count());
        $this->get('/scenarios/kraken')->assertOk();
    }

    public function test_the_print_run_the_pool_the_pocket_and_the_table_carry_the_pick(): void
    {
        $market = $this->addMarket();
        $kraken = $this->pick('kraken', $market, 'Only at high tide.');
        $legion = $this->pick('march-of-dead-legion', $market, 'Closed while burning.');

        $sheet = $this->get('/print/kraken/sheet?deck=town')->assertOk()->assertSee('Market')->assertSee('Only at high tide.')->getContent();
        // Marked as common in the foot — once per common district in this town,
        // and never on a district of the scenario's own.
        $common = Scenario::where('slug', 'kraken')->firstOrFail()->town()->filter->isCommon()->count();
        $this->assertSame($common, substr_count($sheet, 'class="common-mark"'));

        // Each scenario's pick is its own pool key, so each prints its own line.
        $this->post('/print-pool', ['items' => [['key' => 'pick:'.$kraken->id], ['key' => 'pick:'.$legion->id]]])->assertRedirect();
        $this->assertSame(2, PrintPoolItem::count());
        $sheet = $this->get('/print/pool/sheet')->assertOk()->getContent();
        $this->assertTrue(str_contains($sheet, 'Only at high tide.') && str_contains($sheet, 'Closed while burning.'), 'The pool sheet lost a scenario\'s line.');

        $pocket = $this->get('/pocket')->assertOk()->getContent();
        $this->assertTrue(str_contains($pocket, 'Common districts'), 'The pocket page has no common districts section.');
        $this->assertTrue(str_contains($pocket, 'Only at high tide.'), 'The pocket page does not show the Kraken\'s line.');

        $this->get('/scenarios/kraken/play')->assertInertia(fn (Assert $page) => $page
            ->where('town', fn ($town) => collect($town)->contains(fn ($d) => $d['name'] === 'Market' && str_contains($d['note'], 'Only at high tide.')))
        );
    }

    public function test_common_districts_and_picks_round_trip_through_the_design_folder(): void
    {
        $market = $this->addMarket();
        $this->pick('kraken', $market, 'Only at high tide.');
        $this->pick('march-of-dead-legion', $market);
        $picks = ScenarioTownPick::count();

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $common = json_decode(file_get_contents("{$this->path}/data/town.json"), true)['districts'];
        $this->assertContains(
            ['name' => 'Market', 'effect' => 'Buy a potion', 'goldCost' => 2, 'omen' => 1, 'note' => '+1{gold} per town action used'],
            $common,
        );

        $kraken = json_decode(file_get_contents("{$this->path}/data/kraken.json"), true);
        $this->assertSame(['common' => 'market', 'extra' => 'Only at high tide.'], end($kraken['town']));
        $legion = json_decode(file_get_contents("{$this->path}/data/march-of-dead-legion.json"), true);
        $this->assertSame(['common' => 'market'], end($legion['town']));

        // Back in, twice: the same rows, and the export is the same bytes.
        $before = file_get_contents("{$this->path}/data/kraken.json");
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $this->assertSame(1, TownAction::common()->where('name', 'Market')->count());
        $this->assertSame($picks, ScenarioTownPick::count());

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();
        $this->assertSame($before, file_get_contents("{$this->path}/data/kraken.json"));
    }

    public function test_a_district_moved_into_the_library_leaves_no_copy_behind(): void
    {
        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        // Move the Kraken's first own district into the common library by hand.
        $file = "{$this->path}/data/kraken.json";
        $kraken = json_decode(file_get_contents($file), true);
        $index = collect($kraken['town'])->search(fn ($d) => isset($d['name']));
        $moved = $kraken['town'][$index];
        $kraken['town'][$index] = ['common' => Str::slug($moved['name'])];
        file_put_contents($file, json_encode($kraken));

        $townFile = "{$this->path}/data/town.json";
        $town = is_file($townFile) ? json_decode(file_get_contents($townFile), true) : ['districts' => []];
        $town['districts'] = array_values(array_filter($town['districts'], fn ($d) => $d['name'] !== $moved['name']));
        $town['districts'][] = $moved;
        file_put_contents($townFile, json_encode($town));

        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $scenario = Scenario::where('slug', 'kraken')->firstOrFail();
        $this->assertSame(0, $scenario->townActions()->where('name', $moved['name'])->count());
        $district = $scenario->town()->firstWhere('name', $moved['name']);
        $this->assertNotNull($district);
        $this->assertTrue($district->isCommon());
    }

    public function test_a_pick_naming_no_common_district_is_left_out_and_named(): void
    {
        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $file = "{$this->path}/data/kraken.json";
        $kraken = json_decode(file_get_contents($file), true);
        $kraken['town'][] = ['common' => 'lighthouse'];
        file_put_contents($file, json_encode($kraken));

        $this->artisan('design:import', ['--path' => $this->path])
            ->expectsOutputToContain('"lighthouse", which is not a common district')
            ->assertSuccessful();
    }

    public function test_a_folder_without_common_districts_grows_no_file(): void
    {
        TownAction::common()->delete();

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $this->assertFileDoesNotExist("{$this->path}/data/town.json");
    }
}
