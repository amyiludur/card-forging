<?php

namespace Tests\Feature;

use App\Models\CardType;
use App\Models\Character;
use App\Models\EntityCard;
use App\Models\Module;
use App\Models\Scenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A card type's colour, and a type one scenario owns.
 *
 * The colour fills the head band and the type line under it; the ownership
 * decides who may be typed with it. Both reach the printed card, which is the
 * only place either of them really matters.
 */
class CardTypeColourTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->artisan('design:import');
    }

    private function kraken(): Scenario
    {
        return Scenario::where('slug', 'kraken')->firstOrFail();
    }

    public function test_it_adds_a_type_to_the_shared_library(): void
    {
        $this->post('/rules/card-types', ['name' => 'Deep Tide', 'colour' => '#1E3A5F'])
            ->assertRedirect();

        $type = CardType::where('slug', 'deep-tide')->firstOrFail();

        $this->assertNull($type->scenario_id, 'a type added here belongs to nobody in particular');
        $this->assertSame('#1e3a5f', $type->colour);
    }

    public function test_a_scenario_can_own_a_type_of_its_own(): void
    {
        $this->post('/scenarios/kraken/card-types', ['name' => 'Tide', 'colour' => '#0f766e'])
            ->assertRedirect();

        $type = CardType::where('slug', 'tide')->firstOrFail();

        $this->assertSame($this->kraken()->id, $type->scenario_id);
    }

    public function test_a_scenarios_own_type_is_offered_to_its_cards_and_to_nobody_else(): void
    {
        $this->post('/scenarios/kraken/card-types', ['name' => 'Tide', 'colour' => '#0f766e']);

        $other = Scenario::create(['slug' => 'wendigo', 'name' => 'The Wendigo', 'entity_type' => 'creature']);
        $module = Module::first();

        $offered = fn (string $query) => collect(
            $this->get("/cards/create?{$query}")->assertOk()->viewData('page')['props']['cardTypes']
        )->pluck('slug');

        $this->assertContains('tide', $offered('scenario=kraken'));
        $this->assertContains('attack', $offered('scenario=kraken'), 'the shared library is still offered');

        $this->assertNotContains('tide', $offered("scenario={$other->slug}"));
        // A module is played with whichever scenario the table chose, so it
        // draws on the shared library alone.
        $this->assertNotContains('tide', $offered("module={$module->slug}"));
    }

    public function test_the_all_cards_filter_offers_every_type(): void
    {
        $this->post('/scenarios/kraken/card-types', ['name' => 'Tide']);

        $offered = fn (string $query) => collect(
            $this->get("/cards{$query}")->assertOk()->viewData('page')['props']['cardTypes']
        )->pluck('slug');

        // A card typed with it is in this list, so it has to be filterable here.
        $this->assertContains('tide', $offered(''));
        // Narrowed again once the list is one scenario's.
        $this->assertContains('tide', $offered('?scenario=kraken'));
    }

    public function test_a_card_cannot_be_typed_with_another_scenarios_type(): void
    {
        $this->post('/scenarios/kraken/card-types', ['name' => 'Tide']);
        $tide = CardType::where('slug', 'tide')->firstOrFail();

        $other = Scenario::create(['slug' => 'wendigo', 'name' => 'The Wendigo', 'entity_type' => 'creature']);

        $this->post("/cards?scenario={$other->slug}", [
            'name' => 'Cold Snap',
            'qty' => 1,
            'layout' => 'single',
            'omen_cost' => 2,
            'arrow' => 'top',
            'faces' => [['half' => 'single', 'card_type_id' => $tide->id, 'text' => 'Deal 1 damage.']],
        ])->assertSessionHasErrors('faces.0.card_type_id');
    }

    public function test_a_card_already_carrying_a_foreign_type_keeps_it(): void
    {
        // A design file can say anything; report, don't correct. The editor
        // offers the type it already has rather than dropping it on save.
        $this->post('/scenarios/kraken/card-types', ['name' => 'Tide']);
        $tide = CardType::where('slug', 'tide')->firstOrFail();

        $other = Scenario::create(['slug' => 'wendigo', 'name' => 'The Wendigo', 'entity_type' => 'creature']);
        $card = EntityCard::create(['scenario_id' => $other->id, 'name' => 'Cold Snap', 'qty' => 1, 'layout' => 'single', 'omen_cost' => 1, 'arrow' => 'top']);
        $card->faces()->create(['half' => 'single', 'card_type_id' => $tide->id, 'text' => 'Deal 1 damage.']);

        $offered = collect($this->get("/cards/{$card->id}/edit")->assertOk()->viewData('page')['props']['cardTypes']);

        $this->assertContains('tide', $offered->pluck('slug'));
        $this->assertSame('The Kraken', $offered->firstWhere('slug', 'tide')['foreign']);

        $this->put("/cards/{$card->id}", [
            'name' => 'Cold Snap',
            'qty' => 1,
            'layout' => 'single',
            'omen_cost' => 1,
            'arrow' => 'top',
            'faces' => [['half' => 'single', 'card_type_id' => $tide->id, 'text' => 'Deal 1 damage.']],
        ])->assertSessionHasNoErrors();

        $this->assertSame($tide->id, $card->faces()->first()->card_type_id);
    }

    public function test_a_slug_a_shared_type_already_answers_to_is_refused(): void
    {
        // Slugs are unique across the whole table: a design file's
        // "type": "attack" has to mean exactly one thing.
        $this->post('/scenarios/kraken/card-types', ['name' => 'Tide', 'slug' => 'attack'])
            ->assertSessionHasErrors('slug');
    }

    public function test_a_derived_slug_already_taken_is_numbered_and_said_so(): void
    {
        $this->post('/scenarios/kraken/card-types', ['name' => 'Attack'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('success', fn (string $note) => str_contains($note, '{attack-2}'));

        $this->assertSame(
            $this->kraken()->id,
            CardType::where('slug', 'attack-2')->firstOrFail()->scenario_id,
        );
    }

    public function test_a_type_with_a_name_already_taken_gets_a_slug_of_its_own(): void
    {
        $this->post('/rules/card-types', ['name' => 'Attack', 'slug' => 'attack-2'])
            ->assertSessionHasNoErrors();

        $this->assertSame(2, CardType::where('name', 'Attack')->count());
    }

    public function test_deleting_a_scenario_takes_its_own_types_with_it(): void
    {
        $this->post('/scenarios/kraken/card-types', ['name' => 'Tide']);

        $this->delete('/scenarios/kraken');

        $this->assertNull(CardType::where('slug', 'tide')->first());
        // And leaves the shared library exactly where it was.
        $this->assertNotNull(CardType::where('slug', 'attack')->first());
    }

    public function test_deleting_a_type_leaves_the_cards_that_used_it(): void
    {
        $attack = CardType::where('slug', 'attack')->firstOrFail();
        $faces = $attack->faces()->count();

        $this->assertGreaterThan(0, $faces);

        $this->delete("/rules/card-types/{$attack->id}")
            ->assertSessionHas('success', fn (string $note) => str_contains($note, "{$faces} card face"));

        // Report, don't correct: the cards are still there, simply untyped.
        $this->assertGreaterThan(0, EntityCard::count());
    }

    public function test_a_colour_that_is_not_a_colour_is_refused(): void
    {
        $this->post('/rules/card-types', ['name' => 'Tide', 'colour' => 'teal'])
            ->assertSessionHasErrors('colour');
    }

    public function test_the_printed_card_takes_its_head_from_its_type(): void
    {
        CardType::where('slug', 'attack')->update(['colour' => '#7f1d1d']);

        $html = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        $this->assertStringContainsString('background: #7f1d1d;', $html);
        // The head takes the light ink over a dark red, and the type line
        // under it takes the colour as picked, which already reads on cream.
        $this->assertStringContainsString('color: #fdfcf9;', $html);
        $this->assertStringContainsString('<div class="type" style="color: #7f1d1d"', $html);
    }

    public function test_a_pale_type_flips_the_head_ink_and_darkens_the_type_line(): void
    {
        CardType::where('slug', 'attack')->update(['colour' => '#fde68a']);

        $html = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        $this->assertStringContainsString('background: #fde68a; color: #1c1917;', $html);
        // The colour as picked would be unreadable in six-point capitals on
        // cream, so the type line gets a darker version of the same hue.
        $this->assertStringNotContainsString('<div class="type" style="color: #fde68a"', $html);
        $this->assertStringContainsString('<div class="type" style="color: #746a3f"', $html);
    }

    public function test_a_split_card_prints_both_of_its_types(): void
    {
        CardType::where('slug', 'attack')->update(['colour' => '#7f1d1d']);
        CardType::where('slug', 'hazard')->update(['colour' => '#1e3a5f']);

        $card = EntityCard::where('layout', 'split')->with('faces')->firstOrFail();
        $types = CardType::whereIn('slug', ['attack', 'hazard'])->pluck('id', 'slug');

        $card->faces->each(fn ($face, $i) => $face->update([
            'card_type_id' => $i === 0 ? $types['attack'] : $types['hazard'],
        ]));

        $html = $this->get("/print/kraken/sheet?deck=entity&only=entity:{$card->id}")->assertOk()->getContent();

        // Top half's colour at the top, the way the halves sit.
        $this->assertStringContainsString('linear-gradient(to bottom, #7f1d1d, #1e3a5f)', $html);
        // Two stops that disagree about their ink get a halo behind the name.
        $this->assertStringContainsString('text-shadow: 0 0 0.6mm', $html);
    }

    public function test_an_uncoloured_type_prints_the_head_it_always_printed(): void
    {
        $html = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        // Nothing inline at all: the sheet's own CSS is what makes it dark.
        $this->assertStringNotContainsString('<div class="card-head" style', $html);
    }

    public function test_a_character_prints_its_two_colours_as_a_gradient(): void
    {
        Character::where('slug', 'gunslinger')->update([
            'colour' => '#3f2b56',
            'colour_secondary' => '#7c3aed',
        ]);

        $html = $this->get('/print/character/gunslinger/sheet?deck=character')->assertOk()->getContent();

        $this->assertStringContainsString('linear-gradient(135deg, #3f2b56, #7c3aed)', $html);
    }

    public function test_a_character_with_one_colour_prints_a_flat_band(): void
    {
        Character::where('slug', 'gunslinger')->update(['colour' => '#3f2b56']);

        $html = $this->get('/print/character/gunslinger/sheet?deck=character')->assertOk()->getContent();

        $this->assertStringContainsString('background: #3f2b56; color: #fdfcf9;', $html);
        $this->assertStringNotContainsString('linear-gradient(135deg', $html);
    }

    public function test_the_character_editor_loads_the_colours_it_will_save(): void
    {
        Character::where('slug', 'gunslinger')->update(['colour' => '#3f2b56', 'colour_secondary' => '#b45309']);

        $this->get('/characters/gunslinger/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('character.colour', '#3f2b56')
                ->where('character.colour_secondary', '#b45309')
            );
    }

    public function test_the_character_editor_saves_both_colours(): void
    {
        $character = Character::where('slug', 'gunslinger')->firstOrFail();

        $this->put("/characters/{$character->slug}", [
            'name' => $character->name,
            'health' => $character->health,
            'hand_size' => $character->hand_size,
            'gold_per_round' => $character->gold_per_round,
            'colour' => '#3F2B56',
            'colour_secondary' => '#b45309',
        ])->assertSessionHasNoErrors();

        $this->assertSame('#3f2b56', $character->fresh()->colour);
        $this->assertSame('#b45309', $character->fresh()->colour_secondary);
    }

    public function test_the_character_editor_refuses_a_colour_that_is_not_one(): void
    {
        $character = Character::where('slug', 'gunslinger')->firstOrFail();

        $this->put("/characters/{$character->slug}", [
            'name' => $character->name,
            'health' => $character->health,
            'hand_size' => $character->hand_size,
            'gold_per_round' => $character->gold_per_round,
            'colour' => 'purple',
        ])->assertSessionHasErrors('colour');
    }
}
