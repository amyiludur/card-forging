<?php

namespace Tests\Feature;

use App\Models\EntityCard;
use App\Models\Module;
use App\Models\PlayerCard;
use App\Models\Scenario;
use App\Models\Tale;
use App\Models\TaleCard;
use App\Models\TaleLogEntry;
use App\Models\TaleMode;
use App\Models\TaleStep;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A Tale is a campaign box. It lists scenarios rather than owning them, and it
 * owns everything else it brings: its steps, modes, log entries, its own cards,
 * its reward cards and its own modules.
 */
class TaleModelTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    private function tale(): Tale
    {
        return Tale::create(['slug' => 'drowned-coast', 'name' => 'The Drowned Coast']);
    }

    private function step(Tale $tale, string $scenario, int $sort = 0): TaleStep
    {
        return $tale->steps()->create([
            'scenario_id' => Scenario::where('slug', $scenario)->value('id'),
            'sort' => $sort,
        ]);
    }

    private function taleModule(Tale $tale): Module
    {
        $module = Module::create(['slug' => 'tide-of-bones', 'name' => 'Tide of Bones', 'tale_id' => $tale->id]);

        EntityCard::create([
            'module_id' => $module->id, 'name' => 'Bone Surf', 'qty' => 1, 'layout' => 'single',
            'omen_cost' => 1, 'arrow' => 'top',
        ]);

        return $module;
    }

    public function test_deleting_a_tale_takes_everything_it_owns(): void
    {
        $tale = $this->tale();
        $this->step($tale, 'kraken');
        $tale->modes()->create(['name' => 'Hard']);
        $tale->logEntries()->create(['name' => 'Damage kept', 'kind' => 'number', 'scope' => 'character']);
        $tale->cards()->create(['slug' => 'the-lighthouse', 'name' => 'The Lighthouse']);
        $reward = PlayerCard::create([
            'tale_id' => $tale->id, 'slug' => 'sea-glass-charm', 'name' => 'Sea-glass Charm',
            'role' => PlayerCard::ROLE_REWARD, 'origin' => 'reward', 'type' => 'item',
        ]);
        $module = $this->taleModule($tale);

        $tale->delete();

        $this->assertSame(0, TaleStep::count());
        $this->assertSame(0, TaleMode::count());
        $this->assertSame(0, TaleLogEntry::count());
        $this->assertSame(0, TaleCard::count());
        $this->assertNull(PlayerCard::find($reward->id));
        $this->assertNull(Module::find($module->id));
        $this->assertFalse(EntityCard::where('name', 'Bone Surf')->exists());
    }

    public function test_deleting_a_tale_leaves_scenarios_and_shared_modules(): void
    {
        $tale = $this->tale();
        $step = $this->step($tale, 'kraken');
        $step->modules()->attach(Module::where('slug', 'what-lurks-below')->value('id'), ['sort' => 0]);

        $tale->delete();

        $this->assertTrue(Scenario::where('slug', 'kraken')->exists());
        $this->assertTrue(Module::where('slug', 'what-lurks-below')->exists());
    }

    public function test_deleting_a_scenario_drops_the_step_that_named_it(): void
    {
        $tale = $this->tale();
        $this->step($tale, 'kraken', 0);
        $this->step($tale, 'march-of-dead-legion', 1);

        Scenario::where('slug', 'march-of-dead-legion')->first()->delete();

        $this->assertSame(1, $tale->steps()->count());
    }

    public function test_shared_scope_leaves_out_a_tales_modules(): void
    {
        $this->taleModule($this->tale());

        $shared = Module::shared()->pluck('slug');

        $this->assertNotContains('tide-of-bones', $shared);
        $this->assertContains('what-lurks-below', $shared);
        $this->assertTrue(Module::where('slug', 'tide-of-bones')->first()->belongsToTale());
    }

    public function test_a_step_lists_its_modules_in_order(): void
    {
        $tale = $this->tale();
        $step = $this->step($tale, 'kraken');
        $own = $this->taleModule($tale);
        $shared = Module::where('slug', 'what-lurks-below')->first();

        $step->modules()->attach([$shared->id => ['sort' => 1], $own->id => ['sort' => 0]]);

        $this->assertSame(['tide-of-bones', 'what-lurks-below'], $step->fresh()->modules->pluck('slug')->all());
        $this->assertSame(['tide-of-bones'], $tale->modules->pluck('slug')->all());
    }
}
