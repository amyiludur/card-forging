<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Scenario;
use App\Models\Tale;
use App\Support\DeckAssembly;
use App\Support\Pocket;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A Tale's entity-side cards are a module the Tale owns. It only comes out when
 * the Tale is played, so no list that offers a module to ordinary play may
 * show it — and naming it outright still assembles it, which Part 2 relies on.
 */
class TaleModuleTest extends TestCase
{
    use RefreshDatabase;

    private Tale $tale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
        $this->tale = Tale::create(['slug' => 'drowned-coast', 'name' => 'The Drowned Coast']);

        $this->post('/modules', [
            'name' => 'Tide of Bones',
            'tale_id' => $this->tale->id,
            'compatible_scenarios' => [],
            'traits' => [],
        ])->assertRedirect('/modules/tide-of-bones');
    }

    private function slugs(array $rows): array
    {
        return collect($rows)->pluck('slug')->all();
    }

    public function test_a_module_can_be_made_for_a_tale(): void
    {
        $this->assertSame($this->tale->id, Module::where('slug', 'tide-of-bones')->value('tale_id'));

        $this->get('/modules/tide-of-bones')->assertInertia(fn (Assert $page) => $page
            ->where('tale.slug', 'drowned-coast')
            ->where('tale.name', 'The Drowned Coast')
        );

        $this->get('/modules/what-lurks-below')->assertInertia(fn (Assert $page) => $page->where('tale', null));
    }

    public function test_the_create_form_is_prefilled_from_the_tale_it_was_opened_from(): void
    {
        $this->get('/modules/create?tale=drowned-coast')->assertInertia(fn (Assert $page) => $page
            ->where('tale.id', $this->tale->id)
        );
    }

    public function test_a_tales_module_is_offered_nowhere_in_ordinary_play(): void
    {
        $lists = [];

        $this->get('/scenarios/create')->assertInertia(function (Assert $page) use (&$lists) {
            $lists['scenario create'] = $this->slugs($page->toArray()['props']['modules']);
        });
        $this->get('/scenarios/kraken/edit')->assertInertia(function (Assert $page) use (&$lists) {
            $lists['scenario edit'] = $this->slugs($page->toArray()['props']['modules']);
        });

        foreach (['deck', 'play'] as $page) {
            $this->get("/scenarios/kraken/{$page}")->assertInertia(function (Assert $p) use (&$lists, $page) {
                $props = $p->toArray()['props'];
                $lists["{$page} available"] = $this->slugs($props['available']);
                $lists["{$page} others"] = $this->slugs($props['others']);
                $lists['nav'] = $this->slugs($props['nav']['modules']);
            });
        }

        $this->get('/modules')->assertInertia(function (Assert $page) use (&$lists) {
            $lists['modules index'] = $this->slugs($page->toArray()['props']['modules']);
        });

        $lists['compatible'] = Scenario::where('slug', 'kraken')->first()->compatibleModules()->pluck('slug')->all();

        foreach ($lists as $where => $slugs) {
            $this->assertNotContains('tide-of-bones', $slugs, "A Tale's module is offered on {$where}.");
        }

        $this->assertContains('what-lurks-below', $lists['deck available']);

        $sections = collect(Pocket::make()->data()['sections'])->pluck('id');
        $this->assertNotContains('module-tide-of-bones', $sections);
        $this->assertContains('module-what-lurks-below', $sections);
    }

    public function test_a_tales_module_still_assembles_when_named(): void
    {
        $assembly = DeckAssembly::for(Scenario::where('slug', 'kraken')->first(), ['tide-of-bones']);

        $this->assertSame(['tide-of-bones'], $assembly->modules->pluck('slug')->all());
    }
}
