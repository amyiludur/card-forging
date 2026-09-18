<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\CardType;
use App\Models\EntityCard;
use App\Models\Module;
use App\Models\Scenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ModuleTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    private function typeId(string $slug): int
    {
        return CardType::where('slug', $slug)->firstOrFail()->id;
    }

    public function test_it_lists_modules(): void
    {
        $this->get('/modules')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->component('Modules/Index')->has('modules', 2));
    }

    public function test_it_shows_a_module_with_its_cards(): void
    {
        $this->get('/modules/what-lurks-below')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Modules/Show')
                ->where('module.set_icon', 'WLB')
                ->where('module.deck_size', 8)
                ->has('cards', 5)
                ->has('boardCards', 1)
            );
    }

    public function test_it_creates_a_module(): void
    {
        $this->post('/modules', [
            'name' => 'The Long Night',
            'set_icon' => 'TLN',
            'theme' => 'Darkness that does not lift.',
            'compatible_scenarios' => ['kraken'],
            'traits' => ['Night'],
        ])->assertRedirect('/modules/the-long-night');

        $module = Module::where('slug', 'the-long-night')->firstOrFail();

        $this->assertSame('TLN', $module->set_icon);
        $this->assertSame(['kraken'], $module->compatible_scenarios);
    }

    public function test_a_module_with_no_scenarios_listed_works_with_all_of_them(): void
    {
        $this->post('/modules', ['name' => 'Universal', 'compatible_scenarios' => []]);

        $module = Module::where('slug', 'universal')->firstOrFail();

        $this->assertTrue($module->worksWith(Scenario::where('slug', 'kraken')->firstOrFail()));
    }

    public function test_it_rejects_a_scenario_that_does_not_exist(): void
    {
        $this->post('/modules', ['name' => 'Broken', 'compatible_scenarios' => ['nope']])
            ->assertSessionHasErrors('compatible_scenarios.0');
    }

    public function test_deleting_a_module_takes_its_cards_and_leaves_the_scenario_alone(): void
    {
        $module = Module::where('slug', 'what-lurks-below')->firstOrFail();
        $before = EntityCard::whereNotNull('scenario_id')->count();

        $this->delete("/modules/{$module->slug}")->assertRedirect('/modules');

        $this->assertDatabaseMissing('modules', ['id' => $module->id]);
        $this->assertSame(0, EntityCard::where('module_id', $module->id)->count());
        $this->assertSame(0, BoardCard::where('module_id', $module->id)->count());
        $this->assertSame($before, EntityCard::whereNotNull('scenario_id')->count());
    }

    public function test_a_card_can_be_created_inside_a_module(): void
    {
        $this->post('/cards?module=what-lurks-below', [
            'name' => 'Black Water',
            'qty' => 2,
            'layout' => 'single',
            'omen_cost' => 2,
            'omen_is_x' => false,
            'traits' => ['Deep'],
            'arrow' => 'bottom',
            'is_placeholder' => true,
            'faces' => [['half' => 'single', 'card_type_id' => $this->typeId('curse'), 'text' => 'Add 2 omen.']],
        ])->assertRedirect();

        $card = EntityCard::where('name', 'Black Water')->firstOrFail();

        $this->assertNull($card->scenario_id);
        $this->assertSame('what-lurks-below', $card->module->slug);
        $this->assertSame('bottom', $card->arrow);
    }

    public function test_the_card_list_can_be_scoped_to_a_module(): void
    {
        $this->get('/cards?module=what-lurks-below')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Cards/Index')
                ->where('module.set_icon', 'WLB')
                ->has('cards', 5)
            );
    }

    public function test_a_module_prints_as_its_own_deck_with_its_set_icon(): void
    {
        $html = $this->get('/print/module/what-lurks-below/sheet?deck=entity')->assertOk()->getContent();

        $this->assertStringContainsString('Drowned Sailors', $html);
        $this->assertStringContainsString('WLB', $html);
        // The scenario's own cards are not in a module print.
        $this->assertStringNotContainsString('Tentacle Lash', $html);
        $this->assertSame(8, substr_count($html, 'class="arrow-edge'));
    }

    public function test_a_module_board_card_prints_with_the_module(): void
    {
        $html = $this->get('/print/module/what-lurks-below/sheet?deck=board')->getContent();

        $this->assertStringContainsString('Drowned', $html);
        $this->assertStringContainsString('WLB', $html);
    }
}
