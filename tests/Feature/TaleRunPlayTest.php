<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\SavedDeck;
use App\Models\Scenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MakesTaleRuns;
use Tests\TestCase;

/**
 * The playtest table playing one scenario of a playthrough: the step being
 * played, its fixed modules, the log so far, and each saved deck's earned
 * rewards. Everything else about the table is unchanged.
 */
class TaleRunPlayTest extends TestCase
{
    use MakesTaleRuns;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
        $this->makeTale();

        $module = Module::create(['slug' => 'tide-of-bones', 'name' => 'Tide of Bones', 'tale_id' => $this->tale->id]);
        $this->steps['kraken']->modules()->attach($module->id, ['sort' => 0]);

        foreach (['gunslinger', 'runesmith'] as $slug) {
            SavedDeck::create(['name' => ucfirst($slug), 'build' => ['character' => $slug, 'domain' => null, 'take' => []]]);
        }
    }

    private function scenarioName(string $slug): string
    {
        return Scenario::where('slug', $slug)->value('name');
    }

    private function deckFor(array $decks, string $character): array
    {
        return collect($decks)->firstWhere('character', $character);
    }

    public function test_the_table_in_a_run_carries_the_step_the_log_and_the_tale(): void
    {
        $run = $this->startRun('First try', 'hard');

        $this->get("/scenarios/kraken/play?run={$run->id}")->assertInertia(fn (Assert $page) => $page
            ->where('tale.run.id', $run->id)
            ->where('tale.tale.slug', 'drowned-coast')
            ->where('tale.mode.name', 'Hard')
            ->where('tale.step.number', 1)
            ->where('tale.step.scenario', $this->scenarioName('kraken'))
            ->where('tale.step.values', [])
            ->has('tale.entries', 5)
            ->has('tale.rewardCards', 2)
            ->where('taleNotice', null)
        );
    }

    public function test_the_log_so_far_reaches_the_next_scenarios_table(): void
    {
        $run = $this->startRun();
        $this->record($run, 'kraken', ['ship' => 3]);

        $this->get("/scenarios/march-of-dead-legion/play?run={$run->id}")->assertInertia(fn (Assert $page) => $page
            ->where('tale.step.number', 2)
            ->where('tale.grid.party.0.cells.0', '3')
        );
    }

    public function test_the_steps_modules_are_chosen_and_fixed(): void
    {
        $run = $this->startRun();

        $this->get("/scenarios/kraken/play?run={$run->id}")->assertInertia(fn (Assert $page) => $page
            ->where('chosen', ['tide-of-bones'])
            ->where('fixedModules', ['tide-of-bones'])
        );

        $this->get("/scenarios/kraken/play?run={$run->id}&modules[]=what-lurks-below")->assertInertia(fn (Assert $page) => $page
            ->where('chosen', ['tide-of-bones', 'what-lurks-below'])
        );
    }

    public function test_the_tales_modules_travel_with_their_names_for_the_setup_screen(): void
    {
        $this->steps['kraken']->modules()->attach(Module::where('slug', 'what-lurks-below')->value('id'), ['sort' => 1]);
        $run = $this->startRun();

        $this->get("/scenarios/kraken/play?run={$run->id}")->assertInertia(fn (Assert $page) => $page
            ->where('tale.modules', [
                ['slug' => 'tide-of-bones', 'name' => 'Tide of Bones'],
                ['slug' => 'what-lurks-below', 'name' => Module::where('slug', 'what-lurks-below')->value('name')],
            ])
            ->where('fixedModules', ['tide-of-bones', 'what-lurks-below'])
        );
    }

    public function test_each_saved_deck_carries_the_rewards_its_character_earned(): void
    {
        $run = $this->startRun();
        $charm = $this->rewards['charm']->id;
        $this->record($run, 'kraken', ['earned' => ['gunslinger' => [$charm]], 'spoils' => [$this->rewards['compass']->id]]);

        $this->get("/scenarios/march-of-dead-legion/play?run={$run->id}")->assertInertia(function (Assert $page) use ($charm) {
            $props = $page->toArray()['props'];

            $this->assertSame([$charm], $this->deckFor($props['decks'], 'gunslinger')['rewards']);
            $this->assertSame([], $this->deckFor($props['decks'], 'runesmith')['rewards']);
            $this->assertArrayHasKey($charm, $props['playerCards']);
            $this->assertSame([$this->rewards['compass']->id], $props['tale']['partyRewards']);
        });
    }

    public function test_a_run_for_another_scenario_is_ignored_and_named(): void
    {
        $run = $this->startRun();

        $this->get("/scenarios/march-of-dead-legion/play?run={$run->id}")->assertInertia(fn (Assert $page) => $page
            ->where('tale', null)
            ->where('taleNotice', fn ($notice) => str_contains($notice, $this->scenarioName('kraken')))
            ->where('fixedModules', [])
        );
    }

    public function test_a_finished_run_is_ignored_and_named(): void
    {
        $run = $this->startRun();
        $this->record($run, 'kraken', []);
        $this->record($run, 'march-of-dead-legion', []);

        $this->get("/scenarios/kraken/play?run={$run->id}")->assertInertia(fn (Assert $page) => $page
            ->where('tale', null)
            ->where('taleNotice', 'This playthrough is finished.')
        );
    }

    public function test_outside_a_run_nothing_changes(): void
    {
        $this->get('/scenarios/kraken/play')->assertInertia(function (Assert $page) {
            $props = $page->toArray()['props'];

            $this->assertNull($props['tale']);
            $this->assertNull($props['taleNotice']);
            $this->assertSame([], $props['fixedModules']);
            foreach ($props['decks'] as $deck) {
                $this->assertSame([], $deck['rewards']);
            }
        });
    }
}
