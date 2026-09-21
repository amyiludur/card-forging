<?php

namespace Tests\Feature;

use App\Models\Scenario;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

/**
 * The setup card: the fifth pile a scenario prints.
 *
 * The steps are the designer's text, one per line. The tool numbers them,
 * prints them on a card, and says nothing when there is nothing to say — a
 * scenario whose setup is not written yet prints no setup card at all, the same
 * way the keyword library ships empty.
 */
class ScenarioSetupTest extends TestCase
{
    use RefreshDatabase;

    private string $path;

    protected function setUp(): void
    {
        parent::setUp();

        $this->path = storage_path('framework/testing/setup-'.uniqid());
        $this->artisan('design:import');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->path);

        parent::tearDown();
    }

    private function kraken(): Scenario
    {
        return Scenario::where('slug', 'kraken')->firstOrFail();
    }

    private function writeSetup(string $setup): Scenario
    {
        $scenario = $this->kraken();
        $scenario->update(['setup' => $setup]);

        return $scenario->fresh();
    }

    public function test_a_step_is_a_line_and_a_blank_line_is_not(): void
    {
        $scenario = $this->writeSetup("Put The Ocean into play.\n\n  Create 3 Tentacles.  \n\nShuffle the deck.\n");

        $this->assertSame(
            ['Put The Ocean into play.', 'Create 3 Tentacles.', 'Shuffle the deck.'],
            $scenario->setupSteps(),
        );
        $this->assertTrue($scenario->hasSetup());
    }

    public function test_a_scenario_with_nothing_written_has_no_setup_card(): void
    {
        $scenario = $this->writeSetup("  \n\n ");

        $this->assertFalse($scenario->hasSetup());
        $this->assertSame([], $scenario->setupSteps());
    }

    public function test_the_card_prints_its_steps_numbered(): void
    {
        $this->writeSetup("Put The Ocean into play.\nCreate 3 Tentacles.");

        $html = $this->get('/print/kraken/sheet?deck=setup')->assertOk()->getContent();

        $this->assertStringContainsString('class="card setup-card"', $html);
        $this->assertStringContainsString('setup-steps', $html);
        $this->assertStringContainsString('<li>Put The Ocean into play.</li>', $html);
        $this->assertStringContainsString('<li>Create 3 Tentacles.</li>', $html);
        // One card, whatever the step count, and nothing else in this pile.
        $this->assertSame(1, substr_count($html, 'class="card setup-card"'));
        $this->assertStringNotContainsString('Tentacle Lash', $html);
    }

    public function test_the_card_prints_the_scenario_s_own_numbers(): void
    {
        $this->writeSetup('Shuffle the deck.');

        // A plain number, set rather than assumed: the folder's own Kraken now
        // counts the players, and PerPlayerTest is where that is checked.
        Scenario::where('slug', 'kraken')->firstOrFail()
            ->update(['starting_dread' => 2, 'starting_dread_equation' => null]);

        $html = $this->get('/print/kraken/sheet?deck=setup')->getContent();

        // The Dread the dial starts on, and how many modules a play asks for.
        $this->assertMatchesRegularExpression('/class="omen">2/', $html);
        $this->assertStringContainsString('2 modules', $html);
        // Not the deck size: only the base deck is shuffled at setup, so the
        // card does not claim a number that would read wrong beside a step.
        $this->assertStringNotContainsString('card deck', $html);
    }

    public function test_a_step_renders_markup_like_any_other_card_text(): void
    {
        $this->writeSetup('Set the omen pool to {config:startingOmen}{omen}. {dreadRule}');

        $html = $this->get('/print/kraken/sheet?deck=setup')->getContent();

        // The scenario's own Dread rule, written out where the token was.
        $this->assertStringContainsString('Create a Tentacle and add 5', $html);
        $this->assertStringNotContainsString('{dreadRule}', $html);
        $this->assertStringNotContainsString('{omen}', $html);
    }

    public function test_the_setup_card_is_a_pile_of_its_own_and_prints_with_everything(): void
    {
        $this->writeSetup('Shuffle the deck.');

        $this->get('/print/kraken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('counts.setup', 1)
                ->where('decks.setup', 'Setup card')
            );

        $everything = $this->get('/print/kraken/sheet?deck=all')->getContent();
        $this->assertStringContainsString('class="card setup-card"', $everything);

        // The other four piles are untouched by it.
        $entity = $this->get('/print/kraken/sheet?deck=entity')->getContent();
        $this->assertStringNotContainsString('class="card setup-card"', $entity);
    }

    public function test_an_unwritten_setup_offers_no_card_to_print(): void
    {
        $this->writeSetup('');

        $this->get('/print/kraken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->missing('counts.setup'));

        $this->assertStringNotContainsString(
            'class="card setup-card"',
            $this->get('/print/kraken/sheet?deck=all')->getContent(),
        );
    }

    public function test_the_scenario_page_shows_the_card_that_will_print(): void
    {
        $this->writeSetup('');

        $this->get('/scenarios/kraken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('setupCard', null));

        $this->writeSetup("Put The Ocean into play.\nShuffle the deck.");

        $this->get('/scenarios/kraken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('setupCard.steps', ['Put The Ocean into play.', 'Shuffle the deck.'])
                ->where('setupCard.starting_dread', 2)
            );
    }

    public function test_the_editor_saves_the_steps_as_typed(): void
    {
        $scenario = $this->kraken();

        $this->put("/scenarios/{$scenario->slug}", [
            'name' => $scenario->name,
            'entity_type' => $scenario->entity_type,
            'setup' => "Put The Ocean into play.\nCreate 3 Tentacles.",
            'starting_dread' => $scenario->starting_dread,
            'traits' => $scenario->traits,
            'modules_required' => $scenario->modules_required,
        ])->assertRedirect();

        $this->assertSame("Put The Ocean into play.\nCreate 3 Tentacles.", $scenario->fresh()->setup);
    }

    public function test_a_folder_with_no_setup_written_grows_no_setup_key(): void
    {
        $this->writeSetup('');

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $kraken = json_decode(
            file_get_contents("{$this->path}/data/kraken.json"), true, 512, JSON_THROW_ON_ERROR
        );

        $this->assertArrayNotHasKey('setup', $kraken);
    }

    public function test_a_setup_is_written_out_and_read_back(): void
    {
        $this->writeSetup("Put The Ocean into play.\nShuffle the deck.");

        $this->artisan('design:export', ['--path' => $this->path])->assertSuccessful();

        $kraken = json_decode(
            file_get_contents("{$this->path}/data/kraken.json"), true, 512, JSON_THROW_ON_ERROR
        );

        $this->assertSame("Put The Ocean into play.\nShuffle the deck.", $kraken['setup']);

        // And back in: the newlines the designer typed survive the round trip.
        $this->writeSetup('gone');
        $this->artisan('design:import', ['--path' => $this->path])->assertSuccessful();

        $this->assertSame("Put The Ocean into play.\nShuffle the deck.", $this->kraken()->setup);
    }
}
