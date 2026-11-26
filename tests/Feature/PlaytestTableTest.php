<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Module;
use App\Models\SavedDeck;
use App\Models\Scenario;
use App\Support\DeckBuild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The playtest table is a goldfish table now: besides the entity deck it is
 * handed the board, the town and a deck for every seat. What it does with
 * them happens in the browser; what it is handed is checked here.
 */
class PlaytestTableTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importDesignWithoutDomains();
    }

    public function test_with_no_saved_deck_there_is_nobody_to_seat(): void
    {
        $this->get('/scenarios/kraken/play')->assertInertia(fn (Assert $page) => $page->has('decks', 0));
    }

    public function test_a_seat_is_a_deck_saved_from_the_deck_builder(): void
    {
        $slug = $this->characterSlugs()[0];
        $saved = SavedDeck::create(['name' => 'Test hand', 'build' => ['character' => $slug, 'domain' => null, 'take' => []]]);

        $this->get('/scenarios/kraken/play')->assertInertia(function (Assert $page) use ($saved, $slug) {
            $props = $page->toArray()['props'];

            $this->assertCount(1, $props['decks']);
            $deck = $props['decks'][0];

            $this->assertSame("saved:{$saved->id}", $deck['key']);
            $this->assertSame('Test hand', $deck['name']);
            $this->assertSame($slug, $deck['character']);

            // The build read the way the deck builder reads it.
            $build = DeckBuild::for(Character::where('slug', $slug)->firstOrFail(), null, []);
            $this->assertCount($build->deck()->count(), $deck['deck']);
            $this->assertCount($build->kit()->count(), $deck['kit']);
            $this->assertCount($build->upgrades()->count(), $deck['upgrades']);

            foreach ([...$deck['deck'], ...$deck['kit'], ...$deck['upgrades']] as $id) {
                $this->assertArrayHasKey($id, $props['playerCards']);
            }
        });
    }

    public function test_a_saved_deck_whose_character_is_gone_is_left_out(): void
    {
        SavedDeck::create(['name' => 'Orphan', 'build' => ['character' => 'nobody', 'domain' => null, 'take' => []]]);

        $this->get('/scenarios/kraken/play')->assertInertia(fn (Assert $page) => $this->assertNull(
            collect($page->toArray()['props']['decks'])->firstWhere('name', 'Orphan')
        ));
    }

    public function test_the_board_and_the_town_come_with_the_scenario(): void
    {
        $scenario = Scenario::where('slug', 'kraken')->withCount(['boardCards'])->firstOrFail();

        $this->get('/scenarios/kraken/play')->assertInertia(function (Assert $page) use ($scenario) {
            // The chosen modules' board cards join the scenario's own, the
            // same way their entity cards join the deck.
            $modules = Module::whereIn('slug', $page->toArray()['props']['chosen'])->withCount('boardCards')->get();

            $page->has('boardCards', $scenario->board_cards_count + $modules->sum('board_cards_count'))
                // Its own districts and the common ones it picks.
                ->has('town', $scenario->town()->count())
                ->has('config.shopPurchaseDestination')
                ->where('config.shopRowSize', 3)
                ->missing('config.omenRemovedPerBoardKill');
        });
    }

    public function test_a_module_board_card_says_whose_it_is(): void
    {
        // The table lays out setup's board cards as every one no beat claims,
        // minus a module's: those are created by a card, not put out at setup.
        // So each module board card has to arrive saying it is a module's, or
        // the Drowned is on the Kraken's board before anything summons it.
        $this->get('/scenarios/kraken/play?modules[]=what-lurks-below')->assertInertia(function (Assert $page) {
            $cards = collect($page->toArray()['props']['boardCards']);
            $drowned = $cards->firstWhere('name', 'Drowned');

            $this->assertNotNull($drowned, 'the chosen module\'s board card is offered to the table');
            $this->assertNotNull($drowned['module_id']);
            $this->assertNull($drowned['added_by_beat_id']);
            $this->assertTrue($cards->where('name', 'The Kraken')->every(fn ($c) => $c['module_id'] === null));
        });
    }
}
