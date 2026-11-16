<?php

namespace Tests\Feature;

use App\Models\PlayerCard;
use App\Models\Tale;
use App\Models\TaleRun;
use App\Models\TaleRunResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesTaleRuns;
use Tests\TestCase;

/**
 * Starting a playthrough and writing a scenario's results into its log. What
 * is written is checked against the Tale's own entries and rewards, so a
 * result can never quote another Tale's log or hand out a stranger's card.
 */
class TaleRunTest extends TestCase
{
    use MakesTaleRuns;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
        $this->makeTale();
    }

    private function tab(): string
    {
        return '/tales/drowned-coast?tab=playthroughs';
    }

    public function test_a_run_is_started_in_a_mode(): void
    {
        $this->post('/tales/drowned-coast/runs', ['name' => 'Hard try', 'tale_mode_id' => $this->modes['hard']->id])
            ->assertRedirect($this->tab());

        $run = TaleRun::firstOrFail();
        $this->assertSame('Hard try', $run->name);
        $this->assertSame($this->modes['hard']->id, $run->tale_mode_id);
    }

    public function test_a_run_cannot_take_another_tales_mode(): void
    {
        $other = Tale::create(['slug' => 'other', 'name' => 'Other']);
        $mode = $other->modes()->create(['name' => 'Elsewhere']);

        $this->post('/tales/drowned-coast/runs', ['name' => 'Try', 'tale_mode_id' => $mode->id])
            ->assertSessionHasErrors('tale_mode_id');
        $this->post('/tales/drowned-coast/runs', ['name' => ''])->assertSessionHasErrors('name');

        $this->assertSame(0, TaleRun::count());
    }

    public function test_the_mode_can_be_changed_on_a_run_and_a_run_deleted(): void
    {
        $run = $this->startRun('Try', 'story');

        $this->put("/tale-runs/{$run->id}", ['name' => 'Try', 'tale_mode_id' => $this->modes['hard']->id])->assertRedirect($this->tab());
        $this->assertSame($this->modes['hard']->id, $run->fresh()->tale_mode_id);

        $this->delete("/tale-runs/{$run->id}")->assertRedirect($this->tab());
        $this->assertSame(0, TaleRun::count());
    }

    public function test_play_next_goes_to_the_next_scenario_with_the_run(): void
    {
        $run = $this->startRun();

        $this->get("/tale-runs/{$run->id}/play")->assertRedirect("/scenarios/kraken/play?run={$run->id}");

        $this->record($run, 'kraken', ['ship' => 1]);
        $this->get("/tale-runs/{$run->id}/play")->assertRedirect("/scenarios/march-of-dead-legion/play?run={$run->id}");

        $this->record($run, 'march-of-dead-legion', ['ship' => 2]);
        $this->get("/tale-runs/{$run->id}/play")
            ->assertRedirect($this->tab())
            ->assertSessionHas('error', 'Nothing left to play in this playthrough.');
    }

    public function test_results_are_recorded_and_overwritten(): void
    {
        $run = $this->startRun();
        $charm = $this->rewards['charm']->id;
        $url = "/tale-runs/{$run->id}/results/{$this->steps['kraken']->id}";

        $this->put($url, ['values' => $this->byId([
            'ship' => '3',
            'lighthouse' => '1',
            'kept' => ['gunslinger' => '4'],
            'earned' => ['gunslinger' => [(string) $charm]],
        ])])->assertSessionHasNoErrors()->assertRedirect($this->tab());

        $this->assertSame($this->byId([
            'ship' => 3,
            'lighthouse' => true,
            'kept' => ['gunslinger' => 4],
            'earned' => ['gunslinger' => [$charm]],
            'spoils' => [],
        ]), $run->resultFor($this->steps['kraken'])->values);

        $this->put($url, ['values' => $this->byId(['ship' => 5])]);

        $values = $run->resultFor($this->steps['kraken'])->values;
        $this->assertSame(5, $values[$this->entries['ship']->id]);
        $this->assertSame([], $values[$this->entries['kept']->id]);
        $this->assertFalse($values[$this->entries['lighthouse']->id]);
        $this->assertSame(1, TaleRunResult::count());
    }

    public function test_recording_rejects_what_is_not_this_tales(): void
    {
        $run = $this->startRun();
        $url = "/tale-runs/{$run->id}/results/{$this->steps['kraken']->id}";

        $other = Tale::create(['slug' => 'other', 'name' => 'Other']);
        $stranger = $other->logEntries()->create(['name' => 'Elsewhere', 'kind' => 'number', 'scope' => 'party']);
        $domainCard = PlayerCard::whereNotNull('domain_id')->value('id');

        $attempts = [
            'another Tale\'s entry' => [(string) $stranger->id => 1],
            'a card that is not this Tale\'s reward' => $this->byId(['earned' => ['gunslinger' => [$domainCard]]]),
            'a character that does not exist' => $this->byId(['kept' => ['nobody' => 1]]),
            'a number that is not one' => $this->byId(['ship' => 'lots']),
            'a character entry written for the party' => $this->byId(['kept' => 4]),
        ];

        foreach ($attempts as $what => $values) {
            $this->put($url, ['values' => $values])->assertSessionHasErrors('values', null, 'default');
            $this->assertSame(0, TaleRunResult::count(), "Recorded {$what}.");
        }
    }

    public function test_a_step_of_another_tale_cannot_be_recorded(): void
    {
        $run = $this->startRun();
        $other = Tale::create(['slug' => 'other', 'name' => 'Other']);
        $step = $other->steps()->create(['scenario_id' => $this->steps['kraken']->scenario_id]);

        $this->put("/tale-runs/{$run->id}/results/{$step->id}", ['values' => []])->assertNotFound();
    }
}
