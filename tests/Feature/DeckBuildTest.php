<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\Domain;
use App\Models\PlayerCard;
use App\Models\RulesConfig;
use App\Models\SavedDeck;
use App\Support\DeckBuild;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Deck building is where a character and a domain meet. Neither owns the other,
 * so the pairing lives in the query string and nothing about it is stored.
 */
class DeckBuildTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->importDesignWithoutDomains();
        $this->tide();
    }

    /** A pool of 32: 20 for a deck, 12 left to choose between. */
    private function tide(): Domain
    {
        $domain = Domain::create(['slug' => 'tide', 'name' => 'Tide', 'set_icon' => 'TD']);

        $domain->cards()->create([
            'slug' => 'undertow', 'name' => 'Undertow', 'qty' => 20,
            'role' => PlayerCard::ROLE_DOMAIN, 'origin' => 'domain', 'type' => 'action',
            'gold_cost' => 1, 'omen_icons' => 2, 'start_zone' => 'deck', 'sort' => 0,
        ]);

        $domain->cards()->create([
            'slug' => 'swell', 'name' => 'Swell', 'qty' => 12,
            'role' => PlayerCard::ROLE_DOMAIN, 'origin' => 'domain', 'type' => 'item',
            'gold_cost' => 0, 'omen_icons' => 1, 'start_zone' => 'deck', 'sort' => 1,
        ]);

        return $domain;
    }

    private function build(array $take = [], ?string $domain = 'tide'): DeckBuild
    {
        return DeckBuild::for(
            Character::where('slug', 'gunslinger')->first(),
            $domain === null ? null : Domain::where('slug', $domain)->first(),
            $take,
        );
    }

    public function test_a_deck_is_the_character_s_half_plus_what_is_taken_from_the_domain(): void
    {
        $stats = $this->build(['undertow' => 14, 'swell' => 6])->stats();

        $this->assertSame(20, $stats['signature_total']);
        $this->assertSame(20, $stats['domain_total']);
        $this->assertSame(40, $stats['deck_total']);
        $this->assertSame(40, $stats['deck_rule']);
        // The pool is untouched by what a deck takes out of it.
        $this->assertSame(32, $stats['pool_total']);
        $this->assertSame(12, $stats['pool_left']);
    }

    public function test_the_deck_holds_one_entry_per_copy_in_the_order_the_halves_come(): void
    {
        $deck = $this->build(['swell' => 2])->deck();

        $this->assertCount(22, $deck);
        // The character's half first, then the domain's.
        $this->assertSame('signature', $deck->first()->role);
        $this->assertSame('Swell', $deck->last()->name);
        $this->assertSame(2, $deck->where('name', 'Swell')->count());
    }

    public function test_a_full_deck_raises_nothing(): void
    {
        $this->assertSame([], $this->build(['undertow' => 20])->warnings());
    }

    public function test_it_says_how_many_are_still_to_pick(): void
    {
        $warnings = implode("\n", $this->build(['undertow' => 8])->warnings());

        $this->assertStringContainsString('8 domain cards taken, but a deck holds 20. 12 still to pick.', $warnings);
    }

    public function test_it_says_when_too_many_are_taken(): void
    {
        $warnings = implode("\n", $this->build(['undertow' => 20, 'swell' => 3])->warnings());

        $this->assertStringContainsString('23 domain cards taken, but a deck holds 20. 3 too many.', $warnings);
    }

    public function test_asking_for_more_copies_than_the_pool_prints_is_capped_and_reported(): void
    {
        $build = $this->build(['swell' => 40]);

        // Capped at what the pool holds, so the deck shown could be built.
        $this->assertSame(['swell' => 12], $build->taking());
        $this->assertSame(12, $build->stats()['domain_total']);
        $this->assertStringContainsString(
            '40 copies of Swell asked for, but the pool holds 12.',
            implode("\n", $build->warnings()),
        );
    }

    /** The cap is a tunable number, and it ships unset. */
    private function capCopiesAt(?int $max): void
    {
        RulesConfig::where('key', 'maxCopiesPerDomainCard')->update(['value' => ['v' => $max]]);
    }

    public function test_the_copy_cap_ships_unset_so_the_pool_is_the_only_limit(): void
    {
        // How many copies of one card a deck should carry is not settled, so
        // nothing is assumed until the designer sets the number.
        $build = $this->build(['undertow' => 14, 'swell' => 6]);

        $this->assertNull($build->maxCopies());
        $this->assertSame(14, $build->taking()['undertow']);
        $this->assertSame([], $build->warnings());
    }

    public function test_the_copy_cap_limits_how_many_of_one_domain_card_a_deck_takes(): void
    {
        $this->capCopiesAt(2);

        $build = $this->build(['undertow' => 4, 'swell' => 2]);

        // Capped, so the deck shown is one that could actually be built.
        $this->assertSame(2, $build->taking()['undertow']);
        $this->assertSame(4, $build->stats()['domain_total']);
        $this->assertSame(2, $build->stats()['max_copies']);

        // And said, naming the number that bit.
        $this->assertContains(
            '4 copies of Undertow asked for, but a deck takes at most 2 of one domain card.',
            $build->warnings()
        );
    }

    public function test_a_pool_tighter_than_the_cap_is_still_the_pool_s_limit(): void
    {
        $this->capCopiesAt(20);

        $build = $this->build(['swell' => 14]);

        // Swell is printed 12 times, so the pool is what the message names.
        $this->assertSame(12, $build->taking()['swell']);
        $this->assertContains(
            '14 copies of Swell asked for, but the pool holds 12.',
            $build->warnings()
        );
    }

    public function test_the_page_tells_each_pool_card_how_many_it_can_give(): void
    {
        $this->capCopiesAt(3);

        $this->get('/decks?character=gunslinger&domain=tide')->assertInertia(
            fn ($page) => $page
                ->where('pool.0.qty', 20)
                ->where('pool.0.limit', 3)
                ->where('stats.max_copies', 3)
        );
    }

    public function test_a_pool_too_small_for_a_deck_is_reported(): void
    {
        $ash = Domain::create(['slug' => 'ash', 'name' => 'Ash']);
        $ash->cards()->create([
            'slug' => 'cinder', 'name' => 'Cinder', 'qty' => 12,
            'role' => PlayerCard::ROLE_DOMAIN, 'origin' => 'domain', 'type' => 'action',
            'gold_cost' => 0, 'omen_icons' => 1, 'start_zone' => 'deck',
        ]);

        $warnings = implode("\n", $this->build(['cinder' => 12], 'ash')->warnings());

        $this->assertStringContainsString('Ash holds 12 cards in total, 8 short of the 20 a deck takes.', $warnings);
    }

    public function test_the_colourless_pool_is_flagged_when_it_cannot_fill_a_slot(): void
    {
        $tide = Domain::where('slug', 'tide')->firstOrFail();
        $tide->update(['is_neutral' => true]);
        // Flipping the domain's flag does not rewrite cards already there —
        // the deck maths reads each card's own origin, so the fixture has to
        // carry it too, the way an import through defaultOrigin() would.
        $tide->cards()->update(['origin' => 'neutral']);

        $this->assertSame([], $this->build(['undertow' => 20])->warnings());

        RulesConfig::where('key', 'neutralFillsDomainSlots')->firstOrFail()->update(['value' => ['v' => false]]);

        $build = $this->build(['undertow' => 20]);

        // Neutral cards that cannot fill a slot are not silently counted as if
        // they did.
        $this->assertSame(0, $build->stats()['domain_total']);
        $this->assertStringContainsString(
            'Neutral cards do not fill domain slots under the current rules',
            implode("\n", $build->warnings()),
        );
    }

    public function test_a_card_s_own_origin_decides_whether_it_fills_a_slot_not_the_domain_s_flag(): void
    {
        // A coloured domain (is_neutral is false) can still hold a card that
        // overrides its origin to neutral — Domain::defaultOrigin() only picks
        // what a new card starts as.
        $tide = Domain::where('slug', 'tide')->firstOrFail();
        PlayerCard::where('slug', 'swell')->update(['origin' => 'neutral']);

        RulesConfig::where('key', 'neutralFillsDomainSlots')->firstOrFail()->update(['value' => ['v' => false]]);

        $build = $this->build(['undertow' => 14, 'swell' => 6]);

        // Swell cannot be taken at all now; Undertow, still origin "domain", can.
        $this->assertSame(0, $build->limitFor(PlayerCard::where('slug', 'swell')->firstOrFail()));
        $this->assertSame(['undertow' => 14], $build->taking());
        $this->assertSame(14, $build->stats()['domain_total']);
        $this->assertStringContainsString(
            'Swell is neutral, and neutral cards do not fill domain slots',
            implode("\n", $build->warnings()),
        );

        // And the reverse: a colourless domain can hold a card that overrides
        // its origin back to "domain", which fills a slot regardless.
        $tide->update(['is_neutral' => true]);
        PlayerCard::where('slug', 'undertow')->update(['origin' => 'domain']);

        $build = $this->build(['undertow' => 20]);
        $this->assertSame(20, $build->stats()['domain_total']);
        $this->assertSame([], $build->warnings());
    }

    /** A colourless domain, separate from Tide, cards fill slots by default. */
    private function neutral(): Domain
    {
        $domain = Domain::create(['slug' => 'neutral', 'name' => 'Neutral', 'is_neutral' => true]);

        $domain->cards()->create([
            'slug' => 'gold-pouch', 'name' => 'Gold Pouch', 'qty' => 1,
            'role' => PlayerCard::ROLE_DOMAIN, 'origin' => 'neutral', 'type' => 'item',
            'gold_cost' => 1, 'omen_icons' => 1, 'start_zone' => 'deck',
        ]);

        return $domain;
    }

    public function test_the_colourless_pool_joins_whichever_domain_is_chosen(): void
    {
        $this->neutral();

        $build = $this->build(['undertow' => 19, 'gold-pouch' => 1]);

        // Gold Pouch is not one of Tide's own cards, but it is offered anyway.
        $this->assertTrue($build->pool()->contains('slug', 'gold-pouch'));
        $this->assertSame(20, $build->stats()['domain_total']);
        $this->assertSame([], $build->warnings());
        $this->assertTrue($build->hasNeutralPool());
    }

    public function test_picking_the_colourless_pool_itself_does_not_double_it_up(): void
    {
        $neutral = $this->neutral();

        $build = DeckBuild::for(
            Character::where('slug', 'gunslinger')->first(),
            $neutral,
            ['gold-pouch' => 1],
        );

        // Nothing else is neutral, so the pool is just Neutral's own card, once.
        $this->assertFalse($build->hasNeutralPool());
        $this->assertCount(1, $build->pool());
    }

    public function test_the_curves_cover_both_halves_together_and_apart(): void
    {
        $stats = $this->build(['swell' => 4])->stats();

        // Swell carries 1 omen each, on top of whatever the Gunslinger brings.
        $signature = collect($stats['omen_by_half']['signature'])->firstWhere('value', 1)['count'] ?? 0;
        $whole = collect($stats['omen_curve'])->firstWhere('value', 1)['count'] ?? 0;

        $this->assertSame($signature + 4, $whole);
        $this->assertSame(4, collect($stats['omen_by_half']['domain'])->firstWhere('value', 1)['count']);
    }

    public function test_kit_and_upgrades_stay_outside_the_deck(): void
    {
        $stats = $this->build(['undertow' => 20])->stats();

        $this->assertSame(40, $stats['deck_total']);
        // The Gunslinger's Revolver and its five upgrades.
        $this->assertSame(1, $stats['kit_total']);
        $this->assertSame(5, $stats['upgrade_total']);
    }

    public function test_a_domain_s_upgrades_are_set_aside_with_the_character_s(): void
    {
        Domain::where('slug', 'tide')->firstOrFail()->cards()->create([
            'slug' => 'riptide', 'name' => 'Riptide', 'qty' => 1,
            'role' => PlayerCard::ROLE_UPGRADE, 'origin' => 'domain', 'type' => 'action',
            'gold_cost' => 1, 'omen_icons' => 1, 'start_zone' => 'upgrade',
        ]);

        $this->assertSame(6, $this->build()->stats()['upgrade_total']);
    }

    public function test_the_page_builds_from_the_query_string_and_stores_nothing(): void
    {
        $this->get('/decks?character=gunslinger&domain=tide&take[undertow]=14&take[swell]=6')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Decks/Build')
                ->where('character.slug', 'gunslinger')
                ->where('domain.slug', 'tide')
                ->where('stats.deck_total', 40)
                ->where('take', ['undertow' => 14, 'swell' => 6])
                // Each pool card says how many this deck takes of it.
                ->where('pool.0.taken', 14)
                ->where('warnings', [])
            );

        // Nothing about the pairing was written down.
        $this->assertSame(0, Character::where('slug', 'gunslinger')->firstOrFail()->cards()->whereNotNull('domain_id')->count());
    }

    public function test_the_page_opens_with_neither_side_chosen(): void
    {
        $this->get('/decks')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Decks/Build')
                ->where('character', null)
                ->where('domain', null)
                ->has('characters', 2)
                ->has('domains', 1)
                ->where('pool', [])
            );
    }

    public function test_every_domain_is_offered_for_every_character(): void
    {
        Domain::create(['slug' => 'ash', 'name' => 'Ash']);

        $this->get('/decks?character=soothsayer')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->has('domains', 2));
    }

    public function test_a_built_deck_prints_every_copy(): void
    {
        $html = $this->get('/print/deck/sheet?character=gunslinger&domain=tide&take[undertow]=14&take[swell]=6&deck=player')
            ->assertOk()->getContent();

        $this->assertSame(40, substr_count($html, 'class="card player-card"'));
        // The domain half carries its pool badge; the character's half does not.
        $this->assertSame(20, substr_count($html, '<span class="set-icon">TD</span>'));
    }

    public function test_printing_everything_adds_the_character_card_and_the_extras(): void
    {
        $html = $this->get('/print/deck/sheet?character=gunslinger&domain=tide&take[undertow]=20&deck=all')
            ->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'class="card character-card"'));
        // 40 in the deck, plus the Revolver and five upgrades beside it.
        $this->assertSame(46, substr_count($html, 'class="card player-card"'));
    }

    public function test_the_print_page_carries_the_build_through(): void
    {
        $this->get('/print/deck?character=gunslinger&domain=tide&take[swell]=3')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Print/Options')
                ->where('kind', 'deck')
                ->where('scenario.name', 'Gunslinger — Tide')
                ->where('context.character', 'gunslinger')
                ->where('context.take', ['swell' => 3])
                ->where('counts.player', 23)
            );
    }

    public function test_a_deck_can_be_saved_under_a_name(): void
    {
        $this->post('/saved-decks', [
            'name' => 'Gunslinger / Tide',
            'character' => 'gunslinger',
            'domain' => 'tide',
            'take' => ['undertow' => 14, 'swell' => 6],
        ])->assertRedirect();

        $deck = SavedDeck::firstWhere('name', 'Gunslinger / Tide');

        $this->assertNotNull($deck);
        $this->assertSame('gunslinger', $deck->build['character']);
        $this->assertSame('tide', $deck->build['domain']);
        $this->assertSame(['undertow' => 14, 'swell' => 6], $deck->build['take']);
    }

    public function test_saving_under_a_name_already_in_use_overwrites_it(): void
    {
        $this->post('/saved-decks', ['name' => 'Main', 'character' => 'gunslinger', 'domain' => 'tide', 'take' => ['undertow' => 20]]);
        $this->post('/saved-decks', ['name' => 'Main', 'character' => 'gunslinger', 'domain' => 'tide', 'take' => ['swell' => 12]]);

        $this->assertSame(1, SavedDeck::where('name', 'Main')->count());
        $this->assertSame(['swell' => 12], SavedDeck::firstWhere('name', 'Main')->build['take']);
    }

    public function test_saved_decks_are_offered_on_the_builder_and_nothing_else_is_stored(): void
    {
        SavedDeck::create([
            'name' => 'Main',
            'build' => ['character' => 'gunslinger', 'domain' => 'tide', 'take' => ['undertow' => 20]],
        ]);

        $this->get('/decks')->assertInertia(
            fn ($page) => $page
                ->has('saved', 1)
                ->where('saved.0.name', 'Main')
                ->where('saved.0.build.character', 'gunslinger')
        );

        // Saving a deck is a shortcut back to a query string, not a second
        // place the character/domain pairing lives.
        $this->assertSame(0, Character::where('slug', 'gunslinger')->firstOrFail()->cards()->whereNotNull('domain_id')->count());
    }

    public function test_a_saved_deck_can_be_deleted(): void
    {
        $deck = SavedDeck::create([
            'name' => 'Main',
            'build' => ['character' => 'gunslinger', 'domain' => 'tide', 'take' => []],
        ]);

        $this->delete("/saved-decks/{$deck->id}")->assertRedirect();

        $this->assertNull(SavedDeck::find($deck->id));
    }
}
