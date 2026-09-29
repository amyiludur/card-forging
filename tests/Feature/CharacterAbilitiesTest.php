<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Support\CardPresenter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * A character card can carry abilities beyond its identity one. They print
 * under it in the order written, and the design file grows the key only once
 * there is one, so a character with the one ability keeps its file shape.
 */
class CharacterAbilitiesTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/abilities-'.uniqid());
        $this->artisan('design:import');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function gunslinger(): Character
    {
        return Character::where('slug', 'gunslinger')->firstOrFail();
    }

    private function payload(array $extra): array
    {
        return [
            'name' => 'Gunslinger',
            'slug' => 'gunslinger',
            'health' => 10,
            'hand_size' => 5,
            'gold_per_round' => 4,
            'ability_name' => 'Deadeye',
            'ability_text' => 'Once per round, draw the bottom card of your deck.',
            'is_placeholder' => true,
            ...$extra,
        ];
    }

    private function exportedGunslinger(): array
    {
        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        return json_decode(file_get_contents("{$this->path}/players/gunslinger.json"), true, 512, JSON_THROW_ON_ERROR);
    }

    public function test_extra_abilities_are_saved_in_order_and_empty_rows_are_dropped(): void
    {
        $this->put('/characters/gunslinger', $this->payload([
            'extra_abilities' => [
                ['name' => 'Quickdraw', 'text' => 'Draw a card.'],
                ['name' => '', 'text' => ''],
                ['name' => '', 'text' => 'Gain 1 {gold}.'],
            ],
        ]))->assertRedirect('/characters/gunslinger')->assertSessionHasNoErrors();

        $this->assertSame([
            ['name' => 'Quickdraw', 'text' => 'Draw a card.'],
            ['name' => null, 'text' => 'Gain 1 {gold}.'],
        ], $this->gunslinger()->extraAbilities());
    }

    public function test_no_extra_abilities_is_null_not_an_empty_list(): void
    {
        $this->put('/characters/gunslinger', $this->payload([
            'extra_abilities' => [['name' => '', 'text' => '']],
        ]))->assertRedirect('/characters/gunslinger');

        $this->assertNull($this->gunslinger()->extra_abilities);
    }

    public function test_a_character_with_one_ability_writes_no_extra_key(): void
    {
        $this->assertArrayNotHasKey('extraAbilities', $this->exportedGunslinger());
    }

    public function test_extra_abilities_go_through_the_design_folder_and_back(): void
    {
        $abilities = [
            ['name' => 'Quickdraw', 'text' => 'Draw a card.'],
            ['name' => 'Last Stand', 'text' => "When {this} would fall, heal 2.\n\nOnce per game."],
        ];
        $this->gunslinger()->update(['extra_abilities' => $abilities]);

        $file = $this->exportedGunslinger();
        $this->assertSame($abilities, $file['extraAbilities']);
        // Beside the first ability, not somewhere else in the file.
        $keys = array_keys($file);
        $this->assertSame(array_search('ability', $keys) + 1, array_search('extraAbilities', $keys));

        Character::query()->update(['extra_abilities' => null]);
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $this->assertSame($abilities, $this->gunslinger()->extraAbilities());
    }

    public function test_the_card_renders_each_extra_ability_through_the_markup(): void
    {
        $this->gunslinger()->update(['extra_abilities' => [
            ['name' => 'Last Stand', 'text' => 'When {this} would fall, heal 2.'],
        ]]);

        $card = CardPresenter::make()->character($this->gunslinger());

        $this->assertSame('Last Stand', $card['extra_abilities'][0]['name']);
        $this->assertStringContainsString('When Gunslinger would fall', $card['extra_abilities'][0]['html']);

        $this->get('/print/character/gunslinger/sheet?deck=character')
            ->assertOk()
            ->assertSee('Last Stand')
            ->assertSee('When Gunslinger would fall', false);
    }

    public function test_the_edit_form_is_given_the_extra_abilities(): void
    {
        $this->gunslinger()->update(['extra_abilities' => [['name' => 'Quickdraw', 'text' => 'Draw a card.']]]);

        $this->get('/characters/gunslinger/edit')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('character.extra_abilities.0.name', 'Quickdraw'));
    }
}
