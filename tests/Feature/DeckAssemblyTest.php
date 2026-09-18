<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\Scenario;
use App\Support\DeckAssembly;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeckAssemblyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    private function assembly(array $modules): DeckAssembly
    {
        return DeckAssembly::for(Scenario::where('slug', 'kraken')->with('entityCards.faces.cardType')->firstOrFail(), $modules);
    }

    public function test_the_base_deck_alone_is_the_scenarios_own_cards(): void
    {
        $stats = $this->assembly([])->stats();

        // 34 printed cards, of which 10 are shuffled in later by beats.
        $this->assertSame(34, $stats['total']);
        $this->assertSame(24, $stats['starting_total']);
        $this->assertSame(10, $stats['beat_total']);
    }

    public function test_a_module_adds_its_cards_to_the_starting_deck(): void
    {
        $stats = $this->assembly(['what-lurks-below'])->stats();

        $this->assertSame(32, $stats['starting_total']); // 24 + 8
        $this->assertSame(42, $stats['total']);
    }

    public function test_two_modules_stack(): void
    {
        $stats = $this->assembly(['what-lurks-below', 'that-which-comes-from-the-sky'])->stats();

        $this->assertSame(40, $stats['starting_total']);
        $this->assertSame(50, $stats['total']);
    }

    public function test_it_reports_where_every_card_came_from(): void
    {
        $sources = $this->assembly(['what-lurks-below'])->stats()['by_source'];

        $this->assertSame('The Kraken', $sources[0]['name']);
        $this->assertSame(34, $sources[0]['count']);
        $this->assertSame('What Lurks Below', $sources[1]['name']);
        $this->assertSame('WLB', $sources[1]['set_icon']);
        $this->assertSame(8, $sources[1]['count']);
    }

    public function test_the_arrow_split_counts_only_the_starting_deck(): void
    {
        $arrows = $this->assembly([])->stats()['arrows'];

        $this->assertSame(24, $arrows['top'] + $arrows['bottom']);
    }

    public function test_the_omen_curve_is_sorted_and_keeps_x_last(): void
    {
        // The Kraken's only X-cost card is shuffled in by beat 2, so it is not
        // in the starting deck. Put it there to check the ordering.
        \App\Models\EntityCard::where('name', 'Grasping Depths')->firstOrFail()
            ->update(['added_by_beat_id' => null]);

        $costs = array_column($this->assembly([])->stats()['omen_curve'], 'cost');

        // Cost 4 (The Abyss) also arrives with a beat, so the starting deck runs 1 to 3.
        $this->assertSame(['1', '2', '3', 'X'], $costs);
    }

    public function test_the_omen_curve_covers_only_the_starting_deck(): void
    {
        $costs = array_column($this->assembly([])->stats()['omen_curve'], 'cost');

        // Grasping Depths arrives with beat 2, so no X until then.
        $this->assertNotContains('X', $costs);
    }

    public function test_it_knows_when_the_module_count_matches_the_scenario(): void
    {
        $this->assertFalse($this->assembly(['what-lurks-below'])->countMatchesRules());
        $this->assertTrue($this->assembly(['what-lurks-below', 'that-which-comes-from-the-sky'])->countMatchesRules());
    }

    public function test_it_warns_about_an_incompatible_module_without_refusing_it(): void
    {
        $module = Module::where('slug', 'what-lurks-below')->firstOrFail();
        $module->update(['compatible_scenarios' => ['pale-fever']]);

        $assembly = $this->assembly(['what-lurks-below']);

        $this->assertSame(['What Lurks Below'], $assembly->incompatible()->pluck('name')->all());
        // Still counted in the deck: it is a warning, not a rule.
        $this->assertSame(32, $assembly->stats()['starting_total']);
    }

    public function test_the_assembly_page_defaults_to_the_recommended_modules(): void
    {
        $this->get('/scenarios/kraken/deck')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Scenarios/Deck')
                ->where('chosen', ['what-lurks-below'])
                ->where('countMatchesRules', false)
                ->where('stats.starting_total', 32)
                ->has('available', 2)
            );
    }

    public function test_the_assembly_page_honours_an_explicit_choice(): void
    {
        $this->get('/scenarios/kraken/deck?modules[]=what-lurks-below&modules[]=that-which-comes-from-the-sky')
            ->assertInertia(fn ($page) => $page
                ->where('countMatchesRules', true)
                ->where('stats.starting_total', 40)
            );
    }

    public function test_the_storyline_page_draws_a_row_and_resolves_it(): void
    {
        $this->get('/scenarios/kraken/storyline?size=4')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Scenarios/Storyline')
                ->has('row', 4)
                ->where('row.0.deciding_arrow', fn ($a) => in_array($a, ['top', 'bottom'], true))
            );
    }

    public function test_the_storyline_page_accepts_an_explicit_row(): void
    {
        $scenario = Scenario::where('slug', 'kraken')->firstOrFail();
        $single = $scenario->entityCards()->where('layout', 'single')->whereNull('added_by_beat_id')->firstOrFail();
        $split = $scenario->entityCards()->where('layout', 'split')->whereNull('added_by_beat_id')->firstOrFail();

        $single->update(['arrow' => 'bottom']);

        $this->get("/scenarios/kraken/storyline?cards[]={$single->id}&cards[]={$split->id}")
            ->assertInertia(fn ($page) => $page
                ->has('row', 2)
                ->where('row.1.resolves', 'bottom')
                ->where('row.1.is_split', true)
            );
    }

    public function test_a_flip_in_the_storyline_changes_the_next_card(): void
    {
        $scenario = Scenario::where('slug', 'kraken')->firstOrFail();
        $single = $scenario->entityCards()->where('layout', 'single')->whereNull('added_by_beat_id')->firstOrFail();
        $split = $scenario->entityCards()->where('layout', 'split')->whereNull('added_by_beat_id')->firstOrFail();

        $single->update(['arrow' => 'bottom']);

        $this->get("/scenarios/kraken/storyline?cards[]={$single->id}&cards[]={$split->id}&flip[]=0")
            ->assertInertia(fn ($page) => $page->where('row.1.resolves', 'top'));
    }
}
