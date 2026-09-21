<?php

namespace Tests\Feature;

use App\Models\Character;
use App\Models\RulesConfig;
use App\Models\Scenario;
use App\Models\StoryBeat;
use App\Support\Markup;
use App\Support\PlayerScaled;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * A number the designer has written as an equation counting the players.
 *
 * The rule throughout is that the equation is the value and the card prints it
 * as written: a printed card cannot know how many people are at the table, so
 * it never claims a figure. Only the playtest table, which knows who is
 * playing, works one out.
 */
class PerPlayerTest extends TestCase
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

    // ---------------------------------------------------------------- reading

    public function test_an_equation_counts_the_players(): void
    {
        $cases = [
            '1 + 1perPlayer' => [1 => 2, 3 => 4, 4 => 5],
            '2 * 1perPlayer' => [1 => 2, 3 => 6, 4 => 8],
            'perPlayer' => [1 => 1, 3 => 3, 4 => 4],
            '3 + 2(perPlayer)' => [1 => 5, 3 => 9, 4 => 11],
            '10 - perPlayer*2' => [1 => 8, 3 => 4, 4 => 2],
            // The token form, as it comes out of card text.
            '1 + 1{perPlayer}' => [1 => 2, 3 => 4, 4 => 5],
            // The designer's own lowercase spelling reads the same.
            '1 + 1 perplayer' => [1 => 2, 3 => 4, 4 => 5],
        ];

        foreach ($cases as $equation => $expected) {
            foreach ($expected as $players => $value) {
                $this->assertSame(
                    $value,
                    PlayerScaled::evaluate($equation, $players),
                    "{$equation} at {$players} players"
                );
            }
        }
    }

    public function test_something_that_is_not_an_equation_is_reported_rather_than_guessed_at(): void
    {
        // No division: a number of players does not divide into halves, and a
        // rounding rule would be a decision about the game.
        foreach (['1 / perPlayer', '1 +', '(1 + perPlayer', 'lots'] as $broken) {
            $this->assertNotNull(PlayerScaled::validate($broken), "{$broken} should be reported");
            $this->assertNull(PlayerScaled::evaluate($broken, 3));
        }
    }

    public function test_a_plain_number_is_still_a_plain_number(): void
    {
        $value = PlayerScaled::make(2);

        $this->assertFalse($value->isScaled());
        $this->assertSame(2, $value->at(4));
        $this->assertSame('2', $value->markup());
        $this->assertSame(2, $value->forDesign());
    }

    // ------------------------------------------------------------- the folder

    public function test_the_design_folder_holds_a_number_or_an_equation_under_one_key(): void
    {
        // One key, read as a number or as an equation depending on what is in
        // it. Which of the two the Kraken is using is the designer's to change,
        // so this checks that the key round-trips as whatever it says.
        $data = json_decode(file_get_contents(base_path('design/data/kraken.json')), true);
        $written = $data['startingDread'];

        if (is_string($written)) {
            $this->assertSame($written, $this->kraken()->starting_dread_equation);
        } else {
            $this->assertNull($this->kraken()->starting_dread_equation);
            $this->assertSame($written, $this->kraken()->starting_dread);
        }
    }

    public function test_a_plain_number_comes_back_out_a_plain_number(): void
    {
        $this->kraken()->update(['starting_dread' => 3, 'starting_dread_equation' => null]);

        $this->assertSame(3, $this->kraken()->startingDread()->forDesign());

        // A character nobody has scaled keeps three plain numbers, so a design
        // folder nobody has scaled comes back out byte for byte.
        $gunslinger = Character::where('slug', 'gunslinger')->firstOrFail();

        $this->assertIsInt($gunslinger->scaledHealth()->forDesign());
        $this->assertIsInt($gunslinger->scaledHandSize()->forDesign());
        $this->assertIsInt($gunslinger->scaledGoldPerRound()->forDesign());
    }

    public function test_an_equation_survives_the_round_trip(): void
    {
        $folder = base_path('storage/framework/testing/design-per-player');

        Character::where('slug', 'gunslinger')->firstOrFail()
            ->update(['health_equation' => '8 + 2perPlayer']);
        StoryBeat::where('order', 1)->firstOrFail()
            ->update(['dread_change_equation' => '1perPlayer']);

        $this->artisan('design:export', ['--path' => $folder]);

        $character = json_decode(file_get_contents("{$folder}/players/gunslinger.json"), true);
        $scenario = json_decode(file_get_contents("{$folder}/data/kraken.json"), true);

        $this->assertSame('8 + 2perPlayer', $character['health']);
        $this->assertSame('1perPlayer', $scenario['storyBeats'][0]['dreadChange']);

        // And back in, unchanged.
        $this->artisan('design:import', ['--path' => $folder]);

        $this->assertSame(
            '8 + 2perPlayer',
            Character::where('slug', 'gunslinger')->firstOrFail()->health_equation
        );
    }

    // -------------------------------------------------------------- the cards

    public function test_the_setup_card_prints_the_equation_rather_than_a_number(): void
    {
        $this->kraken()->update(['starting_dread' => 1, 'starting_dread_equation' => '1 + 1perPlayer']);

        $html = $this->get('/print/kraken/sheet?deck=setup')->assertOk()->getContent();

        // As written, with the player count drawn as the icon — the card cannot
        // know how many people are at the table, so it does not claim a figure.
        $this->assertStringContainsString('1 + 1<span class="markup-icon markup-icon-perPlayer"', $html);
        $this->assertStringNotContainsString('perPlayer</', $html);
    }

    public function test_a_character_card_prints_its_three_numbers_scaled(): void
    {
        Character::where('slug', 'gunslinger')->firstOrFail()->update([
            'health_equation' => '8 + 2perPlayer',
            'gold_per_round_equation' => '1perPlayer',
        ]);

        $html = $this->get('/print/character/gunslinger/sheet?deck=character')->assertOk()->getContent();

        $this->assertStringContainsString('8 + 2<span class="markup-icon markup-icon-perPlayer"', $html);
        $this->assertStringContainsString('1<span class="markup-icon markup-icon-perPlayer"', $html);
    }

    public function test_a_beat_card_prints_its_dread_change_scaled(): void
    {
        StoryBeat::where('order', 2)->firstOrFail()->update(['dread_change_equation' => '1perPlayer']);

        $html = $this->get('/print/kraken/sheet?deck=beats')->assertOk()->getContent();

        $this->assertStringContainsString('markup-icon-perPlayer', $html);
    }

    // ------------------------------------------------------------- the markup

    public function test_the_token_draws_the_icon_and_writes_the_words_in_plain_text(): void
    {
        $markup = Markup::make();

        $this->assertStringContainsString('markup-icon-perPlayer', $markup->toHtml('Add 1 {perPlayer} omen.'));
        $this->assertSame('Add 1 per player omen.', $markup->toPlain('Add 1 {perPlayer} omen.'));
    }

    public function test_a_keyword_can_never_take_the_tokens_name(): void
    {
        // The token regex is lowercase only, which is the whole of the reason
        // {perPlayer} and {dreadRule} cannot collide with a keyword.
        $this->post('/rules/keywords', [
            'token' => 'perPlayer',
            'name' => 'Per player',
        ])->assertSessionHasErrors('token');
    }

    public function test_a_tunable_number_written_as_an_equation_prints_as_one(): void
    {
        // Written here rather than read off the folder: whether any of the
        // designer's own numbers counts the players is theirs to decide, and
        // this is about what happens to one that does.
        $config = RulesConfig::where('key', 'startingOmen')->firstOrFail();
        $config->update(['value_type' => 'equation', 'value' => ['v' => '1 * perPlayer']]);

        $html = Markup::make()->toHtml('Set the omen pool to {config:startingOmen}.');

        $this->assertStringContainsString('1 * <span class="markup-icon markup-icon-perPlayer"', $html);
    }

    // ------------------------------------------------------------- the editor

    public function test_the_editor_reports_an_equation_it_cannot_read(): void
    {
        $this->put('/scenarios/kraken', [
            'slug' => 'kraken',
            'name' => 'The Kraken',
            'entity_type' => 'creature',
            'starting_dread' => 2,
            'starting_dread_equation' => '1 / perPlayer',
            'printed_arrows' => true,
        ])->assertSessionHasErrors('starting_dread_equation');

        // Nothing was written, so the scenario still says what it said.
        $this->assertSame('1 + 1perPlayer', $this->kraken()->starting_dread_equation);
    }

    public function test_clearing_the_equation_leaves_the_number_that_was_there(): void
    {
        $this->put('/scenarios/kraken', [
            'slug' => 'kraken',
            'name' => 'The Kraken',
            'entity_type' => 'creature',
            'starting_dread' => 2,
            'starting_dread_equation' => null,
            'printed_arrows' => true,
        ])->assertRedirect();

        $scenario = $this->kraken();

        $this->assertNull($scenario->starting_dread_equation);
        $this->assertSame(2, $scenario->starting_dread);
    }

    public function test_a_tunable_number_becomes_an_equation_and_back(): void
    {
        $config = RulesConfig::where('key', 'omenAtEndOfRound')->firstOrFail();

        $this->assertSame('int', $config->value_type);

        $this->put('/rules/config', [
            'values' => [['id' => $config->id, 'value' => '1perPlayer', 'is_placeholder' => true]],
        ])->assertRedirect();

        $config->refresh();
        $this->assertSame('equation', $config->value_type);
        $this->assertSame('1perPlayer', $config->raw_value);
        $this->assertSame(3, $config->scaled()->at(3));

        $this->put('/rules/config', [
            'values' => [['id' => $config->id, 'value' => '2', 'is_placeholder' => true]],
        ])->assertRedirect();

        $config->refresh();
        $this->assertSame('int', $config->value_type);
        $this->assertSame(2, $config->raw_value);
    }

    public function test_a_string_that_is_not_an_equation_stays_a_string(): void
    {
        // "deck-bottom" and "top" are strings, not equations: only a string
        // naming the player count is one.
        $this->assertSame('string', RulesConfig::typeFor('deck-bottom'));
        $this->assertSame('equation', RulesConfig::typeFor('1 * perPlayer'));
        $this->assertSame(
            'string',
            RulesConfig::where('key', 'shopPurchaseDestination')->firstOrFail()->value_type
        );
    }
}
