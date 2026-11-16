<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\TaleRun;
use App\Models\TaleRunResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\MakesTaleRuns;
use Tests\TestCase;

/**
 * A playthrough of a Tale: one row, and one result per scenario played. The
 * next scenario is worked out from what is recorded, never stored, so it
 * survives the Tale's steps changing under it.
 */
class TaleRunModelTest extends TestCase
{
    use MakesTaleRuns;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
        $this->makeTale();
    }

    public function test_the_next_step_is_the_first_one_not_recorded(): void
    {
        $run = $this->startRun();
        $this->assertTrue($run->nextStep()->is($this->steps['kraken']));

        $this->record($run, 'kraken', ['ship' => 1]);
        $this->assertTrue($run->fresh()->nextStep()->is($this->steps['march-of-dead-legion']));

        $this->record($run, 'march-of-dead-legion', ['ship' => 2]);
        $this->assertNull($run->fresh()->nextStep());
        $this->assertTrue($run->fresh()->isFinished());
    }

    public function test_recording_a_step_again_overwrites_it(): void
    {
        $run = $this->startRun();
        $this->record($run, 'kraken', ['ship' => 1]);
        $this->record($run, 'kraken', ['ship' => 5]);

        $this->assertSame(1, $run->results()->count());
        $this->assertSame(5, $run->resultFor($this->steps['kraken'])->values[(string) $this->entries['ship']->id]);
    }

    public function test_deleting_a_recorded_step_drops_its_result_and_moves_the_next_step(): void
    {
        $run = $this->startRun();
        $this->record($run, 'kraken', ['ship' => 1]);
        $this->steps['kraken']->delete();

        $this->assertSame(0, TaleRunResult::count());
        $this->assertTrue($run->fresh()->nextStep()->is($this->steps['march-of-dead-legion']));
    }

    public function test_deleting_the_runs_mode_leaves_the_run_with_none(): void
    {
        $run = $this->startRun('Hard try', 'hard');
        $this->modes['hard']->delete();

        $this->assertNotNull($run->fresh());
        $this->assertNull($run->fresh()->tale_mode_id);
    }

    public function test_rewards_are_read_per_character_and_for_the_party(): void
    {
        $charm = $this->rewards['charm']->id;
        $compass = $this->rewards['compass']->id;
        $run = $this->startRun();

        $this->record($run, 'kraken', [
            'earned' => ['gunslinger' => [$charm], 'runesmith' => [$compass]],
            'spoils' => [$compass],
        ]);
        $this->record($run, 'march-of-dead-legion', ['earned' => ['gunslinger' => [$compass, $charm]]]);

        $run = $run->fresh();
        $this->assertSame([$charm, $compass], $run->rewardIdsFor('gunslinger'));
        $this->assertSame([$compass], $run->rewardIdsFor('runesmith'));
        $this->assertSame([], $run->rewardIdsFor('nobody'));
        $this->assertSame([$compass], $run->partyRewardIds());
        $this->assertSame(['gunslinger', 'runesmith'], $run->characters());
    }

    public function test_the_log_grid_shows_what_was_written(): void
    {
        $run = $this->startRun();
        $this->record($run, 'kraken', [
            'ship' => 3,
            'lighthouse' => true,
            'kept' => ['gunslinger' => 4],
            'earned' => ['gunslinger' => [$this->rewards['charm']->id]],
        ]);

        $grid = $run->fresh()->logGrid();

        $this->assertSame([true, false], array_column($grid['steps'], 'recorded'));
        $this->assertSame(['3', ''], $grid['party'][0]['cells']);
        $this->assertSame(['✓', ''], $grid['party'][1]['cells']);
        $this->assertSame(['', ''], $grid['party'][2]['cells'], 'no spoils recorded');

        $this->assertCount(1, $grid['characters']);
        $this->assertSame('gunslinger', $grid['characters'][0]['slug']);
        $this->assertSame(Character::where('slug', 'gunslinger')->value('name'), $grid['characters'][0]['name']);
        $this->assertSame(['4', ''], $grid['characters'][0]['rows'][0]['cells']);
        $this->assertSame(['Sea-glass Charm', ''], $grid['characters'][0]['rows'][1]['cells']);
    }

    public function test_an_unticked_box_reads_as_a_dash(): void
    {
        $run = $this->startRun();
        $this->record($run, 'kraken', ['lighthouse' => false]);

        $this->assertSame(['—', ''], $run->fresh()->logGrid()['party'][1]['cells']);
    }

    public function test_a_tale_with_no_steps_has_no_next_step_and_is_not_finished(): void
    {
        $this->tale->steps()->delete();
        $run = $this->startRun();

        $this->assertNull($run->nextStep());
        $this->assertFalse($run->isFinished());
    }

    public function test_runs_are_listed_newest_first(): void
    {
        $first = $this->startRun('First');
        $second = $this->startRun('Second');
        $first->forceFill(['created_at' => now()->subDay()])->save();

        $this->assertSame(['Second', 'First'], $this->tale->runs()->pluck('name')->all());
        $this->assertInstanceOf(TaleRun::class, $second);
    }
}
