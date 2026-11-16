<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Scenario;
use App\Models\Tale;
use App\Models\TaleStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Tale's scenarios, in play order, each naming the modules that join it —
 * the Tale's own or shared ones — and the story read around it.
 */
class TaleStepTest extends TestCase
{
    use RefreshDatabase;

    private Tale $tale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
        $this->tale = Tale::create(['slug' => 'drowned-coast', 'name' => 'The Drowned Coast']);
    }

    private function scenario(string $slug): int
    {
        return Scenario::where('slug', $slug)->value('id');
    }

    private function addStep(string $scenario, array $data = []): TaleStep
    {
        $this->post('/tales/drowned-coast/steps', $data + ['scenario_id' => $this->scenario($scenario)])
            ->assertRedirect('/tales/drowned-coast?tab=scenarios');

        // steps() orders by sort; the newest is wanted here, whatever its sort.
        return $this->tale->steps()->reorder()->latest('id')->firstOrFail();
    }

    public function test_a_step_names_a_scenario_and_its_story(): void
    {
        $step = $this->addStep('kraken', ['intro' => 'The fleet sails.', 'outro' => 'Quiet water.', 'notes' => 'Skip beat 1.']);

        $this->assertSame($this->scenario('kraken'), $step->scenario_id);
        $this->assertSame('The fleet sails.', $step->intro);
        $this->assertSame('Skip beat 1.', $step->notes);
    }

    public function test_a_new_step_goes_after_the_others(): void
    {
        $this->addStep('kraken');
        $this->addStep('march-of-dead-legion');

        $this->assertSame(['kraken', 'march-of-dead-legion'], $this->tale->steps()->with('scenario')->get()->pluck('scenario.slug')->all());
    }

    public function test_a_step_takes_modules_in_the_order_given(): void
    {
        $own = Module::create(['slug' => 'tide-of-bones', 'name' => 'Tide of Bones', 'tale_id' => $this->tale->id]);
        $shared = Module::where('slug', 'what-lurks-below')->first();
        $step = $this->addStep('kraken');

        $this->put("/tale-steps/{$step->id}", [
            'scenario_id' => $this->scenario('kraken'),
            'module_ids' => [$own->id, $shared->id],
        ])->assertRedirect();

        $this->assertSame(['tide-of-bones', 'what-lurks-below'], $step->fresh()->modules->pluck('slug')->all());

        $this->put("/tale-steps/{$step->id}", [
            'scenario_id' => $this->scenario('kraken'),
            'module_ids' => [$shared->id],
        ]);

        $this->assertSame(['what-lurks-below'], $step->fresh()->modules->pluck('slug')->all());
    }

    public function test_steps_are_reordered_by_their_sort(): void
    {
        $first = $this->addStep('kraken');
        $second = $this->addStep('march-of-dead-legion');

        $this->put("/tale-steps/{$first->id}", ['scenario_id' => $first->scenario_id, 'sort' => $second->sort + 1]);

        $this->assertSame(['march-of-dead-legion', 'kraken'], $this->tale->steps()->with('scenario')->get()->pluck('scenario.slug')->all());
    }

    public function test_a_step_moves_past_its_neighbour_in_one_request(): void
    {
        $this->addStep('kraken');
        $second = $this->addStep('march-of-dead-legion');
        $order = fn () => $this->tale->steps()->with('scenario')->get()->pluck('scenario.slug')->all();

        $this->post("/tale-steps/{$second->id}/move", ['direction' => 'up'])->assertRedirect('/tales/drowned-coast?tab=scenarios');
        $this->assertSame(['march-of-dead-legion', 'kraken'], $order());

        $this->post("/tale-steps/{$second->id}/move", ['direction' => 'down']);
        $this->assertSame(['kraken', 'march-of-dead-legion'], $order());
    }

    public function test_moving_untangles_steps_that_share_a_sort(): void
    {
        $first = $this->addStep('kraken');
        $second = $this->addStep('march-of-dead-legion');
        $second->update(['sort' => $first->sort]);

        $this->post("/tale-steps/{$first->id}/move", ['direction' => 'down']);

        $this->assertSame(['march-of-dead-legion', 'kraken'], $this->tale->steps()->with('scenario')->get()->pluck('scenario.slug')->all());
    }

    public function test_the_first_step_cannot_move_up(): void
    {
        $first = $this->addStep('kraken');
        $this->addStep('march-of-dead-legion');

        $this->post("/tale-steps/{$first->id}/move", ['direction' => 'up'])->assertRedirect();
        $this->post("/tale-steps/{$first->id}/move", ['direction' => 'sideways'])->assertSessionHasErrors('direction');

        $this->assertSame(['kraken', 'march-of-dead-legion'], $this->tale->steps()->with('scenario')->get()->pluck('scenario.slug')->all());
    }

    public function test_a_step_needs_a_scenario_that_exists(): void
    {
        $this->post('/tales/drowned-coast/steps', ['scenario_id' => 99999])->assertSessionHasErrors('scenario_id');
        $this->post('/tales/drowned-coast/steps', [])->assertSessionHasErrors('scenario_id');
    }

    public function test_deleting_a_step_leaves_the_scenario_and_the_other_steps(): void
    {
        $step = $this->addStep('kraken');
        $this->addStep('march-of-dead-legion');

        $this->delete("/tale-steps/{$step->id}")->assertRedirect('/tales/drowned-coast?tab=scenarios');

        $this->assertSame(1, $this->tale->steps()->count());
        $this->assertTrue(Scenario::where('slug', 'kraken')->exists());
    }
}
