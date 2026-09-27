<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\Character;
use App\Models\EntityCard;
use App\Models\PlayerCard;
use App\Models\PrintPoolItem;
use App\Models\Scenario;
use App\Models\StoryBeat;
use App\Models\TownAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PrintPoolTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    private function add(string ...$keys): void
    {
        $this->post('/print-pool', ['items' => array_map(fn ($key) => ['key' => $key], $keys)])
            ->assertRedirect();
    }

    public function test_the_pool_page_offers_every_printable_card_there_is(): void
    {
        $this->get('/print/pool')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Options')
                ->where('kind', 'pool')
                ->where('options.deck', 'all')
                ->where('items', [])
                ->has('catalogue', EntityCard::count() + BoardCard::count() + PlayerCard::count()
                    + Character::count()
                    + StoryBeat::count() + TownAction::count()
                    + Scenario::all()->filter->hasSetup()->count())
            );
    }

    public function test_cards_from_different_places_print_on_one_sheet(): void
    {
        $lash = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();
        $player = PlayerCard::whereNotNull('character_id')->firstOrFail();
        $character = Character::firstOrFail();

        $this->add('entity:'.$lash->id, 'player:'.$player->id, 'character:'.$character->id);

        $html = $this->get('/print/pool/sheet')->assertOk()->getContent();

        // Tentacle Lash prints its own three copies, the others one each.
        $this->assertSame(3, substr_count($html, 'Tentacle Lash'));
        $this->assertStringContainsString('character-card', $html);
        $this->assertStringContainsString('player-card', $html);
        $this->assertStringContainsString(($lash->qty + $player->qty + 1).' cards', $html);
    }

    public function test_a_deck_pages_own_group_names_add_the_same_player_card(): void
    {
        $upgrade = PlayerCard::where('role', PlayerCard::ROLE_UPGRADE)->firstOrFail();

        $this->add('upgrade:'.$upgrade->id);
        $this->add('extras:'.$upgrade->id);

        $this->assertSame(1, PrintPoolItem::count());
        $this->assertSame('player:'.$upgrade->id, PrintPoolItem::first()->key());
    }

    public function test_a_key_naming_nothing_adds_nothing(): void
    {
        $this->add('entity:999999', 'nonsense:1', 'not a key');

        $this->assertSame(0, PrintPoolItem::count());
    }

    public function test_the_pool_keeps_its_own_count_and_can_drop_back_to_the_cards(): void
    {
        $lash = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();
        $this->add('entity:'.$lash->id);
        $row = PrintPoolItem::firstOrFail();

        $this->put('/print-pool/'.$row->id, ['qty' => 5])->assertRedirect();
        $this->assertSame(5, substr_count($this->get('/print/pool/sheet')->getContent(), 'Tentacle Lash'));

        $this->put('/print-pool/'.$row->id, ['qty' => null])->assertRedirect();
        $this->assertSame($lash->qty, substr_count($this->get('/print/pool/sheet')->getContent(), 'Tentacle Lash'));
    }

    public function test_a_card_deleted_since_it_was_added_leaves_the_pool(): void
    {
        $board = BoardCard::firstOrFail();
        $this->add('board:'.$board->id);

        $board->delete();

        $this->get('/print/pool')->assertOk()->assertInertia(fn ($page) => $page->where('items', []));
        $this->assertSame(0, PrintPoolItem::count());
    }

    public function test_cards_can_be_taken_out_one_at_a_time_or_all_at_once(): void
    {
        $cards = EntityCard::take(3)->get();
        $this->add(...$cards->map(fn ($card) => 'entity:'.$card->id)->all());

        $this->delete('/print-pool/'.PrintPoolItem::first()->id)->assertRedirect();
        $this->assertSame(2, PrintPoolItem::count());

        $this->delete('/print-pool')->assertRedirect();
        $this->assertSame(0, PrintPoolItem::count());
    }

    public function test_every_print_page_says_what_is_already_in_the_pool(): void
    {
        $lash = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();
        $this->add('entity:'.$lash->id);

        $this->get('/print/kraken')
            ->assertInertia(fn ($page) => $page->where('poolKeys', ['entity:'.$lash->id]));
    }

    public function test_every_page_shares_the_pool_keys_so_a_zoomed_card_knows_it_is_in(): void
    {
        $lash = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();
        $beat = StoryBeat::firstOrFail();

        $this->add('entity:'.$lash->id, 'beats:'.$beat->id);

        $this->get('/cards/'.$lash->id.'/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('nav.printPool', 2)
                ->where('nav.printPoolKeys', fn ($keys) => collect($keys)->sort()->values()->all()
                    === collect(['entity:'.$lash->id, 'beats:'.$beat->id])->sort()->values()->all())
            );
    }

    public function test_an_offset_leaves_slots_empty_and_prints_every_card(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=entity&offset=3')->assertOk()->getContent();

        // Every card still prints: the offset moves the run, it never shortens it.
        $this->assertStringContainsString('34 cards', $html);
        $this->assertSame(34, substr_count($html, 'class="arrow-edge'));
        $this->assertStringContainsString('first 3 cells left blank', $html);
        $this->assertStringNotContainsString('already printed', $html);
    }

    public function test_an_offset_is_laid_out_as_empty_cells_before_the_first_card(): void
    {
        $lash = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();
        $this->add('entity:'.$lash->id);

        $html = $this->get('/print/pool/sheet?offset=2')->getContent();

        $this->assertSame($lash->qty, substr_count($html, 'Tentacle Lash'));
        $firstPage = substr($html, strpos($html, '<div class="grid">'));
        $this->assertMatchesRegularExpression(
            '/^<div class="grid">\s*<div class="cell">\s*<\/div>\s*<div class="cell">\s*<\/div>\s*<div class="cell">\s*\S/',
            $firstPage
        );
    }

    public function test_an_offset_adds_to_skip_and_both_leave_one_cell(): void
    {
        $this->get('/print/kraken?skip=1&offset=2')
            ->assertInertia(fn ($page) => $page
                ->where('layout.skipped', 3)
                ->where('options.skip', 1)
                ->where('selection.offset', 2));

        $this->get('/print/kraken?columns=2&rows=2&offset=50')
            ->assertInertia(fn ($page) => $page->where('layout.skipped', 3)->where('layout.first_page', 1));
    }
}
