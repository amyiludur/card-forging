<?php

namespace Tests\Feature;

use App\Models\EntityCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * The card editor's back button returns to the page the card was opened from,
 * through create, save, duplicate and delete — not always to the card list.
 */
class EntityCardBackLinkTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    private function card(): EntityCard
    {
        return EntityCard::where('name', 'Salt Wind')->with('faces')->firstOrFail();
    }

    private function payload(EntityCard $card, array $extra = []): array
    {
        return $extra + [
            'name' => $card->name,
            'qty' => $card->qty,
            'layout' => $card->layout,
            'omen_cost' => $card->omen_cost,
            'omen_is_x' => (bool) $card->omen_is_x,
            'traits' => $card->traits ?? [],
            'arrow' => $card->arrow,
            'is_placeholder' => true,
            'faces' => $card->faces->map(fn ($f) => $f->only('half', 'card_type_id', 'text'))->all(),
        ];
    }

    public function test_the_page_it_was_opened_from_is_where_back_goes(): void
    {
        $card = $this->card();

        $this->withHeader('Referer', url('/scenarios/kraken?tab=deck'))
            ->get("/cards/{$card->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('back', '/scenarios/kraken?tab=deck'));
    }

    public function test_a_back_in_the_query_wins_over_the_referer(): void
    {
        $card = $this->card();

        $this->withHeader('Referer', url('/scenarios/kraken'))
            ->get("/cards/{$card->id}/edit?back=".urlencode('/cards?scenario=kraken&type=attack'))
            ->assertInertia(fn (Assert $page) => $page->where('back', '/cards?scenario=kraken&type=attack'));
    }

    public function test_no_page_to_go_back_to_leaves_it_empty(): void
    {
        $card = $this->card();

        $this->get("/cards/{$card->id}/edit")->assertInertia(fn (Assert $page) => $page->where('back', null));

        // The editor itself is never the page to go back to.
        $this->withHeader('Referer', url("/cards/{$card->id}/edit"))
            ->get("/cards/{$card->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('back', null));

        // Nor is another site, or anything that is not a path on this one.
        $this->withHeader('Referer', 'https://example.com/scenarios/kraken')
            ->get("/cards/{$card->id}/edit")
            ->assertInertia(fn (Assert $page) => $page->where('back', null));

        $this->get("/cards/{$card->id}/edit?back=".urlencode('//example.com/x'))
            ->assertInertia(fn (Assert $page) => $page->where('back', null));
    }

    public function test_saving_keeps_the_page_to_go_back_to(): void
    {
        $card = $this->card();

        $this->put("/cards/{$card->id}", $this->payload($card, ['back' => '/modules']))
            ->assertRedirect("/cards/{$card->id}/edit?back=".urlencode('/modules'));
    }

    public function test_creating_keeps_the_page_to_go_back_to(): void
    {
        $response = $this->post('/cards?scenario=kraken', $this->payload($this->card(), [
            'name' => 'Undertow',
            'back' => '/scenarios/kraken',
        ]));

        $created = EntityCard::where('name', 'Undertow')->firstOrFail();
        $response->assertRedirect("/cards/{$created->id}/edit?back=".urlencode('/scenarios/kraken'));
    }

    public function test_duplicating_keeps_the_page_to_go_back_to(): void
    {
        $card = $this->card();

        $response = $this->post("/cards/{$card->id}/duplicate", ['back' => '/scenarios/kraken']);

        $copy = EntityCard::where('name', 'Salt Wind (copy)')->firstOrFail();
        $response->assertRedirect("/cards/{$copy->id}/edit?back=".urlencode('/scenarios/kraken'));
    }

    public function test_deleting_goes_back_to_that_page(): void
    {
        $card = $this->card();

        $this->delete("/cards/{$card->id}", ['back' => '/scenarios/kraken'])->assertRedirect('/scenarios/kraken');
    }
}
