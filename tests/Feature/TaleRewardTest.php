<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\PlayerCard;
use App\Models\Tale;
use App\Support\CardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * A reward is a player card the Tale owns. A player card belongs to a
 * character, a domain or a Tale — exactly one — and an upgrade pair stays
 * inside whichever it is.
 */
class TaleRewardTest extends TestCase
{
    use RefreshDatabase;

    private Tale $tale;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
        $this->tale = Tale::create(['slug' => 'drowned-coast', 'name' => 'The Drowned Coast', 'set_icon' => 'DC']);
    }

    private function reward(array $overrides = []): array
    {
        return $overrides + [
            'name' => 'Sea-glass Charm', 'qty' => 1, 'role' => 'reward', 'origin' => 'reward',
            'type' => 'item', 'start_zone' => 'deck', 'gold_cost' => 0, 'omen_icons' => 0,
            'traits' => [], 'keywords' => [],
        ];
    }

    public function test_a_reward_card_is_made_on_a_tale(): void
    {
        $this->post('/tales/drowned-coast/cards', $this->reward())->assertRedirect();

        $card = PlayerCard::where('slug', 'sea-glass-charm')->firstOrFail();

        $this->assertSame($this->tale->id, $card->tale_id);
        $this->assertNull($card->character_id);
        $this->assertNull($card->domain_id);
        $this->assertTrue($card->owner()->is($this->tale));
        $this->assertSame(1, $this->tale->rewardCards()->count());
    }

    public function test_a_reward_takes_the_tales_set_icon_as_its_badge(): void
    {
        $this->post('/tales/drowned-coast/cards', $this->reward());

        $presented = CardPresenter::make()->playerCard(PlayerCard::where('slug', 'sea-glass-charm')->first());

        $this->assertSame('DC', $presented['set_icon']);
        $this->assertSame('The Drowned Coast', $presented['tale']);
        $this->assertSame('drowned-coast', $presented['tale_slug']);
    }

    public function test_an_upgrade_pair_stays_inside_the_tale(): void
    {
        $gunslinger = Character::where('slug', 'gunslinger')->firstOrFail();
        $stranger = $gunslinger->cards()->create([
            'slug' => 'sea-glass-charm', 'name' => 'Stranger', 'role' => 'signature', 'origin' => 'signature',
            'type' => 'action', 'upgrades_to' => 'x', 'upgrade_of' => 'y',
        ]);

        $this->post('/tales/drowned-coast/cards', $this->reward(['name' => 'Polished Charm', 'role' => 'upgrade', 'start_zone' => 'upgrade']));
        $this->post('/tales/drowned-coast/cards', $this->reward(['upgrades_to' => 'polished-charm']))->assertSessionHasNoErrors();

        $reward = PlayerCard::where('tale_id', $this->tale->id)->where('slug', 'sea-glass-charm')->firstOrFail();
        $upgrade = PlayerCard::where('tale_id', $this->tale->id)->where('slug', 'polished-charm')->firstOrFail();

        $this->assertSame('sea-glass-charm', $upgrade->upgrade_of, 'the other end of the pair learns of it');
        $this->assertSame(['polished-charm'], $reward->siblings()->pluck('slug')->all());
        $this->assertSame('x', $stranger->fresh()->upgrades_to);
        $this->assertSame('y', $stranger->fresh()->upgrade_of);
    }

    public function test_a_tale_offers_only_its_two_roles(): void
    {
        $this->get('/tales/drowned-coast/cards/create')->assertInertia(fn (Assert $page) => $page
            ->component('PlayerCards/Form')
            ->where('owner.kind', 'tale')
            ->where('options.roles', ['reward', 'upgrade'])
            ->where('options.origins', ['reward'])
            ->where('options.defaultOrigin', 'reward')
        );

        $this->post('/tales/drowned-coast/cards', $this->reward(['role' => 'signature']))
            ->assertSessionHasErrors('role');
    }

    public function test_a_reward_is_edited_through_the_one_player_card_editor(): void
    {
        $this->post('/tales/drowned-coast/cards', $this->reward());
        $card = PlayerCard::where('slug', 'sea-glass-charm')->first();

        $this->get("/player-cards/{$card->id}/edit")->assertInertia(fn (Assert $page) => $page
            ->where('owner.kind', 'tale')
            ->where('owner.slug', 'drowned-coast')
        );

        $this->put("/player-cards/{$card->id}", $this->reward(['name' => 'Sea-glass Charm', 'text' => 'Draw a card.']))
            ->assertRedirect('/tales/drowned-coast?tab=cards');

        $this->assertSame('Draw a card.', $card->fresh()->text);
    }
}
