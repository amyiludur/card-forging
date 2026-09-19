<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\PlayerCard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The design folder is the source of truth, so a character has to come back out
 * of the editor in the shape it went in. Anything else and the designer's git
 * history fills with noise they did not make.
 */
class PlayerDesignRoundTripTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/players-'.uniqid());
        $this->artisan('design:import');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function exported(string $slug): array
    {
        return json_decode(
            file_get_contents("{$this->path}/players/{$slug}.json"),
            true,
            512,
            JSON_THROW_ON_ERROR,
        );
    }

    public function test_an_untouched_character_exports_byte_for_byte(): void
    {
        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        foreach (['gunslinger', 'soothsayer'] as $slug) {
            $this->assertSame(
                // The handoff files carry no trailing newline; the exporter adds
                // one, which is the only difference allowed here.
                rtrim(file_get_contents(base_path("design/players/{$slug}.json"))),
                rtrim(file_get_contents("{$this->path}/players/{$slug}.json")),
                "design/players/{$slug}.json did not survive the round trip",
            );
        }
    }

    public function test_the_three_lists_keep_their_order_and_their_keys(): void
    {
        $this->artisan('design:export', ['--path' => $this->path]);

        $gunslinger = $this->exported('gunslinger');

        $this->assertSame(
            ['id', 'name', 'title', 'story', 'status', 'identity', 'health', 'handSize', 'goldPerRound', 'ability', 'kit', 'signatureCards', 'upgrades', 'notes'],
            array_keys($gunslinger),
        );

        $this->assertSame('revolver', $gunslinger['kit'][0]['id']);
        $this->assertSame('Standard Round', $gunslinger['signatureCards'][0]['name']);
        $this->assertCount(5, $gunslinger['upgrades']);
        $this->assertCount(4, $gunslinger['notes']);
    }

    public function test_an_edit_survives_the_round_trip(): void
    {
        PlayerCard::where('slug', 'silver-round')->firstOrFail()->update(['shop_cost' => 4, 'omen_icons' => 1]);
        Character::where('slug', 'soothsayer')->firstOrFail()->update(['health' => 9]);

        $this->artisan('design:export', ['--path' => $this->path]);

        $card = collect($this->exported('gunslinger')['signatureCards'])->firstWhere('id', 'silver-round');

        $this->assertSame(4, $card['shopCost']);
        $this->assertSame(1, $card['omenIcons']);
        $this->assertSame(9, $this->exported('soothsayer')['health']);
        $this->assertSame(2, $this->exported('soothsayer')['goldPerRound']);

        // And reading it back gives the same thing.
        $this->artisan('design:import', ['--path' => $this->path]);

        $this->assertSame(4, PlayerCard::where('slug', 'silver-round')->firstOrFail()->shop_cost);
        $this->assertSame(9, Character::where('slug', 'soothsayer')->firstOrFail()->health);
    }

    public function test_a_card_the_design_folder_dropped_does_not_linger(): void
    {
        $this->artisan('design:export', ['--path' => $this->path]);

        $file = "{$this->path}/players/gunslinger.json";
        $data = json_decode(file_get_contents($file), true, 512, JSON_THROW_ON_ERROR);
        $data['signatureCards'] = array_values(array_filter(
            $data['signatureCards'],
            fn (array $c) => $c['id'] !== 'lucky-coin',
        ));
        file_put_contents($file, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

        $this->artisan('design:import', ['--path' => $this->path]);

        $this->assertNull(PlayerCard::where('slug', 'lucky-coin')->first());
        // Only that card went; the rest of the character is untouched.
        $this->assertSame(19, Character::where('slug', 'gunslinger')->firstOrFail()->signatureCount());
    }

    public function test_a_character_added_in_the_editor_is_exported(): void
    {
        $character = Character::create([
            'slug' => 'tinker',
            'name' => 'Tinker',
            'health' => 9,
            'hand_size' => 5,
            'ability_name' => 'Improvise',
            'ability_text' => 'Once per round, gain 1 {gold}.',
            'sort' => 9,
        ]);

        $character->cards()->create([
            'slug' => 'spanner',
            'name' => 'Spanner',
            'qty' => 2,
            'role' => 'signature',
            'type' => 'item',
            'gold_cost' => 1,
            'omen_icons' => 0,
            'start_zone' => 'deck',
            'text' => 'Repair 1 damage.',
            'traits' => ['Gear'],
        ]);

        $this->artisan('design:export', ['--path' => $this->path]);

        $tinker = $this->exported('tinker');

        $this->assertSame('Tinker', $tinker['name']);
        $this->assertSame([], $tinker['kit']);
        $this->assertSame('spanner', $tinker['signatureCards'][0]['id']);
        $this->assertSame(['Gear'], $tinker['signatureCards'][0]['traits']);
    }
}
