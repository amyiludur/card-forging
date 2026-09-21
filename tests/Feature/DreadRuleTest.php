<?php

namespace Tests\Feature;

use App\Models\BoardCard;
use App\Models\EntityCard;
use App\Models\Module;
use App\Models\Scenario;
use App\Models\StoryBeat;
use App\Models\TownAction;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * {dreadRule} writes a scenario's Dread effect onto a card that belongs to it,
 * so the rule is written once and quoted rather than copied. {dreadAmount} is
 * its pair for the Dread the dial starts on.
 */
class DreadRuleTest extends TestCase
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

    public function test_a_printed_scenario_card_carries_its_scenarios_dread_rule(): void
    {
        $this->kraken()->update(['dread_effect' => 'Create a Tentacle and add 5 omen.']);

        EntityCard::where('name', 'Tentacle Lash')->firstOrFail()
            ->faces()->first()->update(['text' => 'If this is the only card: {dreadRule}']);

        $html = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        $this->assertStringContainsString('If this is the only card: Create a Tentacle and add', $html);
        $this->assertStringNotContainsString('{dreadRule}', $html);
    }

    public function test_the_card_follows_the_scenario_rather_than_holding_a_copy(): void
    {
        $card = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();
        $card->faces()->first()->update(['text' => '{dreadRule}']);

        // Editing the scenario, not the card, is what changes what it prints.
        $this->put('/scenarios/kraken', [
            'slug' => 'kraken',
            'name' => $this->kraken()->name,
            'entity_type' => 'creature',
            'dread_effect' => 'The sea takes one of you.',
            'starting_dread' => 2,
            'printed_arrows' => true,
        ])->assertRedirect();

        $html = $this->get('/print/kraken/sheet?deck=entity')->getContent();

        $this->assertStringContainsString('The sea takes one of you.', $html);
    }

    public function test_the_rules_own_markup_renders_on_the_card(): void
    {
        $this->kraken()->update(['dread_effect' => 'Add {config:startingOmen} {omen} to the pool.']);

        EntityCard::where('name', 'Tentacle Lash')->firstOrFail()
            ->faces()->first()->update(['text' => '{dreadRule}']);

        $html = $this->get('/print/kraken/sheet?deck=entity')->getContent();

        // The number and the icon are drawn, not printed as braces.
        $this->assertStringContainsString('markup-config', $html);
        $this->assertStringContainsString('markup-icon-omen', $html);
        $this->assertStringNotContainsString('{config:startingOmen}', $html);
    }

    public function test_a_board_card_a_beat_and_a_district_read_the_same_rule(): void
    {
        $this->kraken()->update(['dread_effect' => 'The sea takes one of you.']);

        BoardCard::where('name', 'The Ocean')->firstOrFail()->update(['text' => 'Ocean: {dreadRule}']);
        StoryBeat::where('order', 1)->firstOrFail()->update(['on_reach' => 'Beat: {dreadRule}']);
        TownAction::where('name', 'Chapel')->firstOrFail()->update(['effect' => 'Chapel: {dreadRule}']);

        $html = $this->get('/print/kraken/sheet?deck=all')->getContent();

        $this->assertStringContainsString('Ocean: The sea takes one of you.', $html);
        $this->assertStringContainsString('Beat: The sea takes one of you.', $html);
        $this->assertStringContainsString('Chapel: The sea takes one of you.', $html);
        // Nothing on the whole sheet is left unfilled, the design folder's own
        // uses of the token included.
        $this->assertStringNotContainsString('{dreadRule}', $html);
    }

    public function test_a_module_card_has_no_one_scenario_so_it_reports_the_token(): void
    {
        // A module is played with whichever scenario the table chose, so there
        // is no one rule to print. Reported, never guessed at.
        $module = Module::where('slug', 'what-lurks-below')->firstOrFail();
        $card = $module->entityCards()->firstOrFail();
        $card->faces()->first()->update(['text' => '{dreadRule}']);

        $html = $this->get("/print/module/{$module->slug}/sheet?deck=entity")->assertOk()->getContent();

        $this->assertStringContainsString('?dreadRule', $html);
    }

    public function test_the_browser_gets_the_same_rule_the_print_sheet_used(): void
    {
        // CardPreview re-renders card text in the browser, so the rule has to
        // travel with the card or the preview and the sheet would disagree.
        $this->kraken()->update(['dread_effect' => 'The sea takes one of you.']);

        $this->get('/scenarios/kraken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cards.0.dread_rule', 'The sea takes one of you.')
                ->where('boardCards.0.dread_rule', 'The sea takes one of you.')
                ->where('beats.0.dread_rule', 'The sea takes one of you.')
            );
    }

    public function test_the_card_editor_offers_the_rule_to_a_scenario_card_only(): void
    {
        $this->kraken()->update(['dread_effect' => 'The sea takes one of you.']);

        $card = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();

        $this->get("/cards/{$card->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('scenario.dread_effect', 'The sea takes one of you.'));

        $moduleCard = Module::where('slug', 'what-lurks-below')->firstOrFail()->entityCards()->firstOrFail();

        $this->get("/cards/{$moduleCard->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('scenario', null));
    }

    public function test_a_printed_scenario_card_carries_its_scenarios_starting_dread(): void
    {
        $this->kraken()->update(['starting_dread' => 3, 'starting_dread_equation' => null]);

        EntityCard::where('name', 'Tentacle Lash')->firstOrFail()
            ->faces()->first()->update(['text' => 'Set the dial to {dreadAmount}.']);

        $html = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        $this->assertStringContainsString('Set the dial to 3.', $html);
        $this->assertStringNotContainsString('{dreadAmount}', $html);
    }

    public function test_a_starting_dread_counting_the_players_prints_the_equation(): void
    {
        // The Kraken's own: a printed card cannot know how many people are at
        // the table, so it carries the equation with the count as the icon.
        EntityCard::where('name', 'Tentacle Lash')->firstOrFail()
            ->faces()->first()->update(['text' => 'Start on {dreadAmount}.']);

        $html = $this->get('/print/kraken/sheet?deck=entity')->assertOk()->getContent();

        $this->assertStringContainsString('Start on 1 + 1<span class="markup-icon markup-icon-perPlayer"', $html);
        $this->assertStringNotContainsString('{perPlayer}', $html);
    }

    public function test_a_setup_step_reads_the_number_too(): void
    {
        // A setup step is card text, so it resolves the token the same way.
        $this->kraken()->update([
            'setup' => 'Set the Dread dial to {dreadAmount}.',
            'starting_dread' => 4,
            'starting_dread_equation' => null,
        ]);

        $html = $this->get('/print/kraken/sheet?deck=setup')->assertOk()->getContent();

        $this->assertStringContainsString('Set the Dread dial to 4.', $html);
    }

    public function test_a_module_card_has_no_one_scenario_so_it_reports_the_number_too(): void
    {
        $module = Module::where('slug', 'what-lurks-below')->firstOrFail();
        $card = $module->entityCards()->firstOrFail();
        $card->faces()->first()->update(['text' => '{dreadAmount}']);

        $html = $this->get("/print/module/{$module->slug}/sheet?deck=entity")->assertOk()->getContent();

        $this->assertStringContainsString('?dreadAmount', $html);
    }

    public function test_the_browser_gets_the_same_number_the_print_sheet_used(): void
    {
        $this->kraken()->update(['starting_dread' => 3, 'starting_dread_equation' => null]);

        $this->get('/scenarios/kraken')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('cards.0.dread_amount', '3')
                ->where('boardCards.0.dread_amount', '3')
                ->where('beats.0.dread_amount', '3')
                // The setup steps render on the page as well as on the card.
                ->where('scenario.dread_amount', '3')
            );
    }

    public function test_the_card_editor_is_given_the_number_as_card_text(): void
    {
        // The equation, not a figure: the editor's preview draws what prints.
        $card = EntityCard::where('name', 'Tentacle Lash')->firstOrFail();

        $this->get("/cards/{$card->id}/edit")
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('scenario.dread_amount', '1 + 1{perPlayer}'));
    }

    public function test_the_design_folder_keeps_the_token_rather_than_the_number(): void
    {
        $this->kraken()->update(['starting_dread' => 3, 'starting_dread_equation' => null]);

        EntityCard::where('name', 'Tentacle Lash')->firstOrFail()
            ->faces()->first()->update(['text' => 'Start on {dreadAmount}.']);

        $target = storage_path('framework/testing/design-export');
        $this->artisan("design:export --path={$target}")->assertExitCode(0);

        $json = file_get_contents($target.'/data/kraken.json');

        $this->assertStringContainsString('{dreadAmount}', $json);
        $this->assertStringNotContainsString('"text": "Start on 3."', $json);
    }

    public function test_the_design_folder_keeps_the_token_rather_than_the_rule(): void
    {
        // The stored text is the designer's: exporting writes {dreadRule} back
        // out, so the rule stays written in one place.
        $this->kraken()->update(['dread_effect' => 'The sea takes one of you.']);

        EntityCard::where('name', 'Tentacle Lash')->firstOrFail()
            ->faces()->first()->update(['text' => '{dreadRule}']);

        $target = storage_path('framework/testing/design-export');
        $this->artisan("design:export --path={$target}")->assertExitCode(0);

        $json = file_get_contents($target.'/data/kraken.json');

        $this->assertStringContainsString('{dreadRule}', $json);
        $this->assertStringNotContainsString('"text": "The sea takes one of you."', $json);
    }
}
