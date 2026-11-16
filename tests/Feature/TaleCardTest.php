<?php

namespace Tests\Feature;

use App\Models\Module;
use App\Models\PlayerCard;
use App\Models\Tale;
use App\Models\TaleCard;
use App\Support\CardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A Tale card is a card of the Tale's own — a story card, or one for the box's
 * mechanic — with a free-text type line rather than a card type.
 */
class TaleCardTest extends TestCase
{
    use RefreshDatabase;

    private Tale $tale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
        $this->tale = Tale::create(['slug' => 'drowned-coast', 'name' => 'The Drowned Coast', 'set_icon' => 'DC']);
    }

    private function add(array $data = []): TaleCard
    {
        $this->post('/tales/drowned-coast/tale-cards', $data + ['name' => 'The Lighthouse', 'type_line' => 'Story', 'text' => '{this} stands.', 'traits' => []])
            ->assertRedirect('/tales/drowned-coast?tab=cards');

        return TaleCard::latest('id')->firstOrFail();
    }

    public function test_a_tale_card_is_added_as_a_placeholder_and_names_itself(): void
    {
        $card = $this->add();

        $this->assertSame('the-lighthouse', $card->slug);
        $this->assertTrue($card->is_placeholder);

        $presented = CardPresenter::make()->taleCard($card->fresh('tale'));

        $this->assertSame('Story', $presented['type_line']);
        $this->assertStringContainsString('The Lighthouse stands.', $presented['html']);
        $this->assertSame('DC', $presented['set_icon']);
        $this->assertSame('The Drowned Coast', $presented['tale']);
    }

    public function test_a_tale_card_has_no_scenario_to_quote(): void
    {
        $card = $this->add(['name' => 'Rising Water', 'text' => '{dreadRule}']);

        $this->assertStringContainsString('?dreadRule', CardPresenter::make()->taleCard($card)['html']);
    }

    public function test_an_empty_type_line_is_null_so_the_face_prints_tale(): void
    {
        $card = $this->add(['name' => 'The Ship', 'type_line' => '']);

        $this->assertNull(CardPresenter::make()->taleCard($card)['type_line']);
    }

    public function test_a_name_already_taken_in_the_tale_gets_a_numbered_slug(): void
    {
        $this->add();
        $this->assertSame('the-lighthouse-2', $this->add()->slug);
    }

    public function test_a_tale_card_is_edited_and_deleted(): void
    {
        $card = $this->add();

        $this->put("/tale-cards/{$card->id}", ['name' => 'The Lighthouse', 'qty' => 2, 'text' => 'Lit.', 'traits' => ['Coast']])
            ->assertRedirect('/tales/drowned-coast?tab=cards');
        $this->assertSame(2, $card->fresh()->qty);
        $this->assertSame(['Coast'], $card->fresh()->traits);

        $this->delete("/tale-cards/{$card->id}")->assertRedirect('/tales/drowned-coast?tab=cards');
        $this->assertSame(0, TaleCard::count());
    }

    public function test_the_cards_tab_carries_all_three_kinds(): void
    {
        $this->add();
        Module::create(['slug' => 'tide-of-bones', 'name' => 'Tide of Bones', 'tale_id' => $this->tale->id]);
        PlayerCard::create([
            'tale_id' => $this->tale->id, 'slug' => 'charm', 'name' => 'Charm',
            'role' => 'reward', 'origin' => 'reward', 'type' => 'item',
        ]);

        $this->get('/tales/drowned-coast')->assertInertia(fn (Assert $page) => $page
            ->has('cards', 1)
            ->where('cards.0.name', 'The Lighthouse')
            ->has('rewards', 1)
            ->where('rewards.0.name', 'Charm')
            ->has('modules', 1)
            ->where('modules.0.slug', 'tide-of-bones')
        );
    }
}
