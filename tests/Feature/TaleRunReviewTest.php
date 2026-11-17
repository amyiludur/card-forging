<?php

namespace Tests\Feature;

use App\Models\SavedDeck;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\Concerns\MakesTaleRuns;
use Tests\TestCase;

/**
 * What the final review of Tale playthroughs found: a log that the design
 * folder's round trip wiped, a log that crashed after an ordinary editor
 * change, a reward dealt after it was deleted, an empty Tale that said it was
 * finished, and a recorded result nobody could correct.
 */
class TaleRunReviewTest extends TestCase
{
    use MakesTaleRuns;
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/tale-run-'.uniqid());
        $this->artisan('design:import');
        $this->makeTale();
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    public function test_a_playthrough_survives_the_design_folder_round_trip(): void
    {
        $run = $this->startRun('Kept', 'hard');
        $this->record($run, 'kraken', ['ship' => 3, 'kept' => ['gunslinger' => 4]]);

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $run = $run->fresh();
        $this->assertSame(1, $run->results()->count(), 'The import wiped the playthrough\'s results.');
        $this->assertSame($this->modes['hard']->id, $run->tale_mode_id, 'The import cleared the playthrough\'s mode.');
        $grid = $run->logGrid();
        $this->assertSame('3', $grid['party'][0]['cells'][0], 'The results no longer match the log entries.');
        $this->assertSame('4', $grid['characters'][0]['rows'][0]['cells'][0]);
    }

    public function test_reordering_the_tales_scenarios_in_the_file_keeps_each_result_on_its_scenario(): void
    {
        $run = $this->startRun();
        $this->record($run, 'kraken', ['ship' => 3]);

        $this->artisan('design:export', ['--path' => $this->path]);
        $file = "{$this->path}/data/tales/drowned-coast.json";
        $data = json_decode(File::get($file), true);
        $data['steps'] = array_reverse($data['steps']);
        File::put($file, json_encode($data));
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $grid = $run->fresh()->logGrid();
        $this->assertSame(['march-of-dead-legion', 'kraken'], $this->tale->steps()->with('scenario')->get()->pluck('scenario.slug')->all());
        $this->assertSame(['', '3'], $grid['party'][0]['cells'], 'The Kraken\'s result moved to another scenario.');
    }

    public function test_changing_an_entrys_scope_or_kind_after_results_exist_does_not_crash(): void
    {
        $run = $this->startRun();
        $this->record($run, 'kraken', [
            'kept' => ['gunslinger' => 4],
            'earned' => ['gunslinger' => [$this->rewards['charm']->id]],
            'ship' => 3,
        ]);

        $this->entries['kept']->update(['scope' => 'party']);
        $this->entries['earned']->update(['kind' => 'number']);
        $this->entries['ship']->update(['scope' => 'character']);

        $this->get('/tales/drowned-coast')->assertOk();
        $this->get("/scenarios/march-of-dead-legion/play?run={$run->id}")->assertOk();

        $this->assertNotContains('0', $run->fresh()->characters(), 'A party value was read as a character called "0".');
    }

    public function test_a_reward_deleted_after_it_was_earned_is_not_dealt(): void
    {
        SavedDeck::create(['name' => 'Gunslinger', 'build' => ['character' => 'gunslinger', 'domain' => null, 'take' => []]]);
        $run = $this->startRun();
        $this->record($run, 'kraken', ['earned' => ['gunslinger' => [$this->rewards['charm']->id, $this->rewards['compass']->id]]]);
        $this->rewards['charm']->delete();

        $this->get("/scenarios/march-of-dead-legion/play?run={$run->id}")->assertInertia(function (Assert $page) {
            $deck = collect($page->toArray()['props']['decks'])->firstWhere('character', 'gunslinger');
            $this->assertSame([$this->rewards['compass']->id], $deck['rewards']);
        });
    }

    public function test_a_tale_with_no_scenarios_says_so_rather_than_finished(): void
    {
        $this->tale->steps()->delete();
        $run = $this->startRun();

        $this->get("/tale-runs/{$run->id}/play")->assertSessionHas('error', 'This Tale has no scenarios yet.');
        $this->get("/scenarios/kraken/play?run={$run->id}")->assertInertia(fn (Assert $page) => $page
            ->where('taleNotice', 'This Tale has no scenarios yet.')
        );
    }

    public function test_the_tale_page_sends_what_editing_a_recorded_result_needs(): void
    {
        $run = $this->startRun();
        $this->record($run, 'kraken', ['ship' => 3, 'kept' => ['gunslinger' => 4]]);

        $this->get('/tales/drowned-coast')->assertInertia(fn (Assert $page) => $page
            ->where('runs.0.results.'.$this->steps['kraken']->id.'.'.$this->entries['ship']->id, 3)
            ->has('recordEntries', 5)
            ->has('recordEntries.0.name_html')
        );
    }
}
